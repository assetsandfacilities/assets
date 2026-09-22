# Patch Notes — 2026-09-10

## Pre-Plotted Venue + Approval Trail Fix

### 1. First request stays normal Pending
- The first user who requests a venue on a date is **not** marked Pre-Plotted.
- Their reservation remains **Pending** while waiting for the **First Review — Facilities Management (FMO)** request approval.
- Their approval trail no longer shows **Venue Slot Approved**.
- Later approvers stay **Not yet approved** until the FMO request approval is completed.

### 2. Only later same-venue, same-date requests are Pre-Plotted
- Pre-Plotted is now saved at submission time using `is_pre_plotted`.
- The tag no longer changes dynamically when another request is later approved/rejected.
- Conflict detection uses the same venue plus a shared calendar date; time is intentionally ignored.
- Requestor lists and FMO lists both visibly show the **Pre-Plotted** state.

### 3. Pre-Plotted requests have two separate FMO actions
For a Pre-Plotted Activity Proposal, FMO now sees:
- **Approve Venue** — confirms the contested venue slot.
- **Approve Request** — performs the normal FMO step in the Activity Proposal approval trail.

For a Pre-Plotted request, **Approve Venue is step 1**. **Approve Request is step 2** and becomes active only after the venue is approved. The proposal proceeds to the Adviser / Program Chair only after both FMO gates are complete.

### 4. Venue Slot Approved moved to the top
- Only Pre-Plotted requests receive this trail step.
- It appears at the **top** of the FMO approval trail and the requestor's proposal trail.
- Normal first requests do not receive this step at all.

### 5. Safer competing-request behavior
- A venue cannot be assigned to a Pre-Plotted request if another same-date request is already fully confirmed or has already passed the FMO request review.
- If FMO confirms the Pre-Plotted venue first, a conflicting normal request cannot later pass FMO review until that conflict is resolved.
- Final Executive Director approval also checks confirmed same-date venue claims.

## FMO Reservation Queue UI Fix
- Added separate **Pending** and **Pre-Plotted** filters.
- The old behavior incorrectly labeled every pending reservation as Pre-Plotted.
- Added clearer status labels such as **Pre-Plotted — Venue Approved** while the proposal is still routing.
- The FMO Reservation Requests page keeps a Pre-Plotted filter, while the separate top-dashboard Pre-Plotted shortcut has been removed.

## Deleted Activity Proposal / Ghost Record Fix
- Deleting an Activity Proposal from FMO Super Admin now deletes its linked Facility Reservation in the same database transaction.
- Deleting the linked reservation from FMO Super Admin also deletes the Activity Proposal.
- Program Flow attachments are removed when the proposal is permanently deleted.
- Added a one-time migration cleanup for old one-sided / orphan records caused by the previous delete behavior.
- Old requestor-side proposal/reservation entries that no longer have a valid counterpart are cleaned up so they no longer remain visible after FMO Super Admin deletion.

## Database Migration
Added / updated:
- `database/migrations/2026_09_10_000021_fix_preplotted_venue_flow_and_orphan_proposals.php`
- `database/migrations/2026_09_10_000022_recalculate_preplotted_by_venue_date.php` (one-time TiDB-safe date-rule recalculation)

New `facility_reservations` fields:
- `is_pre_plotted`
- `venue_status`
- `venue_reviewed_by`
- `venue_reviewed_at`
- `venue_rejection_reason`

The migration also backfills existing reservations so only later same-venue, same-date requests become Pre-Plotted.

## Railway / TiDB Compatibility
- Docker uses `pdo_mysql` for the Railway/TiDB production connection.
- The temporary `pdo_sqlite` Docker addition from the earlier patch has been removed.
- Railway environment variables should continue to provide the production MySQL/TiDB credentials.
