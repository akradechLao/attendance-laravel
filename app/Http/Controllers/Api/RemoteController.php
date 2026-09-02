<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RemoteAssignment;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\OfficeLocation;
use App\Services\LocationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteController extends Controller
{
    protected LocationService $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = RemoteAssignment::with([
                'employee:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id',
            ]);

            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('emp_id')) {
                $query->where('emp_id', $request->emp_id);
            }

            $assignments = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $assignments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve remote assignments: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'emp_id' => 'required|exists:employees,id',
                'company_id' => 'required|exists:companies,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'destination' => 'nullable|string|max:255',
                'reason' => 'nullable|string',
            ]);

            $overlap = RemoteAssignment::where('emp_id', $request->emp_id)
                ->where('status', '!=', 'rejected')
                ->where(function ($q) use ($request) {
                    $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                      ->orWhere(function ($q2) use ($request) {
                          $q2->where('start_date', '<=', $request->start_date)
                             ->where('end_date', '>=', $request->end_date);
                      });
                })
                ->first();

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'พนักงานคนนี้มีการมอบหมายในช่วงวันที่นี้อยู่แล้ว (' . $overlap->start_date . ' ถึง ' . $overlap->end_date . ')',
                ], 422);
            }

            $assignment = RemoteAssignment::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $assignment->load(['employee:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id']),
                'message' => 'Remote assignment created successfully.',
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
                'message' => 'Failed to create remote assignment: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $assignment = RemoteAssignment::findOrFail($id);
            
            if ($assignment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This assignment has already been processed.',
                ], 400);
            }

            $assignment->update([
                'status' => 'approved',
                'approved_by' => $request->get('approved_by'),
                'approved_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $assignment->load(['employee:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id']),
                'message' => 'Remote assignment approved.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, $id): JsonResponse
    {
        try {
            $assignment = RemoteAssignment::findOrFail($id);
            
            $assignment->update([
                'status' => 'rejected',
                'approved_by' => $request->get('approved_by'),
                'approved_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $assignment,
                'message' => 'Remote assignment rejected.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkActive(Request $request): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($request->employee_id);
            $hasActive = $employee->hasActiveRemoteAssignment();

            return response()->json([
                'success' => true,
                'data' => [
                    'has_remote_assignment' => $hasActive,
                    'can_remote_scan' => $hasActive,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getLocationHistory(Request $request, $employeeId): JsonResponse
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());

            $logs = AttendanceLog::where('emp_id', $employeeId)
                ->whereDate('date', $date)
                ->orderBy('check_in', 'asc')
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'time' => $log->check_in,
                        'scan_type' => $log->scan_type,
                        'latitude' => $log->remote_latitude,
                        'longitude' => $log->remote_longitude,
                        'location_name' => $log->getLocationDisplayName(),
                        'custom_name' => $log->remote_custom_name,
                        'accuracy' => $log->remote_accuracy,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRealtimeLocations(Request $request): JsonResponse
    {
        try {
            $companyId = $request->get('company_id');
            $today = Carbon::today();

            $query = RemoteAssignment::where('status', 'approved')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->with(['employee:id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix']);

            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            $activeRemotes = $query->get();

            $locations = [];
            foreach ($activeRemotes as $assignment) {
                if (!$assignment->employee) continue;

                $latestScan = AttendanceLog::where('emp_id', $assignment->emp_id)
                    ->whereDate('date', $today)
                    ->where('scan_type', 'remote_scan')
                    ->latest()
                    ->first();

                $locations[] = [
                    'employee_id' => $assignment->emp_id,
                    'employee_name' => $assignment->employee->name,
                    'employee_code' => $assignment->employee->employee_code,
                    'latitude' => $latestScan?->remote_latitude,
                    'longitude' => $latestScan?->remote_longitude,
                    'location_name' => $latestScan ? $latestScan->getLocationDisplayName() : 'ยังไม่ได้สแกนวันนี้',
                    'last_scan_time' => $latestScan?->created_at,
                    'assignment' => [
                        'id' => $assignment->id,
                        'destination' => $assignment->destination,
                            'start_date' => $assignment->start_date,
                            'end_date' => $assignment->end_date,
                        ],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $locations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateLocationName(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'custom_name' => 'required|string|max:255',
            ]);

            $log = AttendanceLog::findOrFail($id);
            $log->update([
                'remote_custom_name' => $request->custom_name,
            ]);

            return response()->json([
                'success' => true,
                'data' => $log,
                'message' => 'Location name updated.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
