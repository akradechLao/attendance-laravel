<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationService
{
    private string $nominatimUrl = 'https://nominatim.openstreetmap.org';

    public function reverseGeocode(float $latitude, float $longitude): ?string
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'AttendanceSystem/1.0 (attendance@etc1992.com)',
                ])
                ->get("{$this->nominatimUrl}/reverse", [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'json',
                    'accept-language' => 'th',
                    'zoom' => 18,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['display_name'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Nominatim geocoding failed: ' . $e->getMessage());
            return null;
        }
    }

    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function isWithinRadius(float $lat1, float $lon1, float $lat2, float $lon2, int $radiusMeters): bool
    {
        $distance = $this->calculateDistance($lat1, $lon1, $lat2, $lon2);
        return $distance <= $radiusMeters;
    }
}
