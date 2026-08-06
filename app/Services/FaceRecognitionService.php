<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceRecognitionService
{
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.face_recognition.url', 'http://localhost:8000');
    }

    public function verify(string $base64Image, int $employeeId): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->apiUrl}/face/verify", [
                'image' => $base64Image,
                'employee_id' => $employeeId,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'verified' => false,
                'score' => 0,
                'message' => 'Face verification failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Face verification error: ' . $e->getMessage());

            return [
                'verified' => false,
                'score' => 0,
                'message' => 'Face recognition service unavailable',
            ];
        }
    }

    public function register(int $employeeId, array $images): array
    {
        try {
            $response = Http::timeout(60)->post("{$this->apiUrl}/face/register", [
                'employee_id' => $employeeId,
                'images' => $images,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'success' => false,
                'message' => 'Face registration failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Face registration error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Face recognition service unavailable',
            ];
        }
    }

    public function status(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiUrl}/face/health");

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Face recognition API returned status: ' . $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unreachable',
                'message' => 'Face recognition service is unreachable',
            ];
        }
    }
}
