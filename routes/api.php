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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

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
});
