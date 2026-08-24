<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\OtRequestController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OfficeLocationController;
use App\Http\Controllers\Api\FaceController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\RemoteController;
use App\Http\Controllers\Api\WfhController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SupervisorController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\Api\CompanySettingsController;
use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\EmployeeHistoryController;
use App\Http\Controllers\Api\EmployeeRequestController;
use App\Http\Controllers\Api\AttendanceAdjustmentController;
use App\Http\Controllers\Api\AttendanceVerificationController;
use App\Http\Controllers\Api\AutoOtController;
use App\Http\Controllers\Api\TelegramController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes - Server time (for kiosk clock sync)
Route::get('/time', function () {
    $now = now('Asia/Bangkok');
    return response()->json([
        'time' => $now->format('Y-m-d\TH:i:s.v') . '+07:00',
        'timezone' => 'Asia/Bangkok',
    ]);
});

// Public routes - Employee authentication (kiosk)
Route::post('/employee/auth/search', [EmployeeAuthController::class, 'search']);
Route::post('/employee/auth/verify', [EmployeeAuthController::class, 'verify']);

// Public routes - Face recognition
Route::post('/face/verify', [FaceController::class, 'verify']);

// Public routes - Face detect (single photo validation for kiosk registration)
Route::post('/face/detect', function () {
    $request = request();
    $request->validate(['image' => 'required|string']);
    try {
        $response = \Illuminate\Support\Facades\Http::timeout(15)->post(
            config('services.face_api.url', 'http://127.0.0.1:8001') . '/api/face/detect',
            ['image' => $request->image]
        );
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['detected' => false, 'message' => 'Face detection service unavailable'], 503);
    }
});

// Public routes - Check if employee has face data (kiosk)
Route::get('/employees/{id}/face-data', [EmployeeController::class, 'faceData']);

// Public routes - Delete face data for re-registration (kiosk)
Route::delete('/employees/{id}/face-data', [EmployeeController::class, 'deleteFaceData']);

// Public routes - Face registration (kiosk self-registration, only for employees without face data)
Route::post('/employees/{id}/face', function ($id) {
    $employee = \App\Models\Employee::find($id);
    if (!$employee) {
        return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
    }

    $todayCount = \App\Models\EmployeeFaceData::where('employee_id', $id)
        ->whereDate('created_at', now()->toDateString())
        ->count();
    if ($todayCount > 0) {
        return response()->json(['success' => false, 'message' => 'ลงทะเบียนใบหน้าวันนี้แล้ว กรุณากลับมาลงทะเบียนใหม่วันถัดไป'], 400);
    }

    $faceCount = \App\Models\EmployeeFaceData::where('employee_id', $id)->count();
    if ($faceCount >= 5) {
        return response()->json(['success' => false, 'message' => 'Employee already has face data registered.'], 400);
    }
    $request = request();
    $request->merge(['employee_id' => $id]);
    return app(\App\Http\Controllers\Api\FaceController::class)->register($request);
});

// Public routes - Company list
Route::get('/companies', [CompanyController::class, 'index']);

// Public routes - Attendance (kiosk check-in/check-out)
Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);

// Public routes - Remote check (kiosk)
Route::post('/remote/check-active', [RemoteController::class, 'checkActive']);

// Public auth routes
Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/employee/auth/login', [EmployeeAuthController::class, 'login']);

