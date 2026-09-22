# Patch Notes — 2026-09-08

## Facilities / Activity Proposal
- Renamed the requestor action from “New Proposal” to “Reserve Facility”.
- Reworked Day(s) of Activity into Start Day / End Day selectors with automatic current-week Start/End Date previews (Asia/Manila); time remains manually selected.
- Name of Speaker is required; users may enter `N/A` when not applicable.
- Facilities Management is now the first approval stage for all new School Facilities Reservation digital proposals.
- Signature routing dropdowns are grouped by department.
- Approved proposals expose Print only after final approval, and the printable document includes proposal details, printed approver names, e-signatures, and approval timestamps.

## E-Signatures
- Approver account registration requires an e-signature via image upload or in-system drawing.
- FMO/FMO Super Admin account creation also requires an e-signature because those accounts can approve facility proposals.
- Existing hard-coded approval/admin accounts receive dummy e-signatures through migration/seeding; the bundled SQLite demo database is already upgraded.
- Approvers do not receive a self-service signature editor after account creation.
- Asset Management Super Admin and FMO Super Admin can replace e-signatures from their existing Users tab only; no new navigation tab was added.
- Every Super Admin signature replacement records user, changer, and exact date/time in a signature audit log.

## Asset Management / OPEX Charge Slip
- Requestors now select the College Dean and Executive Director for each Charge Slip.
- Signatory lists are grouped by department.
- Only the selected Dean/Executive can open/approve/reject their assigned stage; server-side authorization prevents direct-URL bypass.
- The issuance receipt now prints full request/issuance details plus Asset Management, Dean, and Executive Director e-signatures, printed names, and approval timestamps.

## Database
- Added migration `2026_09_08_000020_add_e_signatures_and_selected_signatories.php`.
- Added `signature_audits`, user signature fields, and per-requisition selected approver fields.
- Bundled `database/database.sqlite` was upgraded through migration 000020 and includes the FMO Super Admin demo account.

## Follow-up Patch — 2026-09-09

- Restored the visible **Pre-Plotted** venue-slot indicator for pending Activity Proposal reservations.
- FMO Reservation Requests now label pending venue slots as **Pre-Plotted** so the tentative booking state is clear in the FMO interface.
- FMO Reservation Details now explains that a Pre-Plotted slot is temporary and not yet a final confirmed reservation.
- Replaced flat/optgroup approver lists with a CAPEX-style **Department > Approver** tree dropdown on School Facilities Reservation signature routing.
- Applied the same Department > Approver tree selection to Charge Slip College Dean and Executive Director signatories.
- Approver names remain collapsed until the user explicitly opens a department.
- Existing server-side selected-signatory authorization and role validation remain in place.
- Regression check: no original named web routes and no original controller/model methods were removed.
