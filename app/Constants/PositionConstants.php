<?php
namespace App\Constants;

class PositionConstants
{
    // Positions excluded from attendance calculation
    public const EXCLUDED_POSITIONS = ['chairman', 'md', 'executive_director', 'assistant_md'];

    // Position hierarchy (lower = higher rank)
    public const HIERARCHY = [
        'chairman' => 0,
        'md' => 1,
        'executive_director' => 1,
        'assistant_md' => 2,
        'division_manager' => 3,
        'sub_division_manager' => 4,
        'team_lead' => 5,
        'employee' => 6,
    ];

    // Group A positions (management)
    public const GROUP_A_POSITIONS = ['division_manager', 'sub_division_manager', 'team_lead'];

    public static function getLevel(string $position): int
    {
        return self::HIERARCHY[$position] ?? 6;
    }

    public static function isExcluded(string $position): bool
    {
        return in_array($position, self::EXCLUDED_POSITIONS);
    }
}
