<?php

namespace App\Helpers;

class ShiftCodeHelper
{
    private static array $shifts = [
        'WC0001' => ['start' => '07:30', 'end' => '16:30', 'hours' => 8, 'overnight' => false, 'group' => 0],
        'WC0002' => ['start' => '08:00', 'end' => '17:00', 'hours' => 8, 'overnight' => false, 'group' => 1],
        'WC0003' => ['start' => '16:00', 'end' => '01:00', 'hours' => 8, 'overnight' => true, 'group' => 2],
        'WC0004' => ['start' => '00:00', 'end' => '09:00', 'hours' => 8, 'overnight' => true, 'group' => 3],
        'WC0005' => ['start' => '09:00', 'end' => '18:00', 'hours' => 8, 'overnight' => false, 'group' => 4],
        'WC0006' => ['start' => '20:00', 'end' => '05:00', 'hours' => 8, 'overnight' => true, 'group' => 5],
        'WC0007' => ['start' => '21:00', 'end' => '06:00', 'hours' => 8, 'overnight' => true, 'group' => 6],
        'WC0008' => ['start' => '08:00', 'end' => '16:30', 'hours' => 8, 'overnight' => false, 'group' => 7],
        'WC0009' => ['start' => '16:00', 'end' => '00:30', 'hours' => 8, 'overnight' => true, 'group' => 8],
        'WC0010' => ['start' => '00:00', 'end' => '08:30', 'hours' => 8, 'overnight' => true, 'group' => 9],
        'WC0011' => ['start' => '08:00', 'end' => '20:00', 'hours' => 12, 'overnight' => false, 'group' => 10],
        'WC0012' => ['start' => '20:00', 'end' => '08:00', 'hours' => 12, 'overnight' => true, 'group' => 11],
        'WC0013' => ['start' => '16:00', 'end' => '00:00', 'hours' => 8, 'overnight' => true, 'group' => 12],
        'WC0014' => ['start' => '00:00', 'end' => '08:00', 'hours' => 8, 'overnight' => true, 'group' => 13],
        'WC0015' => ['start' => '07:00', 'end' => '16:00', 'hours' => 8, 'overnight' => false, 'group' => 14],
        'WC0016' => ['start' => '19:00', 'end' => '04:00', 'hours' => 8, 'overnight' => true, 'group' => 15],
    ];

    public static function get(string $shiftCode): ?array
    {
        return self::$shifts[$shiftCode] ?? null;
    }

    public static function getTimes(string $shiftCode): array
    {
        $shift = self::get($shiftCode);
        return $shift ? ['start' => $shift['start'], 'end' => $shift['end']] : ['start' => null, 'end' => null];
    }

    public static function getStartTime(string $shiftCode): ?string
    {
        return self::$shifts[$shiftCode]['start'] ?? null;
    }

    public static function getEndTime(string $shiftCode): ?string
    {
        return self::$shifts[$shiftCode]['end'] ?? null;
    }

    public static function getLabel(string $shiftCode): string
    {
        $shift = self::get($shiftCode);
        if (!$shift) return $shiftCode;
        return "{$shift['start']}-{$shift['end']}";
    }

    public static function isOvernight(string $shiftCode): bool
    {
        return self::$shifts[$shiftCode]['overnight'] ?? false;
    }

    public static function getAll(): array
    {
        return self::$shifts;
    }

    public static function codeFromGroup(int $groupNumber): ?string
    {
        $code = 'WC' . str_pad($groupNumber + 1, 4, '0', STR_PAD_LEFT);
        return isset(self::$shifts[$code]) ? $code : null;
    }
}
