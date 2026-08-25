<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    // Employee: view my payslip
    public function myPayslip(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $payslip = Payslip::where('emp_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (!$payslip) {
            return response()->json([
                'success' => true,
                'data' => [
                    'exists' => false,
                    'month' => $month,
                    'year' => $year,
                    'base_salary' => 0,
                    'ot_pay' => 0,
                    'bonus' => 0,
                    'transport_allowance' => 0,
                    'meal_allowance' => 0,
                    'other_allowance' => 0,
                    'total_allowances' => 0,
                    'deduction_late' => 0,
                    'deduction_absent' => 0,
                    'deduction_social_security' => 0,
                    'deduction_tax' => 0,
                    'deduction_other' => 0,
                    'total_deductions' => 0,
                    'net_salary' => 0,
                    'note' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'exists' => true,
                'month' => $payslip->month,
                'year' => $payslip->year,
                'base_salary' => $payslip->base_salary,
                'ot_pay' => $payslip->ot_pay,
                'bonus' => $payslip->bonus,
                'transport_allowance' => $payslip->transport_allowance,
                'meal_allowance' => $payslip->meal_allowance,
                'other_allowance' => $payslip->other_allowance,
                'total_allowances' => $payslip->total_allowances,
                'deduction_late' => $payslip->deduction_late,
                'deduction_absent' => $payslip->deduction_absent,
                'deduction_social_security' => $payslip->deduction_social_security,
                'deduction_tax' => $payslip->deduction_tax,
                'deduction_other' => $payslip->deduction_other,
                'total_deductions' => $payslip->total_deductions,
                'net_salary' => $payslip->net_salary,
                'note' => $payslip->note,
            ],
        ]);
    }

    // Employee: payslip history
    public function myHistory(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $year = $request->get('year', now()->year);

        $payslips = Payslip::where('emp_id', $employee->id)
            ->where('year', $year)
            ->orderBy('month', 'desc')
            ->get()
            ->map(fn($p) => [
                'month' => $p->month,
                'year' => $p->year,
                'base_salary' => $p->base_salary,
                'net_salary' => $p->net_salary,
                'total_allowances' => $p->total_allowances,
                'total_deductions' => $p->total_deductions,
            ]);

        return response()->json(['success' => true, 'data' => $payslips]);
    }

    // HR: list employees for payslip entry
    public function hrList(Request $request): JsonResponse
    {
        $company = $request->user()->employee?->company_id;
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $employees = Employee::where('company_id', $company)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($emp) use ($month, $year) {
                $payslip = Payslip::where('emp_id', $emp->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->first();

                return [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'employee_code' => $emp->employee_code,
                    'department' => $emp->department,
                    'has_payslip' => (bool) $payslip,
                    'net_salary' => $payslip ? $payslip->net_salary : 0,
                ];
            });

        return response()->json(['success' => true, 'data' => $employees]);
    }

    // HR: get payslip for editing
    public function hrGet(Request $request, int $empId): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $payslip = Payslip::where('emp_id', $empId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (!$payslip) {
            $employee = Employee::find($empId);
            return response()->json([
                'success' => true,
                'data' => [
                    'exists' => false,
                    'employee' => $employee ? ['id' => $employee->id, 'name' => $employee->name] : null,
                    'month' => $month,
                    'year' => $year,
                    'base_salary' => 0,
                    'ot_pay' => 0,
                    'bonus' => 0,
                    'transport_allowance' => 0,
                    'meal_allowance' => 0,
                    'other_allowance' => 0,
                    'deduction_late' => 0,
                    'deduction_absent' => 0,
                    'deduction_social_security' => 0,
                    'deduction_tax' => 0,
                    'deduction_other' => 0,
                    'note' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'exists' => true,
                'id' => $payslip->id,
                'employee' => ['id' => $payslip->employee->id, 'name' => $payslip->employee->name],
                'month' => $payslip->month,
                'year' => $payslip->year,
                'base_salary' => $payslip->base_salary,
                'ot_pay' => $payslip->ot_pay,
                'bonus' => $payslip->bonus,
                'transport_allowance' => $payslip->transport_allowance,
                'meal_allowance' => $payslip->meal_allowance,
                'other_allowance' => $payslip->other_allowance,
                'deduction_late' => $payslip->deduction_late,
                'deduction_absent' => $payslip->deduction_absent,
                'deduction_social_security' => $payslip->deduction_social_security,
                'deduction_tax' => $payslip->deduction_tax,
                'deduction_other' => $payslip->deduction_other,
                'note' => $payslip->note,
            ],
        ]);
    }

    // HR: save payslip
    public function hrSave(Request $request, int $empId): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer',
            'base_salary' => 'required|numeric|min:0',
            'ot_pay' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'meal_allowance' => 'nullable|numeric|min:0',
            'other_allowance' => 'nullable|numeric|min:0',
            'deduction_late' => 'nullable|numeric|min:0',
            'deduction_absent' => 'nullable|numeric|min:0',
            'deduction_social_security' => 'nullable|numeric|min:0',
            'deduction_tax' => 'nullable|numeric|min:0',
            'deduction_other' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $employee = Employee::find($empId);
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $validated['emp_id'] = $empId;
        $validated['company_id'] = $employee->company_id;

        // Default 0 for nullable numeric fields
        $defaults = [
            'ot_pay' => 0, 'bonus' => 0, 'transport_allowance' => 0,
            'meal_allowance' => 0, 'other_allowance' => 0,
            'deduction_late' => 0, 'deduction_absent' => 0,
            'deduction_social_security' => 0, 'deduction_tax' => 0, 'deduction_other' => 0,
        ];
        foreach ($defaults as $field => $default) {
            if (!isset($validated[$field]) || $validated[$field] === '') {
                $validated[$field] = $default;
            }
        }

        $payslip = Payslip::updateOrCreate(
            ['emp_id' => $empId, 'month' => $validated['month'], 'year' => $validated['year']],
            $validated
        );

        $action = $payslip->wasRecentlyCreated ? 'create' : 'update';
        AuditLogService::log(
            $action,
            $payslip,
            $payslip->wasRecentlyCreated ? null : $payslip->getChanges(),
            $validated,
            'บันทึกสลิปเงินเดือน ' . $employee->name . ' เดือน ' . $validated['month'] . '/' . $validated['year'],
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $payslip,
            'message' => 'บันทึกสลิปเงินเดือนสำเร็จ',
        ]);
    }
}
