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
use App\Http\Controllers\Api\EmployeeHolidayController;
use App\Http\Controllers\Api\SupervisorLeaveCalendarController;
use App\Http\Controllers\Api\EmployeeDashboardController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\CompanySettingsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\EmployeeHistoryController;
use App\Http\Controllers\Api\EmployeeRequestController;
use App\Http\Controllers\Api\AttendanceAdjustmentController;
use App\Http\Controllers\Api\AttendanceVerificationController;
use App\Http\Controllers\Api\AutoOtController;
use App\Http\Controllers\Api\TelegramController;
use App\Http\Controllers\Api\EmployeeProfileController;
use App\Http\Controllers\Api\EmployeeScheduleController;
use App\Http\Controllers\Api\EmployeeWarningController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\OtSummaryController;
use App\Http\Controllers\Api\EmployeeStatsController;
use App\Http\Controllers\Api\ShiftSwapController;
use App\Http\Controllers\Api\WfhRequestController;
use App\Http\Controllers\Api\ShiftAssignmentController;
use App\Http\Controllers\Api\MandatoryOtController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\ManualEntryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public routes (no auth)
| Auth routes (any authenticated user)
| Employee self-service routes (employee role)
| Admin routes (admin + super_admin)
| Super admin routes (super_admin only)
|
*/

// ============================================================
// PUBLIC ROUTES (no authentication required)
// ============================================================

Route::get('/time', function () {
    $now = now('Asia/Bangkok');
    return response()->json([
        'time' => $now->format('Y-m-d\TH:i:s.v') . '+07:00',
        'timezone' => 'Asia/Bangkok',
    ]);
});

Route::post('/employee/auth/search', [EmployeeAuthController::class, 'search']);
Route::post('/employee/auth/verify', [EmployeeAuthController::class, 'verify']);
Route::post('/face/verify', [FaceController::class, 'verify']);

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

Route::get('/employees/{id}/face-data', [EmployeeController::class, 'faceData']);
Route::delete('/employees/{id}/face-data', [EmployeeController::class, 'deleteFaceData']);

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

Route::get('/companies', [CompanyController::class, 'index']);
Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
Route::post('/remote/check-active', [RemoteController::class, 'checkActive']);

