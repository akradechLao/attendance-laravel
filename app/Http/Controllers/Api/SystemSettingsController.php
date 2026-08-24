<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $settings = \App\Models\CompanySetting::where('company_id', $companyId)
            ->pluck('value', 'key')
            ->toArray();

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        foreach ($request->all() as $key => $value) {
            \App\Models\CompanySetting::setValue($companyId, $key, $value);
        }

        return response()->json(['message' => 'บันทึกสำเร็จ']);
    }
}
