# Dialog-first FMO/UI update — 2026-09-21

Implemented without changing the existing approval order or reservation business rules.

- Approval actions now show a confirmation dialog with requestor, venue and schedule.
- Reject Proposal / Reject Request now use focused dialogs with a required reason.
- Pre-plotted requests show a conflict comparison dialog (auto-shown once per browser session per reservation).
- Emergency Cancellation is now a full dialog with booking summary, affected date, reason and allowed replacement options.
- Delete Account / Proposal / Reservation uses consistent styled confirmation dialogs; all DELETE forms receive a fallback confirmation automatically.
- Program Flow attachments preview inside a modal. PDFs render inline; DOCX/TXT uses extracted text with a secondary Open Original action.
- Reservation Requests and Activity Proposals now have dialog-based advanced filters: status, date range, department and venue.
- Both lists now have Quick View dialogs to reduce page changes.
- Existing success toasts and blocking error dialogs are retained system-wide.
- Facility Reservation, Activity Proposal and Rebooking forms now warn before discarding unsaved changes.
- Facility Reservation now uses the same sticky action bar pattern as the long Activity Proposal form.

No database migration is required for this UI/UX update.
