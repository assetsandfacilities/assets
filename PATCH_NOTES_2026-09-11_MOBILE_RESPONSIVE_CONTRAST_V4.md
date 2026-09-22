# Mobile Responsive + Contrast Refinement V4 — 2026-09-11

## Scope
Frontend/UI/UX only. No backend logic, routes, controllers, models, database schema, authentication behavior, permissions, approval routing, notifications, calculations, or validation rules were changed.

## Mobile Responsive Improvements
- Reworked mobile sidebar into an off-canvas navigation drawer with a backdrop, Escape-to-close, and auto-close after navigation.
- Reflowed the topbar into a safer two-row mobile layout.
- Page action buttons now remain accessible in a horizontal swipe area instead of being clipped or reduced to icon-only controls.
- Reduced mobile content padding and card radii for better use of phone width.
- Made major grids, dashboard cards, user-management layouts, premium forms, routing cards, and inventory-create layouts stack cleanly.
- Forced form grid columns to a single readable column on phones.
- Added 16px mobile form-control text to avoid unwanted iOS input zoom.
- Improved modal, dropdown, tree-select, and approver-panel sizing on small screens.
- Changed dense data tables to safe horizontal-swipe behavior instead of hiding headers and converting rows into incomplete cards.
- Added automatic UI-only responsive wrappers around legacy `.data-table` and `.kv-table` elements that are not already inside `.table-responsive`.

## Readability / Field Separation
- Darker and clearer form labels with a blue left accent.
- 2px blue-gray borders for editable inputs, selects, dropdown triggers, and textareas.
- Stronger hover/focus rings and clearer active state.
- Readonly/disabled controls now use a distinct blue-gray filled surface and separate border color.
- Form section bodies use a light blue-gray background so white input controls no longer disappear into white cards.
- Stronger borders for form cards, route cards, budget cards, item cards, and system-generated info panels.

## Files Changed
- `resources/views/layouts/admin.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `PATCH_NOTES_2026-09-11_MOBILE_RESPONSIVE_CONTRAST_V4.md` (new)

## Authentication Screen Responsiveness
- Login, registration, and forgot-password screens now use stronger field borders and mobile-safe spacing.
- Registration and password-reset multi-column fields stack to one column on phones.
- Login can scroll vertically on short/landscape phones instead of clipping content.
- Mobile form inputs use 16px text to avoid browser auto-zoom.

## Functional Safety
- No `name` attributes were removed or renamed.
- No existing form `action` routes were changed.
- No controller/model/route/database files were modified.
- Responsive table wrapping is DOM-only and preserves original table IDs, form controls, tbody references, and event listeners.
