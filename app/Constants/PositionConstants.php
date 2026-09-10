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

    // Thai labels for the coded position_level values, used for the HR dropdown
    // and for display wherever the coded value (not the free-text position) is shown.
    public const LEVEL_LABELS_TH = [
        'chairman' => 'ประธานกรรมการ',
        'md' => 'กรรมการผู้จัดการ',
        'executive_director' => 'ผู้อำนวยการบริหาร',
        'assistant_md' => 'ผู้ช่วยกรรมการผู้จัดการ',
        'division_manager' => 'ผู้จัดการฝ่าย',
        'sub_division_manager' => 'ผู้จัดการแผนก',
        'team_lead' => 'หัวหน้าทีม',
        'employee' => 'พนักงานทั่วไป',
    ];

    public static function getLevel(?string $position): int
    {
        return self::HIERARCHY[$position ?? ''] ?? 6;
    }

    public static function isExcluded(?string $position): bool
    {
        return in_array($position, self::EXCLUDED_POSITIONS, true);
    }

    /**
     * Assistant MD and above (assistant_md, md, executive_director, chairman)
     * don't request OT, shift swaps, or shift changes - they don't work fixed
     * shifts. Leave and WFH are unaffected by this (those still auto-approve
     * for chairman/md/executive_director via the HIERARCHY['md'] threshold).
     */
    public static function isTopManagement(?string $position): bool
    {
        return self::getLevel($position) <= self::HIERARCHY['assistant_md'];
    }
}
