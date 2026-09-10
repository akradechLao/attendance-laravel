// Mirrors app/Constants/PositionConstants.php - keep both in sync.
export const POSITION_HIERARCHY = {
  chairman: 0,
  md: 1,
  executive_director: 1,
  assistant_md: 2,
  division_manager: 3,
  sub_division_manager: 4,
  team_lead: 5,
  employee: 6,
}

// Thai labels for the coded position_level values - mirrors PositionConstants::LEVEL_LABELS_TH.
export const POSITION_LEVEL_LABELS = {
  chairman: 'ประธานกรรมการ',
  md: 'กรรมการผู้จัดการ',
  executive_director: 'ผู้อำนวยการบริหาร',
  assistant_md: 'ผู้ช่วยกรรมการผู้จัดการ',
  division_manager: 'ผู้จัดการฝ่าย',
  sub_division_manager: 'ผู้จัดการแผนก',
  team_lead: 'หัวหน้าทีม',
  employee: 'พนักงานทั่วไป',
}

export const POSITION_LEVEL_OPTIONS = Object.keys(POSITION_HIERARCHY).map(value => ({
  value,
  label: POSITION_LEVEL_LABELS[value],
}))

export function getPositionLevel(position) {
  return POSITION_HIERARCHY[position] ?? 6
}

/**
 * Assistant MD and above don't work fixed shifts, so they don't request OT,
 * shift swaps, or shift changes. Leave and WFH are unaffected.
 */
export function isTopManagement(position) {
  return getPositionLevel(position) <= POSITION_HIERARCHY.assistant_md
}
