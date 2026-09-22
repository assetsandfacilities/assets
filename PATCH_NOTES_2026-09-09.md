# Follow-up Patch Notes — September 9, 2026

## Restored Pre-Plotted Reservation Behavior
- Restored the visible **Pre-Plotted** indicator on Activity Proposal details.
- Pending facility reservations continue to function as temporary/pre-plotted venue slots while approval is in progress.
- FMO Reservation Requests and Venue Details now display pending reservations as **Pre-Plotted** instead of only "Pending".
- FMO Reservation Details includes an explanation that the slot is tentative until final approval.
- Existing conflict detection for pending/pre-plotted and approved reservations remains intact.

## Department > Approver Dropdowns
- School Facilities Reservation signature routing now uses a CAPEX-style hierarchical selector.
- The user sees departments first. Approver names are hidden until a department is clicked.
- Applied to Facilities Management, Adviser / Program Chair, Dean / Principal, SDAO, Academic Director, and Executive Director.
- Charge Slip signatories use the same Department > Approver selector for College Dean and Executive Director.
- Existing backend role checks and per-request selected-signatory authorization remain active.

## Regression Verification
- No original named web route was removed.
- No original controller or model method was removed.
- PHP syntax check passed for 191 project PHP files.
- Blade compilation/syntax check passed for 64 views.
- Laravel route list loads successfully with 176 routes.
- Bundled SQLite database integrity check: OK.
