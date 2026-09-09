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
