<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $companies = Company::all();

            return response()->json([
                'success' => true,
                'data' => $companies,
                'message' => 'Companies retrieved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve companies: ' . $e->getMessage(),
            ], 500);
        }
    }
}