// Protected routes - Authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout']);
    Route::get('/auth/me', [LoginController::class, 'me']);

    // Employee management
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    Route::post('/employees/face', [EmployeeController::class, 'registerFace']);
    Route::delete('/employees/face/{id}', [EmployeeController::class, 'deleteFaceData']);
    Route::post('/employees/{id}/reset-password', [EmployeeController::class, 'resetPassword']);

    // Attendance
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::get('/attendance/history/{employeeId}', [AttendanceController::class, 'history']);
    Route::get('/attendance/monthly', [AttendanceController::class, 'monthly']);

    // Remote assignments
    Route::get('/remote-assignments', [RemoteController::class, 'index']);
    Route::post('/remote-assignments', [RemoteController::class, 'store']);
    Route::put('/remote-assignments/{id}/approve', [RemoteController::class, 'approve']);
    Route::put('/remote-assignments/{id}/reject', [RemoteController::class, 'reject']);
    Route::get('/remote/location-history/{employeeId}', [RemoteController::class, 'getLocationHistory']);
    Route::get('/remote/realtime-locations', [RemoteController::class, 'getRealtimeLocations']);
    Route::put('/remote/location-name/{id}', [RemoteController::class, 'updateLocationName']);

    // Leave management
    Route::get('/leave', [LeaveController::class, 'index']);
    Route::post('/leave', [LeaveController::class, 'store']);
    Route::put('/leave/{id}/approve', [LeaveController::class, 'approve']);
    Route::put('/leave/{id}/reject', [LeaveController::class, 'reject']);
    Route::get('/leave/types', [LeaveController::class, 'types']);

    // Overtime management
    Route::get('/ot', [OtRequestController::class, 'index']);
    Route::post('/ot', [OtRequestController::class, 'store']);
    Route::put('/ot/{id}/manager-approve', [OtRequestController::class, 'managerApprove']);
    Route::put('/ot/{id}/final-approve', [OtRequestController::class, 'finalApprove']);
    Route::put('/ot/{id}/reject', [OtRequestController::class, 'reject']);

    // Reports
    Route::get('/reports/attendance', [ReportController::class, 'attendance']);
    Route::get('/reports/monthly', [ReportController::class, 'monthly']);
    Route::get('/reports/employee/{id}', [ReportController::class, 'employee']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/today', [DashboardController::class, 'today']);

    // Office locations
    Route::get('/office-locations', [OfficeLocationController::class, 'index']);
    Route::post('/office-locations', [OfficeLocationController::class, 'store']);
    Route::put('/office-locations/{id}', [OfficeLocationController::class, 'update']);
    Route::delete('/office-locations/{id}', [OfficeLocationController::class, 'destroy']);
    Route::get('/office-locations/{id}/employees', [OfficeLocationController::class, 'getEmployees']);
    Route::get('/office-locations/{id}/unassigned', [OfficeLocationController::class, 'getUnassignedEmployees']);
    Route::post('/office-locations/{id}/assign', [OfficeLocationController::class, 'assignEmployees']);
    Route::post('/office-locations/{id}/remove', [OfficeLocationController::class, 'removeEmployees']);

    // Face registration
    Route::post('/face/register', [FaceController::class, 'register']);

    // WFH Management
    Route::get('/wfh-records', [WfhController::class, 'index']);
    Route::post('/wfh-records', [WfhController::class, 'store']);
    Route::put('/wfh-records/{id}/approve', [WfhController::class, 'approve']);
    Route::put('/wfh-records/{id}/reject', [WfhController::class, 'reject']);

    // Holiday Management
    Route::get('/holidays', [HolidayController::class, 'index']);
    Route::post('/holidays', [HolidayController::class, 'store']);
    Route::delete('/holidays/{id}', [HolidayController::class, 'destroy']);
    Route::put('/holidays/{id}', [HolidayController::class, 'update']);
    Route::post('/holidays/import-official', [HolidayController::class, 'importOfficial']);

    // Shift Management
    Route::get('/shift-schedules', [ShiftController::class, 'index']);
    Route::post('/shift-schedules', [ShiftController::class, 'store']);
    Route::delete('/shift-schedules/{id}', [ShiftController::class, 'destroy']);

    // Supervisor Routes
    Route::get('/supervisor/leave-approval', [SupervisorController::class, 'leaveApproval']);
    Route::get('/supervisor/ot-approval', [SupervisorController::class, 'otApproval']);
    Route::get('/supervisor/team-calendar', [SupervisorController::class, 'teamCalendar']);

    // Manager Routes
    Route::get('/manager/leave-approval', [ManagerController::class, 'leaveApproval']);
    Route::get('/manager/ot-approval', [ManagerController::class, 'otApproval']);
    Route::get('/manager/team-report', [ManagerController::class, 'teamReport']);

    // Company Settings
    Route::get('/company-settings', [CompanySettingsController::class, 'index']);
    Route::put('/company-settings', [CompanySettingsController::class, 'update']);
    Route::post('/company-settings/logo', [CompanySettingsController::class, 'updateLogo']);
    Route::delete('/company-settings/logo', [CompanySettingsController::class, 'destroyLogo']);

    // System Settings
    Route::get('/system-settings', [SystemSettingsController::class, 'index']);
    Route::put('/system-settings', [SystemSettingsController::class, 'update']);

    // Employee History
    Route::get('/employees/{id}/history', [EmployeeHistoryController::class, 'index']);

    // Employee Requests (พนักงานทั่วไป)
    Route::get('/employee/requests/pending-count', [EmployeeRequestController::class, 'pendingCount']);
    Route::post('/employee/leave-requests', [EmployeeRequestController::class, 'storeLeave']);
    Route::post('/employee/ot-requests', [EmployeeRequestController::class, 'storeOt']);
    Route::post('/employee/wfh-requests', [EmployeeRequestController::class, 'storeWfh']);
    Route::post('/employee/change-password', [EmployeeRequestController::class, 'changePassword']);

    // Shift Assignments (จัดการกะรายเดือน)
    Route::get('/shift-assignments', [\App\Http\Controllers\Api\ShiftAssignmentController::class, 'index']);
    Route::post('/shift-assignments', [\App\Http\Controllers\Api\ShiftAssignmentController::class, 'assign']);
    Route::delete('/shift-assignments', [\App\Http\Controllers\Api\ShiftAssignmentController::class, 'remove']);

    // Mandatory OT Assignments (มอบหมาย OT บังคับ)
    Route::get('/mandatory-ot', [\App\Http\Controllers\Api\MandatoryOtController::class, 'index']);
    Route::post('/mandatory-ot', [\App\Http\Controllers\Api\MandatoryOtController::class, 'store']);
    Route::delete('/mandatory-ot/{id}', [\App\Http\Controllers\Api\MandatoryOtController::class, 'destroy']);

    // Attendance Adjustment (ปรับแก้สถานะเข้างาน)
    Route::get('/attendance-adjustment', [AttendanceAdjustmentController::class, 'index']);
    Route::put('/attendance-adjustment/{id}/adjust', [AttendanceAdjustmentController::class, 'adjust']);
    Route::get('/attendance-adjustment/forced-leaves', [AttendanceAdjustmentController::class, 'forcedLeaves']);
    Route::put('/attendance-adjustment/forced-leaves/{id}/approve', [AttendanceAdjustmentController::class, 'approveForcedLeave']);
    Route::put('/attendance-adjustment/forced-leaves/{id}/reject', [AttendanceAdjustmentController::class, 'rejectForcedLeave']);

    // Attendance Verification (ยืนยันข้อมูลเข้างาน)
    Route::get('/attendance-verification', [AttendanceVerificationController::class, 'index']);
    Route::put('/attendance-verification/{id}/verify', [AttendanceVerificationController::class, 'verify']);
    Route::put('/attendance-verification/{id}/unverify', [AttendanceVerificationController::class, 'unverify']);
    Route::post('/attendance-verification/verify-all', [AttendanceVerificationController::class, 'verifyAll']);

    // Auto OT (OT อัตโนมัติ - มาเร็ว/กลับช้า ≥ 1 ชม.)
    Route::get('/auto-ot', [AutoOtController::class, 'index']);
    Route::put('/auto-ot/{id}/approve', [AutoOtController::class, 'approve']);
    Route::put('/auto-ot/{id}/reject', [AutoOtController::class, 'reject']);
    Route::post('/auto-ot/approve-all', [AutoOtController::class, 'approveAll']);
});

