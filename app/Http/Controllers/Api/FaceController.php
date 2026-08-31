<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\WfhRecord;
use App\Services\TelegramService;
use App\Models\Employee;
use App\Models\EmployeeFaceData;
use App\Models\OfficeLocation;
use App\Models\LateForcedLeave;
use App\Models\AutoOtRecord;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Helpers\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceController extends Controller
{
    private string $pythonApiUrl;

    public function __construct()
    {
        $this->pythonApiUrl = config('services.face_api.url', 'http://localhost:8000');
    }

    public function verify(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'image' => 'required|string',
                'type' => 'required|string|in:check_in,check_out,verify_only',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'accuracy' => 'nullable|numeric',
                'custom_location_name' => 'nullable|string|max:255',
            ]);

            $employee = Employee::findOrFail($request->employee_id);

            $faceEncodings = EmployeeFaceData::where('employee_id', $employee->id)
                ->pluck('face_encoding')
                ->toArray();

            if (empty($faceEncodings)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'No face data registered for this employee.',
                ], 400);
            }

            $response = Http::timeout(30)->post("{$this->pythonApiUrl}/api/face/verify", [
                'image' => $request->image,
                'face_encodings' => $faceEncodings,
            ]);

            if (!$response->successful()) {
                Log::error('Face API error: ' . $response->body());
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Face verification service unavailable.',
                ], 503);
            }

            $result = $response->json();
            Log::info("Face verify result for employee {$employee->id}: matched=" . var_export($result['matched'] ?? false, true) . ", distance=" . ($result['distance'] ?? 'N/A'));

            if (!$result['matched'] ?? false) {
                Log::info("Face verification failed for employee {$employee->id}", $result);

                return response()->json([
                    'success' => false,
                    'data' => $result,
                    'message' => 'Face not recognized.',
                ], 401);
            }

            $now = Carbon::now('Asia/Bangkok');

            // ─── verify_only: ยืนยันตัวตนอย่างเดียว ไม่บันทึกเวลา ───
            if ($request->type === 'verify_only') {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'face_match' => $result,
                        'employee' => [
                            'id' => $employee->id,
                            'name' => $employee->name,
                            'employee_code' => $employee->employee_code,
                            'has_ot' => $employee->has_ot,
                        ],
                    ],
                    'message' => 'ยืนยันตัวตนสำเร็จ',
                ]);
            }

            if ($request->type === 'check_in') {
                // หาวันที่เริ่มกะ (รองรับกะข้ามคืน)
                $shiftInfo = $this->getEmployeeShiftInfo($employee, $now);
                $shiftStartDate = $shiftInfo['shift_start_date'];
                $isOvernight = $shiftInfo['is_overnight'];
                $resolvedShift = $shiftInfo['resolved'];

                // ตรวจสอบรอบที่ยังไม่ได้เช็คเอาท์
                $activeRound = AttendanceLog::where('emp_id', $employee->id)
                    ->whereDate('date', $shiftStartDate)
                    ->whereNull('check_out')
                    ->first();

                if ($activeRound) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'กรุณาเช็คเอาท์รอบที่ ' . $activeRound->round_no . ' ก่อนเช็คอินรอบใหม่',
                    ], 400);
                }

                $maxRound = AttendanceLog::where('emp_id', $employee->id)
                    ->whereDate('date', $shiftStartDate)
                    ->max('round_no') ?? 0;
                $nextRound = $maxRound + 1;

                $isRemote = $employee->hasActiveRemoteAssignment();
                $officeLocation = $employee->getAssignedOfficeLocation();

                // ─── คำนวณเวลาเข้างานจริงของกะ ───
                $workStartTime = $resolvedShift['start_time']
                    ? Carbon::parse($shiftStartDate->toDateString() . ' ' . $resolvedShift['start_time'])
                    : null;

                // Fallback to office location
                if (!$workStartTime && $officeLocation && $officeLocation->work_start_time) {
                    $time = $officeLocation->work_start_time instanceof Carbon
                        ? $officeLocation->work_start_time->format('H:i')
                        : $officeLocation->work_start_time;
                    $workStartTime = Carbon::parse($shiftStartDate->toDateString() . ' ' . $time);
                }

                $lateMinutes = 0;
                $originalStatus = 'on_time';

                if ($workStartTime) {
                    $lateMinutes = AttendanceCalculator::calculateLateMinutes($workStartTime, $now);
                    if ($lateMinutes > 0) {
                        $originalStatus = 'late';
                    }
                }

                $scanType = $isRemote ? 'remote_scan' : 'office_scan';
                $remoteLatitude = null;
                $remoteLongitude = null;
                $remoteAccuracy = null;
                $remoteLocationName = null;

                if ($isRemote && $request->latitude && $request->longitude) {
                    $remoteLatitude = $request->latitude;
                    $remoteLongitude = $request->longitude;
                    $remoteAccuracy = $request->accuracy ?? null;
                    $remoteLocationName = $request->custom_location_name ?: $this->reverseGeocode($request->latitude, $request->longitude);
                } elseif (!$isRemote && $officeLocation && $request->latitude && $request->longitude) {
                    $remoteLatitude = $request->latitude;
                    $remoteLongitude = $request->longitude;

                    $distance = $this->calculateDistance(
                        $request->latitude,
                        $request->longitude,
                        $officeLocation->latitude,
                        $officeLocation->longitude
                    );

                    if ($distance > $officeLocation->radius_meters) {
                        return response()->json([
                            'success' => false,
                            'message' => 'คุณอยู่นอกพื้นที่เช็คอิน (ห่าง ' . round($distance) . ' เมตร กรุณาเข้าใกล้สถานที่เช็คอินให้อยู่ในรัศมี ' . $officeLocation->radius_meters . ' เมตร)',
                        ], 400);
                    }
                }

                $latLong = $request->latitude && $request->longitude
                    ? $request->latitude . ',' . $request->longitude
                    : null;

                $log = AttendanceLog::create([
                    'emp_id' => $employee->id,
                    'company_id' => $employee->company_id,
                    'date' => $shiftStartDate,
                    'round_no' => $nextRound,
                    'check_in' => $now,
                    'check_in_status' => $originalStatus,
                    'original_status' => $originalStatus,
                    'final_status' => $originalStatus,
                    'late_minutes' => $lateMinutes > 0 ? $lateMinutes : null,
                    'lat_long' => $latLong,
                    'scan_type' => $scanType,
                    'remote_latitude' => $remoteLatitude,
                    'remote_longitude' => $remoteLongitude,
                    'remote_accuracy' => $remoteAccuracy,
                    'remote_location_name' => $remoteLocationName,
                    'is_verified' => true,
                ]);

                // ─── สายเกิน 30 นาที → บังคับลากิจ 1 ชม. ───
                if ($lateMinutes > 30) {
                    $this->createForcedLeaveIfApplicable($employee, $log, $shiftStartDate, $lateMinutes);
                }

                // ─── ตรวจจับ OT ก่อนเวลา (มาเร็ว ≥ 1 ชม.) ───
                if ($workStartTime && $employee->has_ot) {
                    $this->detectBeforeShiftOt($employee, $log, $shiftStartDate, $workStartTime, $now);
                }

                $locationLabel = $isRemote
                    ? ($remoteLocationName ?: 'ตำแหน่งปัจจุบัน')
                    : ($officeLocation->name ?? 'ออฟฟิศ');

                $roundLabel = $nextRound > 1 ? ' (รอบที่ ' . $nextRound . ')' : '';

                $statusMessage = $originalStatus === 'late'
                    ? 'สถานะ: สาย (' . $lateMinutes . ' นาที)'
                    : 'สถานะ: ปกติ';

                $lateForceMsg = '';
                if ($lateMinutes > 30) {
                    $existingLeave = $this->checkExistingLeave($employee, $shiftStartDate);
                    if ($existingLeave) {
                        $leaveTypeLabel = $existingLeave->leaveType->name ?? $existingLeave->leave_type;
                        $lateForceMsg = ' (ลา: ' . $leaveTypeLabel . ' 1 ชม.)';
                    } else {
                        $lateForceMsg = ' (บังคับลากิจ 1 ชม.)';
                    }
                }

                $overnightMsg = $isOvernight ? ' [ข้ามคืน]' : '';

                return response()->json([
                    'success' => true,
                    'data' => [
                        'attendance_log' => $log,
                        'face_match' => $result,
                    ],
                    'message' => 'เช็คอินสำเร็จ' . $roundLabel . ' (' . $locationLabel . ')'
                        . "\n" . $statusMessage . $lateForceMsg . $overnightMsg
                        . ($isRemote ? ' [นอกสถานที่]' : ''),
                ], 201);
            }

            if ($request->type === 'check_out') {
                // หาวันที่เริ่มกะ
                $shiftInfo = $this->getEmployeeShiftInfo($employee, $now);
                $shiftStartDate = $shiftInfo['shift_start_date'];

                Log::info("Face check-out for employee {$employee->id}, shift_start_date={$shiftStartDate}");

                $log = AttendanceLog::where('emp_id', $employee->id)
                    ->whereDate('date', $shiftStartDate)
                    ->whereNull('check_out')
                    ->orderBy('round_no', 'desc')
                    ->first();

                if (!$log) {
                    Log::warning("No open attendance log for employee {$employee->id} on {$shiftStartDate}");
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'ไม่พบรายการเช็คอินที่ยังไม่ได้เช็คเอาท์',
                    ], 400);
                }

                // ─── GPS check-out enforcement ───
                $isRemote = $employee->hasActiveRemoteAssignment();
                $officeLocation = $employee->getAssignedOfficeLocation();

                if (!$isRemote && $officeLocation && $request->latitude && $request->longitude) {
                    $distance = $this->calculateDistance(
                        $request->latitude,
                        $request->longitude,
                        $officeLocation->latitude,
                        $officeLocation->longitude
                    );

                    if ($distance > $officeLocation->radius_meters) {
                        return response()->json([
                            'success' => false,
                            'message' => 'คุณอยู่นอกพื้นที่เช็คเอาท์ (ห่าง ' . round($distance) . ' เมตร กรุณาเข้าใกล้สถานที่เช็คเอาท์ให้อยู่ในรัศมี ' . $officeLocation->radius_meters . ' เมตร)',
                        ], 400);
                    }
                }

                $updateData = ['check_out' => $now];

                if ($request->latitude && $request->longitude) {
                    $updateData['remote_latitude'] = $request->latitude;
                    $updateData['remote_longitude'] = $request->longitude;
                    $updateData['remote_accuracy'] = $request->accuracy ?? null;
                }

                $log->update($updateData);

                // ─── ตรวจจับ OT หลังเวลา (กลับช้า ≥ 1 ชม.) ───
                $shiftInfo = $this->getEmployeeShiftInfo($employee, $now);
                $resolvedShift = $shiftInfo['resolved'];
                if ($resolvedShift && $resolvedShift['end_time'] && $employee->has_ot) {
                    $this->detectAfterShiftOt($employee, $log, $shiftStartDate, $resolvedShift['end_time'], $now);
                }

                $roundLabel = $log->round_no > 1 ? ' (รอบที่ ' . $log->round_no . ')' : '';

                return response()->json([
                    'success' => true,
                    'data' => [
                        'attendance_log' => $log->fresh(),
                        'face_match' => $result,
                    ],
                    'message' => 'เช็คเอาท์สำเร็จ' . $roundLabel,
                ]);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Invalid type. Must be check_in or check_out.',
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Employee not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Face verify error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Face verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $hasEncodings = $request->has('encodings') && is_array($request->encodings) && count($request->encodings) >= 5;
            $hasImages = $request->has('images') && is_array($request->images) && count($request->images) >= 5;

            if (!$hasEncodings && !$hasImages) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'กรุณาถ่ายรูปอย่างน้อย 5 รูป',
                ], 422);
            }

            $employee = Employee::findOrFail($request->employee_id);

            if ($hasEncodings) {
                $encodings = $request->encodings;
                $angles = ['front', 'left', 'right', 'up', 'down'];

                EmployeeFaceData::where('employee_id', $employee->id)->delete();

                $savedFaceData = [];
                foreach ($encodings as $i => $encoding) {
                    $angle = $angles[$i] ?? "angle_{$i}";
                    $faceData = EmployeeFaceData::create([
                        'employee_id' => $employee->id,
                        'angle' => $angle,
                        'face_encoding' => is_string($encoding) ? $encoding : json_encode($encoding),
                    ]);
                    $savedFaceData[] = $faceData;
                }

                return response()->json([
                    'success' => true,
                    'data' => ['face_data' => $savedFaceData],
                    'message' => 'ลงทะเบียนใบหน้าสำเร็จ ' . count($savedFaceData) . ' รูป',
                ]);
            }

            try {
                $response = Http::timeout(60)->post("{$this->pythonApiUrl}/api/face/encode", [
                    'images' => $request->images,
                ]);
            } catch (\Exception $e) {
                Log::error('Face API connection error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Face encoding service unavailable. Please try again.',
                ], 503);
            }

            if ($response->status() === 422) {
                $body = $response->json();
                $detail = $body['detail'] ?? [];
                $errors = $detail['errors'] ?? [];
                $angleNames = ['front' => 'หน้าตรง', 'left' => 'ซ้าย', 'right' => 'ขวา', 'up' => 'มองขึ้น', 'down' => 'มองลง'];
                $angleKeys = ['front', 'left', 'right', 'up', 'down'];
                $errorMessages = array_map(function ($err) use ($angleNames, $angleKeys) {
                    $idx = $err['index'] ?? 0;
                    $key = $angleKeys[$idx] ?? null;
                    $label = $key ? ($angleNames[$key] ?? $key) : ("รูปที่ " . ($idx + 1));
                    return $label . ': ' . ($err['detail'] ?? $err['error'] ?? 'ไม่พบใบหน้า');
                }, $errors);

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'ไม่พบใบหน้าในบางรูป: ' . implode(', ', $errorMessages),
                    'errors' => $errors,
                ], 422);
            }

            if (!$response->successful()) {
                Log::error('Face API encode error: ' . $response->body());
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Face encoding service error.',
                ], 503);
            }

            $result = $response->json();
            $encodings = $result['encodings'] ?? [];
            $successIndices = $result['indices'] ?? [];
            $apiErrors = $result['errors'] ?? [];

            if (empty($encodings)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'No faces detected in the provided images.',
                ], 422);
            }

            $angles = ['front', 'left', 'right', 'up', 'down'];
            $savedFaceData = [];

            EmployeeFaceData::where('employee_id', $employee->id)->delete();

            foreach ($encodings as $i => $encoding) {
                $originalIndex = $successIndices[$i] ?? $i;
                $angle = $angles[$originalIndex] ?? "angle_{$originalIndex}";

                $faceData = EmployeeFaceData::create([
                    'employee_id' => $employee->id,
                    'angle' => $angle,
                    'face_encoding' => is_string($encoding) ? $encoding : json_encode($encoding),
                ]);

                $savedFaceData[] = $faceData;
            }

            $warning = null;
            if (!empty($apiErrors)) {
                $angleLabelMap = ['front' => 'หน้าตรง', 'left' => 'ซ้าย', 'right' => 'ขวา', 'up' => 'มองขึ้น', 'down' => 'มองลง'];
                $angleKeys2 = ['front', 'left', 'right', 'up', 'down'];
                $failedAngles = array_map(function ($err) use ($angleLabelMap, $angleKeys2) {
                    $idx = $err['index'] ?? 0;
                    $key = $angleKeys2[$idx] ?? null;
                    return $key ? ($angleLabelMap[$key] ?? $key) : ("รูปที่ " . ($idx + 1));
                }, $apiErrors);
                $warning = 'บันทึกสำเร็จ ' . count($savedFaceData) . ' รูป แต่บางรูปไม่พบใบหน้า: ' . implode(', ', $failedAngles);
            }

            return response()->json([
                'success' => true,
                'data' => $savedFaceData,
                'message' => $warning ?: ('Face data registered successfully for ' . count($savedFaceData) . ' angles.'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Employee not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Face register error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Face registration failed.',
            ], 500);
        }
    }

    // ─── Private helpers ───

    /**
     * หาข้อมูลกะของพนักงาน + วันที่เริ่มกะ (รองรับข้ามคืน)
     *
     * @return array{resolved: array, shift_start_date: Carbon, is_overnight: bool}
     */
    private function getEmployeeShiftInfo(Employee $employee, Carbon $now): array
    {
        $todayStr = $now->toDateString();
        $resolvedToday = \App\Services\ShiftResolver::resolve($employee, $todayStr);

        // Handle overnight shifts
        if ($resolvedToday['is_overnight'] ?? false) {
            $endTime = $resolvedToday['end_time'] ?? null;
            if ($endTime) {
                $endCarbon = Carbon::parse($endTime);
                if ($now->format('H:i') < $endCarbon->format('H:i')) {
                    $yesterdayStr = Carbon::yesterday()->toDateString();
                    $resolvedYesterday = \App\Services\ShiftResolver::resolve($employee, $yesterdayStr);
                    return [
                        'resolved' => $resolvedYesterday['start_time'] ? $resolvedYesterday : $resolvedToday,
                        'shift_start_date' => Carbon::yesterday(),
                        'is_overnight' => true,
                    ];
                }
            }
        }

        return [
            'resolved' => $resolvedToday,
            'shift_start_date' => $now->copy()->startOfDay(),
            'is_overnight' => $resolvedToday['is_overnight'] ?? false,
        ];
    }

    /**
     * หาเวลาเข้างานจากกะ (ค้ำด้วย ShiftResolver)
     * ตรวจสอบว่าพนักงานมีลามาแล้วหรือยัง
     */
    private function checkExistingLeave(Employee $employee, Carbon $date): ?LeaveRequest
    {
        return LeaveRequest::where('emp_id', $employee->id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where('status', 'approved')
            ->first();
    }

    /**
     * สร้างบันทึกลาบังคับ (ลากิจ 1 ชม.) ถ้าสายเกิน 30 นาที
     */
    private function createForcedLeaveIfApplicable(
        Employee $employee,
        AttendanceLog $log,
        Carbon $shiftStartDate,
        int $lateMinutes
    ): void {
        // ตรวจสอบว่ามีบันทึกลาบังคับแล้วหรือยัง
        $existing = LateForcedLeave::where('emp_id', $employee->id)
            ->where('date', $shiftStartDate)
            ->exists();
        if ($existing) {
            return;
        }

        // ตรวจสอบลามาแล้ว
        $existingLeave = $this->checkExistingLeave($employee, $shiftStartDate);
        if ($existingLeave) {
            $leaveType = $existingLeave->leaveType;
            LateForcedLeave::create([
                'emp_id' => $employee->id,
                'attendance_log_id' => $log->id,
                'date' => $shiftStartDate,
                'late_minutes' => $lateMinutes,
                'leave_minutes' => 60,
                'leave_type' => $leaveType->name ?? 'personal',
                'leave_request_id' => $existingLeave->id,
                'status' => 'approved',
                'reason' => 'สายเกิน 30 นาที (มีลามาแล้ว: ' . ($leaveType->name ?? 'ลากิจ') . ')',
            ]);
        } else {
            LateForcedLeave::create([
                'emp_id' => $employee->id,
                'attendance_log_id' => $log->id,
                'date' => $shiftStartDate,
                'late_minutes' => $lateMinutes,
                'leave_minutes' => 60,
                'leave_type' => 'personal',
                'status' => 'pending',
                'reason' => 'สายเกิน 30 นาที (' . $lateMinutes . ' นาที) → บังคับลากิจ 1 ชม.',
            ]);
        }

        // Send late warning notification
        $this->sendLateWarningNotification($employee, $lateMinutes, $shiftStartDate);
    }

    private function sendLateWarningNotification(Employee $employee, int $lateMinutes, Carbon $date): void
    {
        try {
            $telegram = new TelegramService();
            $companyName = $employee->company->name ?? '-';
            $dateStr = $date->format('d/m/Y');

            $message = "⚠️ <b>แจ้งเตือนสาย</b>\n\n";
            $message .= "👤 <b>ชื่อ:</b> {$employee->name}\n";
            $message .= "🏢 <b>บริษัท:</b> $companyName\n";
            $message .= "📅 <b>วันที่:</b> $dateStr\n";
            $message .= "⏰ <b>สาย:</b> $lateMinutes นาที\n";
            if ($lateMinutes > 30) {
                $message .= "📝 <b>สถานะ:</b> บังคับลากิจ 1 ชม.\n";
            }

            if ($employee->telegram_chat_id) {
                $telegram->sendToChat($employee->telegram_chat_id, $message);
            }

            // Send to supervisor via company group
            if ($employee->company) {
                $telegram->sendToCompanyGroups($employee->company_id, $message, 'attendance');
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    /**
     * ตรวจจับ OT ก่อนเวลา (มาเร็ว ≥ 1 ชม. ก่อนเวลาเริ่มงาน)
     */
    private function detectBeforeShiftOt(
        Employee $employee,
        AttendanceLog $log,
        Carbon $shiftStartDate,
        Carbon $workStartTime,
        Carbon $checkInTime
    ): void {
        // ตรวจสอบว่ามี auto_ot แล้วหรือยัง
        $existing = AutoOtRecord::where('emp_id', $employee->id)
            ->where('date', $shiftStartDate)
            ->where('ot_type', 'before_shift')
            ->exists();
        if ($existing) return;

        // ถ้ามาเร็วกว่าเวลาเริ่มงาน ≥ 60 นาที
        if ($checkInTime->lt($workStartTime)) {
            $otMinutes = (int) $checkInTime->diffInMinutes($workStartTime);
            if ($otMinutes >= 60) {
                AutoOtRecord::create([
                    'emp_id' => $employee->id,
                    'attendance_log_id' => $log->id,
                    'date' => $shiftStartDate,
                    'ot_type' => 'before_shift',
                    'actual_time' => $checkInTime->format('H:i:s'),
                    'shift_time' => $workStartTime->format('H:i:s'),
                    'ot_minutes' => $otMinutes,
                    'status' => 'pending',
                    'reason' => 'มาเร็วก่อนเวลาเริ่มงาน ' . $otMinutes . ' นาที',
                ]);
            }
        }
    }

    /**
     * ตรวจจับ OT หลังเวลา (กลับช้า ≥ 1 ชม. หลังเวลาเลิกงาน)
     */
    private function detectAfterShiftOt(
        Employee $employee,
        AttendanceLog $log,
        Carbon $shiftStartDate,
        string $endTime,
        Carbon $checkOutTime
    ): void {
        $existing = AutoOtRecord::where('emp_id', $employee->id)
            ->where('date', $shiftStartDate)
            ->where('ot_type', 'after_shift')
            ->exists();
        if ($existing) return;

        $shiftEnd = Carbon::parse($shiftStartDate->toDateString() . ' ' . $endTime);

        // ถ้าเลิกงานช้ากว่าเวลาจบทะ ≥ 60 นาที
        if ($checkOutTime->gt($shiftEnd)) {
            $otMinutes = (int) $shiftEnd->diffInMinutes($checkOutTime);
            if ($otMinutes >= 60) {
                AutoOtRecord::create([
                    'emp_id' => $employee->id,
                    'attendance_log_id' => $log->id,
                    'date' => $shiftStartDate,
                    'ot_type' => 'after_shift',
                    'actual_time' => $checkOutTime->format('H:i:s'),
                    'shift_time' => $shiftEnd->format('H:i:s'),
                    'ot_minutes' => $otMinutes,
                    'status' => 'pending',
                    'reason' => 'กลับช้าหลังเวลาเลิกงาน ' . $otMinutes . ' นาที',
                ]);
            }
        }
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function reverseGeocode(float $lat, float $lon): ?string
    {
        try {
            $response = Http::timeout(5)->get("https://nominatim.openstreetmap.org/reverse", [
                'lat' => $lat,
                'lon' => $lon,
                'format' => 'json',
                'accept-language' => 'th',
            ]);

            if ($response->successful()) {
                return $response->json('display_name');
            }
        } catch (\Exception $e) {
            Log::warning('Reverse geocode failed: ' . $e->getMessage());
        }
        return null;
    }

    private function sendTelegramNotification($employee, $type, $time, $latLong = null, $distanceInfo = null)
    {
        try {
            $telegram = new TelegramService();
            $companyName = $employee->company->name ?? '-';
            $statusText = $type === 'check_in' ? 'เช็คอิน' : 'เช็คออก';
            $emoji = $type === 'check_in' ? '🟢' : '🔴';
            
            $message = "$emoji <b>$statusText สำเร็จ</b>\n\n";
            $message .= "👤 <b>ชื่อ:</b> {$employee->name}\n";
            $message .= "🏢 <b>บริษัท:</b> $companyName\n";
            $message .= "🕐 <b>เวลา:</b> $time\n";
            if ($latLong) {
                $message .= "📍 <b>GPS:</b> $latLong\n";
            }
            if ($distanceInfo) {
                $message .= "📏 <b>ระยะห่าง:</b> $distanceInfo\n";
            }

            // Send to employee
            if ($employee->telegram_chat_id) {
                $telegram->sendToChat($employee->telegram_chat_id, $message);
            }

            // Send to supervisor
            if ($employee->supervisor && $employee->supervisor->telegram_chat_id) {
                $supMessage = "$emoji <b>$statusText - {$employee->name}</b>\n\n";
                $supMessage .= "🏢 <b>บริษัท:</b> $companyName\n";
                $supMessage .= "🕐 <b>เวลา:</b> $time\n";
                if ($distanceInfo) {
                    $supMessage .= "📏 <b>ระยะห่าง:</b> $distanceInfo\n";
                }
                $telegram->sendToChat($employee->supervisor->telegram_chat_id, $supMessage);
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

}