Route::get('/employee/{employee}/office-location', function (\App\Models\Employee $employee) {
    $office = $employee->getAssignedOfficeLocation();
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

// ============================================================
// AUTH ROUTES (login/logout)
// ============================================================

Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/employee/auth/login', [EmployeeAuthController::class, 'login']);

// ============================================================
// AUTHENTICATED ROUTES (any logged-in user)
// ============================================================

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [LoginController::class, 'logout']);
    Route::get('/auth/me', [LoginController::class, 'me']);

    // ---- Employee Self-Service (any role) ----
    Route::get('/employee/profile', [EmployeeProfileController::class, 'show']);
    Route::put('/employee/profile', [EmployeeProfileController::class, 'update']);
    Route::post('/employee/profile/photo', [EmployeeProfileController::class, 'uploadPhoto']);
    Route::get('/employee/schedule', [EmployeeScheduleController::class, 'index']);
    Route::get('/employee/warnings', [EmployeeWarningController::class, 'index']);
    Route::get('/employee/holidays', [EmployeeHolidayController::class, 'index']);
    Route::get('/employee/dashboard', [EmployeeDashboardController::class, 'index']);
    Route::get('/employee/requests/pending-count', [EmployeeRequestController::class, 'pendingCount']);
    Route::get('/employee/attendance/history', [EmployeeHistoryController::class, 'myHistory']);
    Route::post('/employee/leave-requests', [EmployeeRequestController::class, 'storeLeave']);
    Route::post('/employee/ot-requests', [EmployeeRequestController::class, 'storeOt']);
    Route::post('/employee/wfh-requests', [EmployeeRequestController::class, 'storeWfh']);
    Route::post('/employee/change-password', [EmployeeRequestController::class, 'changePassword']);
    Route::get('/announcements', [AnnouncementController::class, 'index']);

    // Employee Payslip (self)
    Route::get('/employee/payslip', [PayslipController::class, 'myPayslip']);
    Route::get('/employee/payslip/history', [PayslipController::class, 'myHistory']);

    // Shift Swap (employee self)
    Route::get('/shift-swaps/my-requests', [ShiftSwapController::class, 'myRequests']);
    Route::get('/shift-swaps/available-employees', [ShiftSwapController::class, 'availableEmployees']);
    Route::post('/shift-swaps', [ShiftSwapController::class, 'store']);

    // Attendance (any authenticated user can read)
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::get('/attendance/history/{employeeId}', [AttendanceController::class, 'history']);

    // Supervisor/Manager approvals (position-based)
    Route::middleware('position:team_lead,sub_division_manager,division_manager,assistant_md,md')->group(function () {
        Route::get('/supervisor/leave-approval', [SupervisorController::class, 'leaveApproval']);
        Route::get('/supervisor/ot-approval', [SupervisorController::class, 'otApproval']);
        Route::get('/supervisor/team-calendar', [SupervisorController::class, 'teamCalendar']);
        Route::get('/supervisor/leave-calendar', [SupervisorLeaveCalendarController::class, 'index']);
        Route::get('/manager/leave-approval', [ManagerController::class, 'leaveApproval']);
        Route::get('/manager/ot-approval', [ManagerController::class, 'otApproval']);
        Route::get('/manager/team-report', [ManagerController::class, 'teamReport']);
    });

    // Leave requests (self service + approvals)
    Route::get('/leave', [LeaveController::class, 'index']);
    Route::post('/leave', [LeaveController::class, 'store']);
    Route::put('/leave/{id}/approve', [LeaveController::class, 'approve']);
    Route::put('/leave/{id}/reject', [LeaveController::class, 'reject']);
    Route::get('/leave/types', [LeaveController::class, 'types']);

    // OT requests (self service + approvals)
    Route::get('/ot', [OtRequestController::class, 'index']);
    Route::post('/ot', [OtRequestController::class, 'store']);
    Route::put('/ot/{id}/manager-approve', [OtRequestController::class, 'managerApprove']);
    Route::put('/ot/{id}/final-approve', [OtRequestController::class, 'finalApprove']);
    Route::put('/ot/{id}/reject', [OtRequestController::class, 'reject']);

    // WFH (self service + approvals)
    Route::get('/wfh-records', [WfhController::class, 'index']);
    Route::post('/wfh-records', [WfhController::class, 'store']);
    Route::put('/wfh-records/{id}/approve', [WfhController::class, 'approve']);
    Route::put('/wfh-records/{id}/reject', [WfhController::class, 'reject']);

    // Shift Swap approvals
    Route::get('/shift-swaps', [ShiftSwapController::class, 'index']);
    Route::get('/shift-swaps/team-swaps', [ShiftSwapController::class, 'teamSwaps']);
    Route::put('/shift-swaps/{id}/approve', [ShiftSwapController::class, 'approve']);
    Route::put('/shift-swaps/{id}/reject', [ShiftSwapController::class, 'reject']);

    // WFH request routes
    Route::prefix('wfh')->group(function () {
        Route::get('/', [WfhRequestController::class, 'index']);
        Route::get('/available-saturdays', [WfhRequestController::class, 'availableSaturdays']);
        Route::get('/my-requests', [WfhRequestController::class, 'myRequests']);
        Route::get('/team-requests', [WfhRequestController::class, 'teamRequests']);
        Route::post('/', [WfhRequestController::class, 'store']);
        Route::put('/{id}/approve', [WfhRequestController::class, 'approve']);
        Route::put('/{id}/reject', [WfhRequestController::class, 'reject']);
        Route::delete('/{id}', [WfhRequestController::class, 'cancel']);
    });

    // Leave request routes
    Route::prefix('leave')->group(function () {
        Route::get('/balance', [LeaveRequestController::class, 'balance']);
        Route::get('/my-requests', [LeaveRequestController::class, 'myRequests']);
        Route::get('/team-leaves', [LeaveRequestController::class, 'teamLeaves']);
        Route::post('/', [LeaveRequestController::class, 'store']);
        Route::put('/{id}/approve', [LeaveRequestController::class, 'approve']);
        Route::put('/{id}/reject', [LeaveRequestController::class, 'reject']);
    });

    // Change password (self)
    Route::post('/api/permissions/change-password', [PermissionController::class, 'changePassword']);
});

