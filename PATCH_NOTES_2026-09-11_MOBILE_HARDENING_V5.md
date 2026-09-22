# Mobile Hardening V5 — 2026-09-11

## Scope
UI/UX and responsive layout only. No controllers, routes, models, database schema/data, approval logic, notifications, validation rules, authentication behavior, calculations, or form submission behavior were changed.

## Fixes
- Removed the remaining horizontal clipping in premium forms at phone widths.
- Neutralized Bootstrap row negative-gutter overflow inside forms on narrow screens.
- Forced all mobile form columns to a true single-column layout.
- Added width/min-width guards to cards, sections, fields, routing controls, upload controls, and content surfaces.
- Converted key/value detail tables (Activity Proposal, FMO reservation/venue details, Requisition details) to stacked label/value cards on mobile.
- Kept full data grids horizontally swipeable where a table is genuinely required.
- Converted the Charge Slip requested-items entry table into mobile field cards while preserving the existing inputs and JavaScript calculations.
- Added wrapping protections for long proposal titles, venue names, schedules, requirement tags, program-flow text/files, and approval timeline labels/statuses.
- Stopped JavaScript from wrapping `kv-table` detail tables in a horizontal-scroll container.

## Files Changed
- `resources/views/layouts/admin.blade.php`
- `resources/views/activity_proposals/show.blade.php`
- `PATCH_NOTES_2026-09-11_MOBILE_HARDENING_V5.md` (new)

## Functional Safety
- Existing form names/IDs remain unchanged.
- Existing routes/actions remain unchanged.
- Charge Slip item add/remove/calculation selectors remain unchanged.
- Activity Proposal data and approval logic remain unchanged.
