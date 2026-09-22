# FMO Users + Signature Routing Patch — 2026-09-10

## Signature Routing
- **Facilities Management — First Review is now auto-assigned.** The requestor no longer chooses an FMO account in the reservation form.
- New proposals enter a shared FMO first-review queue. The first authorized **FMO Staff** or **FMO Super Admin** who approves/rejects the FMO step becomes the assigned FMO reviewer.
- All active FMO approval accounts are notified when a new proposal enters the shared first-review queue.
- **SDAO is no longer department-grouped.** The selector now opens as `SDAO > [SDAO users]`.
- Signature Routing numbering is corrected to 1–6.
- Existing SDAO approver accounts have their `department_id` cleared by migration because SDAO is school-wide in this deployment.

## FMO User Management
- Kept admin names unchanged: **Super Admin** and **FMO Super Admin**.
- FMO create-role choices are now only:
  - FMO Super Admin
  - FMO Staff (database role remains `fmo` for compatibility)
  - Housekeeping
- **Requestor was removed from the assignable Role dropdown.** Requestor accounts continue to use the requestor registration flow.
- Existing self-registered Facilities requestors may still be visible to FMO Super Admin for maintenance, but their role is locked and cannot be converted from/to FMO staff roles there.
- E-signature management is shown only for FMO approval accounts (FMO Staff / FMO Super Admin).

## Housekeeping Ownership
- **Housekeeping is now administratively managed only by FMO Super Admin.**
- Housekeeping was removed from the Super Admin assignable roles and from the Super Admin Users query, including backend/direct-URL protection.
- Housekeeping keeps operational access to the Asset Scanner tools only; it does **not** gain FMO reservation approval powers.
- Existing housekeeping rows are normalized by migration to `account_type=housekeeping` and scanner-compatible scope.

## Database / Railway / TiDB
- Added migration: `2026_09_10_000023_align_fmo_user_ownership_and_sdao.php`.
- Migration uses ordinary Laravel/MySQL-compatible UPDATE statements and is suitable for the Railway + TiDB production setup.
- Like other Laravel migrations, it runs only once per database unless the migration record is intentionally rolled back/removed.
