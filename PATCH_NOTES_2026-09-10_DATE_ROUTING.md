# Patch — Date-Based Pre-Plotted + Strict Sequential Approval Routing

## Changes

- Pre-Plotted detection now uses **same venue + shared calendar date**, regardless of time.
- The first active request for a venue/date remains normal Pending; later active requests for that same venue/date are Pre-Plotted.
- All competing/confirmed venue checks use the same date-based rule so approval behavior stays consistent.
- Added migration `2026_09_10_000022_recalculate_preplotted_by_venue_date.php` for Railway/TiDB databases that may already have run the previous migration. It runs once.
- Removed the **Pre-Plotted** shortcut button from the top of the FMO Dashboard. The Pre-Plotted filter inside Reservation Requests remains available.
- Downstream Activity Proposal visibility is now stage-based:
  - FMO first.
  - Adviser / Program Chair becomes visible only after the FMO gate is complete.
  - Dean / Principal becomes visible only after Adviser / Program Chair.
  - SDAO becomes visible only after Dean / Principal signs.
  - Academic Director becomes visible only after SDAO signs.
  - Executive Director becomes visible only after Academic Director signs.
- Backend signing was already status-gated; direct URL visibility and queue listing are now gated too.
- Renamed `Venue Slot — Final Confirmation` to **Venue Slot Approved**.

## TiDB / Railway

The new migration uses Laravel Query Builder / `whereDate()` and does not depend on SQLite-specific behavior.

- For Pre-Plotted requests, **Venue Slot Approved** is step 1 and **Approve Request (FMO)** is step 2. Both buttons remain visible to FMO, but Approve Request is disabled until Approve Venue is completed.
