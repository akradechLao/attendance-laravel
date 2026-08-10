<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\EmployeeFaceData;
use App\Models\OfficeLocation;
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
                'type' => 'required|string|in:check_in,check_out',
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

            if (!$result['matched'] ?? false) {
                Log::info("Face verification failed for employee {$employee->id}", $result);

                return response()->json([
                    'success' => false,
                    'data' => $result,
                    'message' => 'Face not recognized.',
                ], 401);
            }

            $today = Carbon::today();

            if ($request->type === 'check_in') {
                $existingLog = AttendanceLog::where('emp_id', $employee->id)
                    ->whereDate('date', $today)
                    ->first();

                if ($existingLog) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'Already checked in today.',
                    ], 400);
                }

                $isRemote = $employee->hasActiveRemoteAssignment();
                $officeLocation = $employee->getAssignedOfficeLocation();

                $status = 'on_time';
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
                            'data' => null,
                            'message' => 'Outside office location radius.',
                        ], 400);
                    }

                    if ($officeLocation->work_start_time) {
                        $workStartTime = Carbon::parse($officeLocation->work_start_time);
                        if (Carbon::now()->gt($workStartTime)) {
                            $status = 'late';
                        }
                    }
                } elseif (!$isRemote && $officeLocation && !$request->latitude) {
                    if ($officeLocation->work_start_time) {
                        $workStartTime = Carbon::parse($officeLocation->work_start_time);
                        if (Carbon::now()->gt($workStartTime)) {
                            $status = 'late';
                        }
                    }
                }

                $latLong = $request->latitude && $request->longitude
                    ? $request->latitude . ',' . $request->longitude
                    : null;

                $log = AttendanceLog::create([
                    'emp_id' => $employee->id,
                    'date' => $today,
                    'check_in' => Carbon::now(),
                    'check_in_status' => $status,
                    'lat_long' => $latLong,
                    'scan_type' => $scanType,
                    'remote_latitude' => $remoteLatitude,
                    'remote_longitude' => $remoteLongitude,
                    'remote_accuracy' => $remoteAccuracy,
                    'remote_location_name' => $remoteLocationName,
                ]);

                $locationLabel = $isRemote
                    ? ($remoteLocationName ?: 'ตำแหน่งปัจจุบัน')
                    : ($officeLocation->name ?? 'ออฟฟิศ');

                return response()->json([
                    'success' => true,
                    'data' => [
                        'attendance_log' => $log,
                        'face_match' => $result,
                    ],
                    'message' => 'เช็คอินสำเร็จ (' . $locationLabel . ') '
                        . ($status === 'late' ? 'สถานะ: สาย' : 'สถานะ: ตรงเวลา')
                        . ($isRemote ? ' [นอกสถานที่]' : ''),
                ], 201);
            }

            if ($request->type === 'check_out') {
                $log = AttendanceLog::where('emp_id', $employee->id)
                    ->whereDate('date', $today)
                    ->whereNull('check_out')
                    ->first();

                if (!$log) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'No active check-in found for today.',
                    ], 400);
                }

                $updateData = ['check_out' => Carbon::now()];

                if ($request->latitude && $request->longitude) {
                    $updateData['remote_latitude'] = $request->latitude;
                    $updateData['remote_longitude'] = $request->longitude;
                    $updateData['remote_accuracy'] = $request->accuracy ?? null;
                }

                $log->update($updateData);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'attendance_log' => $log->fresh(),
                        'face_match' => $result,
                    ],
                    'message' => 'เช็คเอาท์สำเร็จ',
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
}
