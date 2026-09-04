<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanySettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $company = Company::findOrFail($companyId);

        $settings = CompanySetting::where('company_id', $companyId)
            ->pluck('value', 'key')
            ->toArray();

        return response()->json([
            'data' => $settings,
            'company' => $company,
        ]);
    }

    public function indexAll(Request $request): JsonResponse
    {
        $companies = Company::with('settings')->get();

        return response()->json([
            'data' => $companies,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $company = Company::findOrFail($companyId);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'website' => 'nullable|string',
        ]);

        $company->update($validated);

        $settingFields = $request->only([
            'work_start_time', 'work_end_time', 'late_threshold',
            'location_radius', 'enable_face_recognition', 'enable_remote_scan',
        ]);

        foreach ($settingFields as $key => $value) {
            CompanySetting::setValue($companyId, $key, $value);
        }

        return response()->json([
            'message' => 'บันทึกสำเร็จ',
            'company' => $company,
        ]);
    }

    public function updateLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
        ]);

        $companyId = $this->resolveCompanyId($request);
        $company = Company::findOrFail($companyId);

        if ($company->logo) {
            Storage::disk('public')->delete('companies/' . $company->logo);
        }

        $file = $request->file('logo');
        $filename = 'company_' . $companyId . '_' . time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('companies', $filename, 'public');

        $company->update(['logo' => $filename]);

        return response()->json([
            'message' => 'อัพโหลดโลโก้สำเร็จ',
            'logo_url' => $company->logo_url,
        ]);
    }

    public function destroyLogo(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $company = Company::findOrFail($companyId);

        if ($company->logo) {
            Storage::disk('public')->delete('companies/' . $company->logo);
            $company->update(['logo' => null]);
        }

        return response()->json(['message' => 'ลบโลโก้สำเร็จ']);
    }

    protected function resolveCompanyId(Request $request): int
    {
        $user = $request->user();

        // Super admin can manage any company
        if ($user && $user->role === 'super_admin' && $request->input('company_id')) {
            return (int) $request->input('company_id');
        }

        return parent::resolveCompanyId($request);
    }
}