// Permission routes
use App\Http\Controllers\Api\PermissionController;

Route::prefix('wfh')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [WfhRequestController::class, 'index']);
    Route::get('/available-saturdays', [WfhRequestController::class, 'availableSaturdays']);
    Route::get('/my-requests', [WfhRequestController::class, 'myRequests']);
    Route::get('/team-requests', [WfhRequestController::class, 'teamRequests']);
    Route::post('/', [WfhRequestController::class, 'store']);
    Route::put('/{id}/approve', [WfhRequestController::class, 'approve']);
    Route::put('/{id}/reject', [WfhRequestController::class, 'reject']);
    Route::delete('/{id}', [WfhRequestController::class, 'cancel']);
});

Route::middleware('auth:sanctum')->prefix('api/permissions')->group(function () {
    Route::get('/employees', [PermissionController::class, 'index']);
    Route::put('/employees/{id}/role', [PermissionController::class, 'updateRole']);
    Route::put('/employees/{id}/status', [PermissionController::class, 'updateStatus']);
    Route::post('/employees/{id}/reset-password', [PermissionController::class, 'resetPassword']);
    Route::post('/change-password', [PermissionController::class, 'changePassword']);
});

// OT Summary
use App\Http\Controllers\Api\OtSummaryController;
Route::middleware('auth:sanctum')->get('/ot-summary', [OtSummaryController::class, 'index']);

