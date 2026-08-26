<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\OfficeLocation;
use App\Models\RemoteAssignment;
use App\Services\AttendanceService;
use App\Services\LocationService;
use App\Constants\RoleConstants;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    protected AttendanceService $attendanceService;
    protected LocationService $locationService;

    public function __construct(AttendanceService $attendanceService, LocationService $locationService)
    {
        $this->attendanceService = $attendanceService;
        $this->locationService = $locationService;
    }

    public function checkIn(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'scan_type' => 'nullable|in:office_scan,remote_scan',
                'custom_location_name' => 'nullable|string|max:255',
            ]);

            $employee = Employee::findOrFail($request->employee_id);
            $today = Carbon::now('Asia/Bangkok')->today();
            $scanType = $request->get('scan_type', 'office_scan');

            // Check if already checked in today
            $existingLog = AttendanceLog::where('emp_id', $employee->id)
                ->whereDate('date', $today)
                ->first();

            if ($existingLog && $existingLog->check_in) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already checked in today.',
                ], 400);
            }

            $locationName = null;
            $isRemoteScan = false;

            if ($scanType === 'remote_scan') {
                // Verify employee has active remote assignment
                $hasRemote = $employee->hasActiveRemoteAssignment();
                if (!$hasRemote) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No active remote assignment. Please check in at office.',
                    ], 400);
                }
                $isRemoteScan = true;
                $locationName = $this->locationService->reverseGeocode(
                    $request->latitude,
                    $request->longitude
                );
            } else {
                // Office scan - verify within assigned office radius
                $officeLocations = $employee->officeLocations()->where('is_active', true)->get();

                if ($officeLocations->isEmpty()) {
                    $officeLocations = OfficeLocation::where('company_id', $employee->company_id)
                        ->where('is_active', true)
                        ->get();
                }

                $withinAnyOffice = false;
                $nearestDistance = null;

                foreach ($officeLocations as $officeLocation) {
                    $distance = $this->locationService->calculateDistance(
                        $request->latitude,
                        $request->longitude,
                        $officeLocation->latitude,
                        $officeLocation->longitude
                    );

                    if ($distance <= $officeLocation->radius_meters) {
                        $withinAnyOffice = true;
                        break;
                    }

                    if ($nearestDistance === null || $distance < $nearestDistance) {
                        $nearestDistance = $distance;
                        $nearestOffice = $officeLocation;
                    }
                }

                if (!$withinAnyOffice && $nearestOffice) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Outside office radius. Nearest: ' . $nearestOffice->name . ' (' . round($nearestDistance) . 'm)',
                    ], 400);
                }
            }

            // Calculate late/on_time
            $officeStartTime = Carbon::now('Asia/Bangkok')->today()->setTime(8, 30, 0);
            $checkInTime = Carbon::now('Asia/Bangkok');
            $isLate = $checkInTime->gt($officeStartTime);
            $status = $isLate ? 'late' : 'on_time';

            // Create log
            $log = AttendanceLog::create([
                'emp_id' => $employee->id,
                'date' => $today,
                'check_in' => $checkInTime->format('H:i:s'),
                'check_in_status' => $status,
                'scan_type' => $scanType,
                'remote_latitude' => $isRemoteScan ? $request->latitude : null,
                'remote_longitude' => $isRemoteScan ? $request->longitude : null,
                'remote_location_name' => $locationName,
                'remote_custom_name' => $request->get('custom_location_name'),
                'is_verified' => true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $log,
                'message' => 'Check-in successful. Status: ' . ($isLate ? 'Late' : 'On Time'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Check-in failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkOut(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'scan_type' => 'nullable|in:office_scan,remote_scan',
                'custom_location_name' => 'nullable|string|max:255',
            ]);

            $employee = Employee::findOrFail($request->employee_id);
            $today = Carbon::now('Asia/Bangkok')->today();

            $log = AttendanceLog::where('emp_id', $employee->id)
                ->whereDate('date', $today)
                ->whereNull('check_out')
                ->first();

            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active check-in found for today.',
                ], 400);
            }

            // ─── GPS check-out enforcement ───
            $scanType = $request->get('scan_type', 'office_scan');
            if ($scanType === 'office_scan' && $request->latitude && $request->longitude) {
                $officeLocations = $employee->officeLocations()->where('is_active', true)->get();
                if ($officeLocations->isEmpty()) {
                    $officeLocations = OfficeLocation::where('company_id', $employee->company_id)
                        ->where('is_active', true)->get();
                }

                $withinAnyOffice = false;
                foreach ($officeLocations as $officeLocation) {
                    $distance = $this->locationService->calculateDistance(
                        $request->latitude, $request->longitude,
                        $officeLocation->latitude, $officeLocation->longitude
                    );
                    if ($distance <= $officeLocation->radius_meters) {
                        $withinAnyOffice = true;
                        break;
                    }
                }

                if (!$withinAnyOffice) {
                    return response()->json([
                        'success' => false,
                        'message' => 'คุณอยู่นอกพื้นที่เช็คเอาท์ กรุณาเข้าใกล้สถานที่เช็คเอาท์ให้อยู่ในรัศมีที่กำหนด',
                    ], 400);
                }
            }

            $updateData = ['check_out' => Carbon::now('Asia/Bangkok')->format('H:i:s')];

            if ($request->has('latitude') && $request->has('longitude')) {
                $scanType = $request->get('scan_type', 'office_scan');
                
                if ($scanType === 'remote_scan') {
                    $locationName = $this->locationService->reverseGeocode(
                        $request->latitude,
                        $request->longitude
                    );
                    $updateData['scan_type'] = 'remote_scan';
                    $updateData['remote_latitude'] = $request->latitude;
                    $updateData['remote_longitude'] = $request->longitude;
                    $updateData['remote_location_name'] = $locationName;
                    $updateData['remote_custom_name'] = $request->get('custom_location_name');
                } else {
                    $updateData['remote_latitude'] = $request->latitude;
                    $updateData['remote_longitude'] = $request->longitude;
                }
            }

            $log->update($updateData);

            return response()->json([
                'success' => true,
                'data' => $log->fresh(),
                'message' => 'Check-out successful.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Check-out failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function today(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();
            $today = Carbon::now('Asia/Bangkok')->today();

            $log = AttendanceLog::where('emp_id', $employee->id)
                ->whereDate('date', $today)
                ->first();

            if (!$log) {
                return response()->json(['success' => true, 'data' => null]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $log->id,
                    'emp_id' => $log->emp_id,
                    'date' => Carbon::parse($log->date)->setTimezone('Asia/Bangkok')->format('Y-m-d'),
                    'check_in' => $log->check_in ? Carbon::parse($log->check_in)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                    'check_out' => $log->check_out ? Carbon::parse($log->check_out)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                    'status' => $log->status,
                    'late_minutes' => (int) ($log->late_minutes ?? 0),
                    'scan_type' => $log->scan_type,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function history(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $userRole = $user->role ?? 'employee';

            if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
                $id = $user->id;
            }

            $query = AttendanceLog::where('emp_id', $id)->with('employee');

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('date', [
                    $request->start_date,
                    $request->end_date,
                ]);
            }

            $logs = $query->orderBy('date', 'desc')
                ->paginate($request->get('per_page', 15));

            $logs->getCollection()->transform(fn($log) => [
                'id' => $log->id,
                'emp_id' => $log->emp_id,
                'date' => Carbon::parse($log->date)->setTimezone('Asia/Bangkok')->format('Y-m-d'),
                'check_in' => $log->check_in ? Carbon::parse($log->check_in)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                'check_out' => $log->check_out ? Carbon::parse($log->check_out)->setTimezone('Asia/Bangkok')->format('H:i') : null,
                'status' => $log->status,
                'late_minutes' => (int) ($log->late_minutes ?? 0),
                'early_minutes' => (int) ($log->early_minutes ?? 0),
                'overtime_minutes' => (int) ($log->overtime_minutes ?? 0),
                'schedule_start' => $log->schedule_start,
                'schedule_end' => $log->schedule_end,
                'employee' => $log->employee ? ['id' => $log->employee->id, 'employee_code' => $log->employee->employee_code, 'first_name' => $log->employee->first_name, 'last_name' => $log->employee->last_name] : null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve history: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function monthly(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020',
            ]);

            $startDate = Carbon::create($request->year, $request->month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $totalEmployees = Employee::where('company_id', $request->company_id)
                ->count();

            $attendance = AttendanceLog::whereHas('employee', function ($q) use ($request) {
                $q->where('company_id', $request->company_id);
            })
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $presentDays = $attendance->pluck('emp_id')->unique()->count();
            $lateCount = $attendance->where('check_in_status', 'late')->count();
            $onTimeCount = $attendance->where('check_in_status', 'on_time')->count();
            $remoteCount = $attendance->where('scan_type', 'remote_scan')->count();

            $daysInMonth = $startDate->daysInMonth;
            $absentCount = ($totalEmployees * $daysInMonth) - $attendance->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees' => $totalEmployees,
                    'present_days' => $presentDays,
                    'late_count' => $lateCount,
                    'on_time_count' => $onTimeCount,
                    'remote_count' => $remoteCount,
                    'absent_count' => max(0, $absentCount),
                    'total_records' => $attendance->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve monthly stats: ' . $e->getMessage(),
            ], 500);
        }
    }
}