// ============================================================
// ADMIN ROUTES (admin + super_admin only)
// ============================================================

Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {

    // Dashboard (HR)
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/today', [DashboardController::class, 'today']);

    // Employee list (read)
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);

    // Reports
    Route::get('/reports/attendance', [ReportController::class, 'attendance']);
    Route::get('/reports/monthly', [ReportController::class, 'monthly']);
    Route::get('/reports/employee/{id}', [ReportController::class, 'employee']);
    Route::get('/reports/export-attendance', [ReportController::class, 'exportAttendance']);
    Route::get('/reports/export-attendance-pdf', [ReportController::class, 'exportAttendancePdf']);
    Route::get('/reports/leave', [ReportController::class, 'leave']);
    Route::get('/reports/export-leave-pdf', [ReportController::class, 'exportLeavePdf']);
    Route::get('/reports/ot', [ReportController::class, 'ot']);
    Route::get('/reports/export-ot-pdf', [ReportController::class, 'exportOtPdf']);

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/summary', [AuditLogController::class, 'summary']);
    Route::get('/audit-logs/{type}/{id}', [AuditLogController::class, 'forModel']);

    // Attendance monthly
    Route::get('/attendance/monthly', [AttendanceController::class, 'monthly']);

    // Employee History (HR view)
    Route::get('/employees/{id}/history', [EmployeeHistoryController::class, 'index']);

    // Attendance Adjustment
    Route::get('/attendance-adjustment', [AttendanceAdjustmentController::class, 'index']);
    Route::put('/attendance-adjustment/{id}/adjust', [AttendanceAdjustmentController::class, 'adjust']);
    Route::get('/attendance-adjustment/forced-leaves', [AttendanceAdjustmentController::class, 'forcedLeaves']);
    Route::put('/attendance-adjustment/forced-leaves/{id}/approve', [AttendanceAdjustmentController::class, 'approveForcedLeave']);
    Route::put('/attendance-adjustment/forced-leaves/{id}/reject', [AttendanceAdjustmentController::class, 'rejectForcedLeave']);

    // Attendance Verification
    Route::get('/attendance-verification', [AttendanceVerificationController::class, 'index']);
    Route::put('/attendance-verification/{id}/verify', [AttendanceVerificationController::class, 'verify']);
    Route::put('/attendance-verification/{id}/unverify', [AttendanceVerificationController::class, 'unverify']);
    Route::post('/attendance-verification/verify-all', [AttendanceVerificationController::class, 'verifyAll']);

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

    // Shift Assignments
    Route::get('/shift-assignments', [ShiftAssignmentController::class, 'index']);
    Route::post('/shift-assignments', [ShiftAssignmentController::class, 'assign']);
    Route::delete('/shift-assignments', [ShiftAssignmentController::class, 'remove']);

    // Mandatory OT
    Route::get('/mandatory-ot', [MandatoryOtController::class, 'index']);
    Route::post('/mandatory-ot', [MandatoryOtController::class, 'store']);
    Route::delete('/mandatory-ot/{id}', [MandatoryOtController::class, 'destroy']);

    // Auto OT
    Route::get('/auto-ot', [AutoOtController::class, 'index']);
    Route::put('/auto-ot/{id}/approve', [AutoOtController::class, 'approve']);
    Route::put('/auto-ot/{id}/reject', [AutoOtController::class, 'reject']);
    Route::post('/auto-ot/approve-all', [AutoOtController::class, 'approveAll']);

    // OT Summary
    Route::get('/ot-summary', [OtSummaryController::class, 'index']);

    // Remote assignments
    Route::get('/remote-assignments', [RemoteController::class, 'index']);
    Route::post('/remote-assignments', [RemoteController::class, 'store']);
    Route::put('/remote-assignments/{id}/approve', [RemoteController::class, 'approve']);
    Route::put('/remote-assignments/{id}/reject', [RemoteController::class, 'reject']);
    Route::get('/remote/location-history/{employeeId}', [RemoteController::class, 'getLocationHistory']);
    Route::get('/remote/realtime-locations', [RemoteController::class, 'getRealtimeLocations']);
    Route::put('/remote/location-name/{id}', [RemoteController::class, 'updateLocationName']);

    // HR Payslip Management
    Route::get('/hr/payslips', [PayslipController::class, 'hrList']);
    Route::get('/hr/payslips/{empId}', [PayslipController::class, 'hrGet']);
    Route::post('/hr/payslips/{empId}', [PayslipController::class, 'hrSave']);

    // Manual Entry (บันทึกข้อมูลด้วยมือ)
    Route::get('/manual/attendance', [ManualEntryController::class, 'attendanceIndex']);
    Route::post('/manual/attendance', [ManualEntryController::class, 'attendanceStore']);
    Route::put('/manual/attendance/{id}', [ManualEntryController::class, 'attendanceUpdate']);
    Route::delete('/manual/attendance/{id}', [ManualEntryController::class, 'attendanceDestroy']);
    Route::get('/manual/ot', [ManualEntryController::class, 'otIndex']);
    Route::post('/manual/ot', [ManualEntryController::class, 'otStore']);
    Route::put('/manual/ot/{id}', [ManualEntryController::class, 'otUpdate']);
    Route::delete('/manual/ot/{id}', [ManualEntryController::class, 'otDestroy']);
    Route::get('/manual/shift', [ManualEntryController::class, 'shiftIndex']);
    Route::post('/manual/shift', [ManualEntryController::class, 'shiftStore']);
    Route::put('/manual/shift/{id}', [ManualEntryController::class, 'shiftUpdate']);
    Route::delete('/manual/shift/{id}', [ManualEntryController::class, 'shiftDestroy']);
    Route::get('/manual/leave', [ManualEntryController::class, 'leaveIndex']);
    Route::post('/manual/leave', [ManualEntryController::class, 'leaveStore']);
    Route::put('/manual/leave/{id}', [ManualEntryController::class, 'leaveUpdate']);
    Route::delete('/manual/leave/{id}', [ManualEntryController::class, 'leaveDestroy']);
    Route::post('/manual/import-shift', [ManualEntryController::class, 'importShiftSchedule']);

    // Face registration
    Route::post('/face/register', [FaceController::class, 'register']);

    // Office locations
    Route::get('/office-locations', [OfficeLocationController::class, 'index']);
    Route::post('/office-locations', [OfficeLocationController::class, 'store']);
    Route::put('/office-locations/{id}', [OfficeLocationController::class, 'update']);
    Route::delete('/office-locations/{id}', [OfficeLocationController::class, 'destroy']);
    Route::get('/office-locations/{id}/employees', [OfficeLocationController::class, 'getEmployees']);
    Route::get('/office-locations/{id}/unassigned', [OfficeLocationController::class, 'getUnassignedEmployees']);
    Route::post('/office-locations/{id}/assign', [OfficeLocationController::class, 'assignEmployees']);
    Route::post('/office-locations/{id}/remove', [OfficeLocationController::class, 'removeEmployees']);

    // Employee stats
    Route::get('/employee-stats', [EmployeeStatsController::class, 'index']);
});

