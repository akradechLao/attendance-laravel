<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class PhotoService
{
    public function saveCheckInPhoto(int $empId, string $date, string $photoBase64): string
    {
        return $this->savePhoto($empId, $date, $photoBase64, 'check-in');
    }

    public function saveCheckOutPhoto(int $empId, string $date, string $photoBase64): string
    {
        return $this->savePhoto($empId, $date, $photoBase64, 'check-out');
    }

    private function savePhoto(int $empId, string $date, string $photoBase64, string $type): string
    {
        $folder = "photos/{$empId}/{$date}";
        $filename = "{$type}_" . time() . ".jpg";

        $imageData = base64_decode($photoBase64);
        $path = "{$folder}/{$filename}";

        Storage::disk('public')->put($path, $imageData);

        return $path;
    }

    public function getPhotoHistory(int $empId, string $startDate, string $endDate): array
    {
        $photos = [];
        $folder = "photos/{$empId}";

        if (Storage::disk('public')->exists($folder)) {
            $files = Storage::disk('public')->allFiles($folder);

            foreach ($files as $file) {
                $parts = explode('/', $file);
                $photoDate = $parts[2] ?? null;

                if ($photoDate && $photoDate >= $startDate && $photoDate <= $endDate) {
                    $photos[] = [
                        'path' => Storage::disk('public')->url($file),
                        'date' => $photoDate,
                        'type' => str_contains($file, 'check-in') ? 'check-in' : 'check-out',
                    ];
                }
            }
        }

        return $photos;
    }

    public function deletePhoto(string $path): bool
    {
        return Storage::disk('public')->delete($path);
    }
}
