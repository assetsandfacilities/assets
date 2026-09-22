# Patch Notes — 2026-09-20

## FMO Emergency Cancellation
- Emergency Cancellation is no longer restricted to requests that have reached full approval.
- FMO can release a non-rejected reservation while its approval route is still in progress.
- Existing approval signatures/timestamps are preserved; the emergency flow only releases/replaces the schedule slot.
- The requestor can still choose a replacement date/venue according to the option selected by FMO.
- An unresolved emergency cancellation cannot be started a second time; a completed rebooking may be emergency-cancelled again if needed.
- Updated FMO/requestor guidance text so it no longer incorrectly says that every approver has already signed.

## Users Tab — Manage Account Dialog
- Replaced the inline/collapsible Manage Account panel with a large scrollable modal dialog on both Asset Management and FMO user directories.
- Account Configuration fields now have wider usable space, clearer spacing, stronger field boundaries, and responsive stacking on smaller screens.
- E-Signature Management and Password Reset remain inside the same account dialog and keep their existing backend routes/processes.
- Existing create/update/delete/toggle/signature actions were not changed.
- Added mobile-friendly modal sizing and full-width controls so field content is no longer visually cut off.

## Backend / Data Safety
- No database migration was added for this patch.
- No existing account-management route names or form actions were changed.
- Approval records are not cleared or restarted during emergency rebooking.
