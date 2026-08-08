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
        $companyId = $request->user()->company_id ?? 1;
        $company = Company::findOrFail($companyId);

        $settings = CompanySetting::where('company_id', $companyId)
            ->pluck('value', 'key')
            ->toArray();

        return response()->json([
            'data' => $settings,
            'company' => $company,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        foreach ($request->except('logo') as $key => $value) {
            CompanySetting::setValue($companyId, $key, $value);
        }

        return response()->json(['message' => 'บันทึกสำเร็จ']);
    }

    public function updateLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
        ]);

        $companyId = $request->user()->company_id ?? 1;
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
        $companyId = $request->user()->company_id ?? 1;
        $company = Company::findOrFail($companyId);

        if ($company->logo) {
            Storage::disk('public')->delete('companies/' . $company->logo);
            $company->update(['logo' => null]);
        }

        return response()->json(['message' => 'ลบโลโก้สำเร็จ']);
    }
}