Route::get('/employee/{employee}/office-location', function (\App\Models\Employee $employee) {
    \Log::info('office-location route hit', ['employee_id' => $employee->id, 'employee_name' => $employee->name]);
    $office = $employee->getAssignedOfficeLocation();
    \Log::info('office-location result', ['office' => $office?->name ?? 'null']);
    if (!$office) {
        return response()->json(['success' => true, 'data' => null]);
    }
    return response()->json([
        'success' => true,
        'data' => [
            'id' => $office->id,
            'name' => $office->name,
            'latitude' => $office->latitude,
            'longitude' => $office->longitude,
            'radius_meters' => $office->radius_meters,
        ]
    ]);
});


// Shift Swap Routes
Route::prefix('shift-swaps')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ShiftSwapController::class, 'index']);
    Route::get('/my-requests', [ShiftSwapController::class, 'myRequests']);
    Route::get('/team-swaps', [ShiftSwapController::class, 'teamSwaps']);
    Route::post('/', [ShiftSwapController::class, 'store']);
    Route::put('/{id}/approve', [ShiftSwapController::class, 'approve']);
    Route::put('/{id}/reject', [ShiftSwapController::class, 'reject']);
});

// Telegram Routes
Route::post('/telegram/test', [TelegramController::class, 'test']);
Route::get('/telegram/bot-info', fn() => response()->json(['data' => App\Services\TelegramService::getBotInfo()]));
Route::get('/telegram/groups', [TelegramController::class, 'groups']);
Route::post('/telegram/groups', [TelegramController::class, 'storeGroup']);
Route::put('/telegram/groups/{id}', [TelegramController::class, 'updateGroup']);
Route::delete('/telegram/groups/{id}', [TelegramController::class, 'deleteGroup']);
Route::post('/telegram/test-group', [TelegramController::class, 'testGroup']);
Route::get('/employee-stats', [EmployeeStatsController::class, 'index']);
// Leave Request Routes
Route::prefix('leave')->middleware('auth:sanctum')->group(function () {
    Route::get('/balance', [LeaveRequestController::class, 'balance']);
    Route::get('/my-requests', [LeaveRequestController::class, 'myRequests']);
    Route::get('/team-leaves', [LeaveRequestController::class, 'teamLeaves']);
    Route::post('/', [LeaveRequestController::class, 'store']);
    Route::put('/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::put('/{id}/reject', [LeaveRequestController::class, 'reject']);
});