// ============================================================
// SUPER ADMIN ROUTES (super_admin only)
// ============================================================

Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {

    // Employee management (CRUD)
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    Route::post('/employees/face', [EmployeeController::class, 'registerFace']);
    Route::delete('/employees/face/{id}', [EmployeeController::class, 'deleteFaceData']);
    Route::post('/employees/{id}/reset-password', [EmployeeController::class, 'resetPassword']);

    // Permission management
    Route::get('/api/permissions/employees', [PermissionController::class, 'index']);
    Route::put('/api/permissions/employees/{id}/role', [PermissionController::class, 'updateRole']);
    Route::put('/api/permissions/employees/{id}/status', [PermissionController::class, 'updateStatus']);
    Route::post('/api/permissions/employees/{id}/reset-password', [PermissionController::class, 'resetPassword']);

    // Company settings
    Route::get('/company-settings', [CompanySettingsController::class, 'index']);
    Route::put('/company-settings', [CompanySettingsController::class, 'update']);
    Route::post('/company-settings/logo', [CompanySettingsController::class, 'updateLogo']);
    Route::delete('/company-settings/logo', [CompanySettingsController::class, 'destroyLogo']);

    // System settings
    Route::get('/system-settings', [SystemSettingsController::class, 'index']);
    Route::put('/system-settings', [SystemSettingsController::class, 'update']);

    // Announcements (manage)
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

    // Telegram settings
    Route::post('/telegram/test', [TelegramController::class, 'test']);
    Route::get('/telegram/bot-info', fn() => response()->json(['data' => App\Services\TelegramService::getBotInfo()]));
    Route::get('/telegram/groups', [TelegramController::class, 'groups']);
    Route::post('/telegram/groups', [TelegramController::class, 'storeGroup']);
    Route::put('/telegram/groups/{id}', [TelegramController::class, 'updateGroup']);
    Route::delete('/telegram/groups/{id}', [TelegramController::class, 'deleteGroup']);
    Route::post('/telegram/test-group', [TelegramController::class, 'testGroup']);
});
