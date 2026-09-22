# Patch Notes — 2026-09-21

## Activity Proposals
- Removed the Activity Proposal print route, print page, and all Print buttons.
- Approved proposals now remain as digital records in the system.
- Added search by requester name, activity title, proposal number, or venue.
- Search results stay within the current account's existing authorization scope.

## Reserve Facility — Lead Time UX
- Kept `FMO_RESERVATION_LEAD_DAYS` as the single server-side source of truth.
- Removed the browser `min` behavior that could visually snap a selected date to the earliest allowed date.
- The system now keeps the date the requestor selected and shows a modern dialog when it is inside the configured lead-time window.
- The dialog shows the configured minimum number of days and the earliest allowed date.
- Submission is blocked until the requestor chooses a valid date.
- Server-side BookingWindow validation remains enabled, so crafted requests cannot bypass the rule.
- Removed automatic end-date/time filling on the Activity Proposal form; start and end are now chosen manually.
- Added the same lead-time dialog behavior to the standalone Facility Reservation form for consistency.

## UI/UX
- Added a responsive search bar to Activity Proposals.
- Added responsive, modern lead-time modal styling for desktop and mobile.
