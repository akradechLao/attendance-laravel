<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfficeLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeLocationController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $locations = OfficeLocation::all();

            return response()->json([
                'success' => true,
                'data' => $locations,
                'message' => 'Office locations retrieved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve office locations: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|exists:companies,id',
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'required|numeric|min:10',
                'work_start_time' => 'required|date_format:H:i',
                'work_end_time' => 'required|date_format:H:i|after:work_start_time',
            ]);

            $location = OfficeLocation::create($validated);

            return response()->json([
                'success' => true,
                'data' => $location,
                'message' => 'Office location created successfully.',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to create office location: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($id);

            $validated = $request->validate([
                'company_id' => 'sometimes|exists:companies,id',
                'name' => 'sometimes|string|max:255',
                'address' => 'nullable|string|max:500',
                'latitude' => 'sometimes|numeric|between:-90,90',
                'longitude' => 'sometimes|numeric|between:-180,180',
                'radius' => 'sometimes|numeric|min:10',
                'work_start_time' => 'sometimes|date_format:H:i',
                'work_end_time' => 'sometimes|date_format:H:i',
            ]);

            $location->update($validated);

            return response()->json([
                'success' => true,
                'data' => $location,
                'message' => 'Office location updated successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Office location not found.',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to update office location: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($id);
            $location->delete();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Office location deleted successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Office location not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to delete office location: ' . $e->getMessage(),
            ], 500);
        }
    }
}
