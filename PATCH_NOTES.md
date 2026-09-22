# PATCH NOTES — Asset Management / FMO Super Admin Separation + FMO Reservation Features

Date: 2026-09-06
Base project: `upd-forgot-password` (NU Clark CAPEX/OPEX + Facilities System, Laravel)

---

## 1. Summary of the changes

This update splits the system into two fully independent administrative domains and adds
the FMO reservation features that were requested.

**Separation**

- The existing Super Admin (`super_admin`) is now **Asset Management only**. Every
  Facilities/FMO menu, route, controller action and user record is closed to it.
- A brand new role **`fmo_super_admin`** owns the Facilities side: dashboard,
  reservations, venues, items, services and FMO user accounts.
- Enforcement is on the **backend** (middleware + policy checks + query scopes), not just
  by hiding sidebar links. Typing an `/fmo/...` URL as an Asset Management Super Admin
  returns 403, and vice versa.

**New FMO features (from the follow-up request)**

- The FMO account now sees the **full approval trail** of each reservation — who has
  already approved, who is holding it right now, and who has not acted yet.
- Reservation Requests has a **"View All Details"** button opening a page with everything
  the requestor filled up, plus the entire approver process.
- The **"Items Needed" checkboxes now accept quantities** (how many speakers, tables,
  ITSO personnel, etc.).
- Ticking **"Others"** reveals a free-text field where the requestor types what else they need.
- The **Activity Proposals tab was removed from the FMO account** (it is a requestor form).
  FMO still signs proposals — the sign button moved onto the reservation detail page.
- Reservation Requests now has **status filters (All / Pending / Approved / Rejected)** and a
  **search bar** for requestor name, activity title, reservation number and venue.

No existing data, workflow, approval process, reservation, OPEX requisition or user account
was removed or reset. Every schema change is additive.

---

## 2. Files modified

| File | What changed |
|---|---|
| `app/Models/User.php` | New role helpers (`isFmoSuperAdmin`, `isFmoSide`, `canManageFmoUsers`, `canDeleteFacilityRecords`, `canAccessAssetManagement`); `canManageFacilities()` no longer returns true for the Asset Management Super Admin; new `facilitiesSide()` / `assetManagementSide()` query scopes; `homeRouteName()` sends FMO accounts to the FMO dashboard; added `facilityReservations()` and `activityProposals()` relations. |
| `app/Models/FacilityReservation.php` | New fillable columns; `approvalTrail()`, `approvalProgress()`, `approvedByNames()`, `pendingApproverNames()`, `requirementLines()`, `requirementOtherNote()`, `isApproved()`, `isRejected()`. |
| `app/Models/ActivityProposal.php` | Added `equipment_details` / `equipment_other_note` to `$fillable`; new `requirementLines()` helper. |
| `app/Http/Controllers/Web/FacilityController.php` | Venue CRUD moved out to the FMO controllers. `index()` now redirects by role. `storeReservation()` stores structured quantities and the Others note; notifies FMO roles; redirects by role. `destroyReservation()` is now FMO Super Admin only. |
| `app/Http/Controllers/Web/ActivityProposalController.php` | Loads the DB-driven item/service catalogue; stores quantities + Others note on both the proposal and its reservation; every `isAdmin()` override replaced with `isFmoSuperAdmin()` / `isFmoSide()` so Asset Management can no longer sign, reject or delete Facilities records. |
| `app/Http/Controllers/Web/AdminUserController.php` | Query now runs through `assetManagementSide()`; `ASSIGNABLE_ROLES` no longer contains any FMO role; new `guardTarget()` blocks writes aimed at FMO accounts. |
| `app/Http/Controllers/Web/AuthController.php` | New FMO staff registrations are routed to the FMO Super Admin for approval instead of Asset Management. |
| `app/Notifications/FacilityReservationStatusNotification.php` | New `linkFor()` sends FMO recipients to the reservation detail page and everyone else to a URL their role can actually open. |
| `bootstrap/app.php` | Registered `fmo_access`, `fmo_super_admin` and `asset_management` middleware aliases. |
| `routes/web.php` | New `/fmo` route group; Facilities routes gated; Asset Management/OPEX routes gated with `asset_management`. |
| `resources/views/layouts/admin.blade.php` | Sidebar rewritten into two mutually exclusive branches; Activity Proposals removed from FMO and from Asset Management Super Admin; brand and topbar labels follow the signed-in side; new CSS for trail states, filter chips and the quantity picker. |
| `resources/views/facilities/reserve.blade.php` | Uses the new items/services picker instead of a plain textarea. |
| `resources/views/activity_proposals/create.blade.php` | Hard-coded checkbox list replaced by the DB-driven picker with quantities and Others. |
| `resources/views/activity_proposals/show.blade.php` | Items now render with quantities and the Others note; role checks updated. |
| `resources/views/activity_proposals/index.blade.php` | Role checks updated. |
| `database/seeders/DatabaseSeeder.php` | Seeds the FMO Super Admin account and the facility item/service catalogue (idempotent `updateOrCreate`). |

---

## 3. New files created

**Models / support**
- `app/Models/FacilityItem.php`
- `app/Support/FacilityRequirements.php`

**Middleware**
- `app/Http/Middleware/FmoAccessMiddleware.php`
- `app/Http/Middleware/FmoSuperAdminMiddleware.php`
- `app/Http/Middleware/AssetManagementMiddleware.php`

**Controllers**
- `app/Http/Controllers/Web/Fmo/FmoDashboardController.php`
- `app/Http/Controllers/Web/Fmo/FmoReservationController.php`
- `app/Http/Controllers/Web/Fmo/FmoVenueController.php`
- `app/Http/Controllers/Web/Fmo/FacilityItemController.php`
- `app/Http/Controllers/Web/Fmo/FmoUserController.php`

**Views**
- `resources/views/fmo/dashboard.blade.php`
- `resources/views/fmo/reservations/index.blade.php`
- `resources/views/fmo/reservations/show.blade.php`
- `resources/views/fmo/venues/index.blade.php`, `show.blade.php`, `create.blade.php`, `edit.blade.php`, `form.blade.php`
- `resources/views/fmo/catalog/index.blade.php`
- `resources/views/fmo/users/index.blade.php`
- `resources/views/facilities/partials/requirements-picker.blade.php`

**Removed (intentionally replaced by the FMO venue screens)**
- `resources/views/facilities/index.blade.php`
- `resources/views/facilities/create.blade.php`
- `resources/views/facilities/edit.blade.php`
- `resources/views/facilities/form.blade.php`

---

## 4. Database migrations added

`database/migrations/2026_09_06_000016_add_fmo_super_admin_and_facility_catalog.php`

- **Creates `facility_items`**: `id, name, type (item|service), unit, description,
  allows_quantity, is_active, sort_order, timestamps`.
- **Adds to `activity_proposals`**: `equipment_details` (nullable text/JSON),
  `equipment_other_note` (nullable string).
- **Adds to `facility_reservations`**: `resources_details` (nullable text/JSON),
  `resources_other_note` (nullable string).
- **Back-fills the catalogue** with the previously hard-coded options plus the new ones
  (Table, Chairs, Sound System, Speaker, Projector, Extension Cord, Microphone, Flag,
  Whiteboard, ITSO Services, Technical Assistance, Audio/Visual Support, Janitors,
  Electricians) — **only when the table is empty**, so re-running migrations never
  duplicates or overwrites rows the FMO Super Admin has edited.

Notes:
- Every column is added with a `Schema::hasColumn` guard, so the migration is safe to run
  against an already-updated database.
- `users.role` is a plain `string` column, so the new `fmo_super_admin` value needed **no
  schema change** and no existing role was rewritten.
- The old `equipment_needed` / `resources_needed` string columns are untouched and still
  written to, so every old screen, export and notification keeps working.

---

## 5. Roles / permissions added or changed

| Role | Asset Mgmt | Facilities | FMO Users | Notes |
|---|---|---|---|---|
| `super_admin` (Asset Management Super Admin) | Full | **DENIED** | **DENIED** | Lost all Facilities access |
| `admin` (Asset Management Admin) | Full | **DENIED** | **DENIED** | Lost Activity Proposal override powers |
| `fmo_super_admin` (**NEW**) | **DENIED** | Full | Full | Venues, items, services, reservations, FMO users |
| `fmo` (FMO Staff) | **DENIED** | Manage (no deletes) | No | Existing responsibilities preserved |
| `approver` | Unchanged | Signs proposals as before | No | Unchanged |
| `requestor` | Unchanged | Submits reservations | No | Unchanged |
| `housekeeping` | Scans only | No | No | Unchanged |

Destructive Facilities actions (delete venue, delete reservation, delete catalogue entry,
delete activity proposal, delete FMO user) are **FMO Super Admin only**.

---

## 6. Asset Management Super Admin changes

- Sidebar no longer renders Facilities, Activity Proposals, or anything FMO.
- `canManageFacilities()` returns `false` — every `abort_unless` in the Facilities
  controllers now rejects it.
- All `/fmo/*` routes return **403** via `FmoAccessMiddleware`.
- `/facilities` redirects it to its own Asset Management home instead of showing venues.
- Its Users page runs `assetManagementSide()`, so FMO Super Admin and FMO staff accounts
  are not returned by the query at all.
- It can no longer assign `fmo` or `fmo_super_admin` roles — those values fail validation.
- It can no longer sign, reject or delete Activity Proposals.

## 7. FMO Super Admin features

- **Facilities Dashboard** (`/fmo/dashboard`) — pending/approved/rejected counts, venue
  counts, upcoming confirmed schedules, item/service/user counts, review queue.
- **Reservation Requests** (`/fmo/reservations`) — status filter, search bar, approval
  progress bar, View All Details.
- **Reservation Details** (`/fmo/reservations/{id}`) — full request data, linked Activity
  Proposal data, items and services with quantities, Others note, complete approval trail,
  approve/reject, and "Sign as Facilities Management".
- **Venues** (`/fmo/venues`) — full CRUD, activate/deactivate, detail view with history.
- **Facility Items** (`/fmo/items`) and **Facility Services** (`/fmo/services`) — add, edit,
  activate/deactivate, delete.
- **FMO Users** (`/fmo/users`) — list, add, edit, activate/deactivate, reset password, delete.
- No CAPEX, OPEX, requisition, issuance, supplier, forecast, scan or reference-data module
  appears anywhere in this account.

## 8. Venue management changes

- Full CRUD plus activate/deactivate, moved to `FmoVenueController`.
- Venues marked **active** appear automatically on the Reservation Request form and the
  Activity Proposal form (both read `Facility::where('is_active', true)`).
- **Delete protection:** a venue linked to any reservation record, or referenced by any
  Activity Proposal, cannot be deleted. The UI shows a "Protected" badge and the backend
  refuses the request with an explanation. Deactivating hides it from new reservations
  while all historical bookings stay intact.

## 9. Facility item / service management changes

- The reservation checklist is now **database-driven** through `facility_items`. Nothing is
  hard-coded in the Blade views anymore.
- Each entry has a name, a type (item or service), an optional unit, an optional
  description, a quantity-input toggle, an active flag and a display order.
- Adding an entry makes it appear on the Reservation form immediately; deactivating or
  deleting removes it from **new** forms only.
- Old reservations keep their own saved copy of the item name and quantity, so removing a
  catalogue entry never corrupts or blanks a historical record.

## 10. FMO user management changes

- The list is built with `User::query()->facilitiesSide()`, which returns only:
  FMO Super Admin, FMO staff, housekeeping, requestors from a facility-only department,
  and any requestor who has actually filed a facility reservation or activity proposal.
- Asset Management Super Admin, Asset Management Admins, approvers and OPEX-only
  requestors are excluded **in SQL**.
- Assignable roles are limited to `fmo_super_admin`, `fmo`, `housekeeping`, `requestor`.
- Actions: view, add, edit, activate/deactivate, reset password, delete.
- Guards: cannot change your own role, cannot remove the last FMO Super Admin, cannot
  delete your own account, cannot delete a user who has reservation or proposal history
  (deactivate instead, so the approval trail keeps their name).

## 11. Security improvements

- **Three new middleware** enforce the split at the routing layer: `fmo_access`,
  `fmo_super_admin`, `asset_management`.
- **Direct URL access is blocked both ways.** Asset Management accounts get 403 on
  `/fmo/*`; FMO accounts get 403 on dashboard, CAPEX, OPEX, requisitions, issuances, QR,
  reports, suppliers, allocations, departments, forecast, scans, reference data and the
  Asset Management users page.
- **No privilege escalation through edited form requests.** Both user controllers validate
  `role` against a whitelist that excludes the other side's roles, so a hand-crafted POST
  cannot mint an `fmo_super_admin` from Asset Management or a `super_admin` from FMO.
- **Target guards on every write.** `FmoUserController::guardTarget()` re-runs the
  facilities-side query against the target id, and `AdminUserController::guardTarget()`
  rejects FMO targets — so forging a user id in the URL fails even though the list never
  showed that user.
- Quantity input is clamped server-side to 1–100000 and item ids are resolved against the
  database, so arbitrary values cannot be injected into a reservation.
- Deletion of venues, reservations, catalogue entries and proposals is restricted to the
  FMO Super Admin at both the route (`fmo_super_admin` middleware) and controller level.

## 12. Routes / middleware changes

**New middleware aliases** (`bootstrap/app.php`): `fmo_access`, `fmo_super_admin`, `asset_management`.

**New route group** — all under `fmo_access`:

```
GET    /fmo/dashboard                         fmo.dashboard
GET    /fmo/reservations                      fmo.reservations.index
GET    /fmo/reservations/{reservation}        fmo.reservations.show
POST   /fmo/reservations/{reservation}/approve fmo.reservations.approve
POST   /fmo/reservations/{reservation}/reject  fmo.reservations.reject
DELETE /fmo/reservations/{reservation}        fmo.reservations.destroy   (fmo_super_admin)
GET    /fmo/venues                            fmo.venues.index
GET    /fmo/venues/create                     fmo.venues.create
POST   /fmo/venues                            fmo.venues.store
GET    /fmo/venues/{venue}                    fmo.venues.show
GET    /fmo/venues/{venue}/edit               fmo.venues.edit
PUT    /fmo/venues/{venue}                    fmo.venues.update
POST   /fmo/venues/{venue}/toggle             fmo.venues.toggle
DELETE /fmo/venues/{venue}                    fmo.venues.destroy         (fmo_super_admin)
GET    /fmo/items                             fmo.items.index
POST   /fmo/items                             fmo.items.store
GET    /fmo/services                          fmo.services.index
POST   /fmo/services                          fmo.services.store
PUT    /fmo/catalog/{facilityItem}            fmo.catalog.update
POST   /fmo/catalog/{facilityItem}/toggle     fmo.catalog.toggle
DELETE /fmo/catalog/{facilityItem}            fmo.catalog.destroy        (fmo_super_admin)
GET    /fmo/users                             fmo.users.index            (fmo_super_admin)
POST   /fmo/users                             fmo.users.store            (fmo_super_admin)
PUT    /fmo/users/{user}                      fmo.users.update           (fmo_super_admin)
POST   /fmo/users/{user}/toggle               fmo.users.toggle           (fmo_super_admin)
POST   /fmo/users/{user}/reset-password       fmo.users.reset-password   (fmo_super_admin)
DELETE /fmo/users/{user}                      fmo.users.destroy          (fmo_super_admin)
```

**Changed routes**

- `Route::resource('facilities', ...)` (venue CRUD) removed — replaced by `/fmo/venues`.
- `facilities.reservations.approve` / `.reject` now behind `fmo_access`.
- `facilities.reservations.destroy` now behind `fmo_super_admin`.
- `GET /facilities` kept as a redirect so old bookmarks and previously-sent notification
  links never 404 or 403.
- `asset_management` middleware added to: dashboard, items, departments, suppliers,
  allocations, reference-data, requisitions, issuances, QR, reports, forecast, asset-scans
  and the Asset Management users routes.

## 13. Testing performed

Verified by code review and route/flow tracing (see §14 for what this does not cover):

- Asset Management Super Admin: sidebar renders no Facilities/FMO links; `canManageFacilities()`
  is false; `/fmo/*` hits `FmoAccessMiddleware` before the controller; users query excludes FMO
  roles; role validation rejects `fmo` and `fmo_super_admin`.
- FMO Super Admin: lands on `/fmo/dashboard`; sidebar shows only Facilities links; no Activity
  Proposals tab; `asset_management` middleware blocks every Asset Management route.
- FMO staff: same Facilities navigation minus the Users tab; delete buttons hidden and the
  matching routes refuse them via `fmo_super_admin`.
- Reservation flow: submitting the form writes the summary string, the JSON breakdown and the
  Others note; the FMO detail page renders all three plus the trail.
- Old records: `FacilityRequirements::decode()` falls back to splitting the legacy comma string,
  so pre-update reservations still display their items (without quantities, which they never had).
- Migration re-run safety: `hasColumn` / `hasTable` guards and the empty-table check on the
  catalogue seed.
- Structural syntax check (brace/paren/bracket balance) run across all modified and new PHP files.

## 14. Remaining limitations

1. **No PHP runtime was available in the environment used to prepare this build**, so
   `php artisan migrate`, `php artisan serve` and the automated test suite were **not
   executed**. The code was reviewed and structurally checked, but please run the commands
   in §15 and click through the flows before treating this as production-ready. If
   anything fails on first run, the error will almost certainly be in one of the new files
   listed in §3.
2. Existing reservations created before this update have **no quantity data** — they only
   ever stored a comma-separated list. They display as item names without a quantity. This
   is a data limitation, not a bug.
3. Reservations that are **not** linked to an Activity Proposal have a short two-step trail
   (Submitted → FMO Confirmation), because there is no signature chain to show for them.
4. The **Activity Proposal routes still exist** for FMO accounts (the tab is removed from the
   sidebar only). This is deliberate: the FMO signature action posts to
   `activity-proposals.sign-facilities`, so blocking those routes would break signing.
5. `housekeeping` accounts appear in the FMO Users list because they sit in the FMO
   department, but they still use the Asset Scans module. Move them to another department
   if you want them out of that list.
6. The Asset Management Admin lost its Activity Proposal override powers (it could
   previously sign or reject any stage). If you want that back, it must be added
   deliberately — it conflicts with the separation as specified.
7. No automated PHPUnit tests were written for the new controllers.

## 15. Commands needed to run the updated project

```bash
# 1. Install PHP dependencies
composer install

# 2. Environment (an .env is already included; regenerate the key if needed)
php artisan key:generate

# 3. Run the new migration on your EXISTING database (additive, keeps all data)
php artisan migrate

# 4. Optional — add the FMO Super Admin account and the item/service catalogue
#    to an existing database. This seeder uses updateOrCreate, so it will not
#    duplicate or reset existing records.
php artisan db:seed

# 5. Clear caches after the route/view changes
php artisan optimize:clear

# 6. Run
php artisan serve
```

**For a clean demo database only** (this DROPS everything — never run it on live data):

```bash
php artisan migrate:fresh --seed
```

### Demo accounts

| Account | Email | Password |
|---|---|---|
| Asset Management Super Admin | `superadmin@nuclark.local` | `super123` |
| Asset Management Admin | `admin@nuclark.local` | `admin123` |
| **FMO Super Admin (new)** | `fmosuperadmin@nuclark.local` | `fmosuper123` |
| FMO Staff | `fmo@nuclark.local` | `fmo12345` |
| Requestor | `requestor@nuclark.local` | `request123` |

Change these passwords before any real deployment.

## 2026-09-06 — Voucher-Gated Account Creation & Admin Scope Separation

### Account creation
- Replaced the public Requestor / Approver / FMO role selector with two registration paths:
  - **Student Access** — no voucher required; account is created as a Student requestor and is routed to **Activity Proposals** only.
  - **Asset Management Access** — locked until a valid voucher from Asset Management is verified.
- Public registration no longer allows users to self-select `approver`, `fmo`, `admin`, or `super_admin` roles.
- Added explicit user fields:
  - `account_type` (`student`, `staff`, `organization`, `approver`, etc.)
  - `access_scope` (`fmo` or `asset`)
- Staff and Organization Asset requestors receive **OPEX + Requisition + Activity Proposals** access.
- Approver accounts remain **Pending Review** after voucher registration until Asset Management approves them.

### Asset Management access vouchers
- Added **Access Vouchers** page for Asset Management Admin / Super Admin.
- Voucher types are intentionally separate:
  - Staff
  - Organization
  - Approver
- Approver vouchers are bound to an exact approver type (`Adviser`, `Dean`, `SDAO`, `Academic Director`, or `Executive Director`). The account creator cannot change it.
- Optional department binding is supported. If a voucher is bound to a department, registration cannot override that department.
- Vouchers are:
  - Single-use
  - Expiring (24 hours / 3 days / 7 days)
  - Revocable before use
  - Server-side verified
  - Stored as SHA-256 hashes; the full voucher is shown only once when generated
  - Audited with generator, usage, revocation, and timestamp fields

### User Management separation
- New Student requestors are owned by the **FMO / Facilities** side and do not appear in Asset Management Users.
- New Staff and Organization requestors, Approvers, Asset Admins, and Asset Super Admin remain on the **Asset Management** side.
- FMO can still process facility/activity requests submitted by Staff or Organizations, but does not own or edit those Asset-side accounts.
- Write endpoints now re-check account scope, preventing a hand-crafted request from Asset Management from editing an FMO-owned Student account.

### Authorization hardening
- `access_scope` is now enforced by `AssetManagementMiddleware` through `User::canAccessAssetManagement()`.
- A Student account (`scope=fmo`) is blocked from direct Asset Management URLs even if a sidebar link is hidden.
- Legacy accounts without `account_type` / `access_scope` retain the previous role/department fallback behavior for compatibility.

### Database migration
Run after deploying this patch:

```bash
php artisan migrate --force
```

New migration:
`2026_09_06_000017_add_account_types_and_access_vouchers.php`

## Registration UI refinement
- Combined the email send-code and 6-digit verification-code sections into one Step 1.
- Removed the duplicate "enter the same email" field; verification now uses the email stored in the registration session.
- Renumbered registration to 3 steps: Verify Email, Choose Account Access, Finish Account Creation.
- The email in the final account form is now read-only and comes only from the verified email in Step 1.
- Staff, Organization, and Approver voucher registrations now remain Pending Review until Asset Management Admin/Super Admin approval.
- Asset Management admins receive the existing database notification for all pending voucher-created Asset accounts, not only Approvers.

## 2026-09-06 — Voucher accounts active immediately + real account types

- Staff, Organization, and Approver accounts created with a valid Asset Management voucher are now **active immediately** after successful registration.
- Removed the second/pending Asset Management approval step for voucher-created accounts. The voucher issuance is now the authorization step.
- Removed the registration-side "pending approval" notification for Asset Management voucher accounts.
- Existing Staff / Organization / Approver rows that are still pending are activated by migration `2026_09_06_000018_backfill_account_types_and_activate_voucher_accounts.php`.
- Asset Management Users now shows **Account Status: Active / Deactivated** instead of Approved / Pending Review.
- Removed the user-facing **Legacy** value from the Account Type column.
- Existing accounts are backfilled to meaningful account types such as Staff, Approver, Asset Management Admin, Asset Management Super Admin, FMO Staff, FMO Super Admin, Student, or Housekeeping.
- New voucher registrations continue storing the exact type: `staff`, `organization`, or `approver`.
- Important: old requestor records created before account types existed did not store whether they were Staff vs Organization. Facility-only old requestors are classified as Student; remaining old requestors are classified as Staff. New Organization registrations remain correctly identified as Organization.

### Deploy

```bash
php artisan optimize:clear
php artisan migrate --force
```


## Requestor voucher simplification + voucher history deletion
- Removed **Staff** and **Organization** as new Asset Management voucher/account choices.
- Asset Management voucher types are now only **Requestor** and **Approver**.
- Requestor accounts retain OPEX, Requisition, and Activity Proposals access.
- Existing Asset-side Staff/Organization accounts are migrated to `account_type=requestor`.
- Existing Staff/Organization voucher history is migrated to `voucher_type=requestor`.
- Added permanent Voucher History deletion for the **Asset Management Super Admin only**. Regular Asset Management Admins can still generate/revoke vouchers but cannot delete history.

---

# ADDENDUM — Password Policy + Show/Hide Password (2026-09-06)

## Password policy: 8 characters, 1 number, 1 symbol

New single source of truth in `app/Http/Controllers/Web/AuthController.php`:

```php
public static function passwordRules(): Password
{
    return Password::min(8)->numbers()->symbols();
}
```

Applied everywhere a password is set, so the rule cannot drift between screens:

- `AuthController@register` (account creation)
- `AuthController@resetPassword` (forgot-password flow)
- `Fmo\FmoUserController@store` (FMO Super Admin creates a facilities account)
- `Fmo\FmoUserController@resetPassword` (FMO Super Admin resets a password)

Validation is server-side, so a user cannot bypass it by editing the form.

## Show/hide password (eye icon)

New file: `resources/views/auth/partials/password-toggle.blade.php`

Included on: `auth/login.blade.php`, `auth/register.blade.php`,
`auth/forgot-password.blade.php`, and `layouts/admin.blade.php`.

It automatically finds every `input[type="password"]` on the page and injects an
eye button inside the field. No markup changes were needed on the inputs
themselves, so nothing existing was restructured or could break. The icon flips
between "eye" and "eye with a slash" and updates its `aria-label` for screen
readers.

## Live password checklist

Any password input marked `data-pw-rules` also renders a small checklist
underneath (8 characters / 1 number / 1 symbol) that ticks green as the user
types. This mirrors the server rule exactly, so users see why a password is
rejected before submitting instead of after.

Marked inputs: the new-password field on registration, on password reset, and on
the FMO Super Admin's "Add Facilities User" form. Sign-in was deliberately left
without the checklist -- it only needs the eye icon.

## Files changed in this addendum

- `app/Http/Controllers/Web/AuthController.php` (new `passwordRules()`, 2 rule sites)
- `app/Http/Controllers/Web/Fmo/FmoUserController.php` (2 rule sites)
- `resources/views/auth/partials/password-toggle.blade.php` (new)
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/fmo/users/index.blade.php`

No migration is required. Existing passwords are unaffected -- the new rule only
applies when a password is created or changed from now on.

## Not done in this addendum

The screenshot that was meant to receive an image did not arrive usable (it came
through at 35x26 pixels, ~650 bytes, which is a thumbnail rather than the actual
screen capture). No image was added to any page as a result. Re-upload the
screenshot at full size and this can be finished.

## Browser tab icon (favicon)

`public/favicon.ico` existed but was a **0-byte empty file**, which is why the
browser fell back to its generic globe icon on the tab.

New artwork matches the gold "NU" badge on the login screen exactly: rounded
gold gradient square (`#F0C876` to `#C9932E`) with the wordmark in navy
(`#080D22`).

Generated files in `public/`:

- `favicon.ico` — multi-resolution (16/32/48) so Windows and Chrome pick the sharpest
- `favicon-16x16.png`, `favicon-32x32.png`, `favicon-48x48.png`, `favicon-512x512.png`
- `apple-touch-icon.png` (180x180) for iOS home-screen bookmarks
- `icon.svg` — scalable version for browsers that prefer it
- `site.webmanifest` — sets the installed-app name and `#0C1330` theme colour

New file: `resources/views/partials/favicon.blade.php`, included in the `<head>`
of `layouts/admin`, all three auth pages, both error pages, and `welcome`.

The leftover v0/Next.js scaffold icons (`icon-light-32x32.png`,
`icon-dark-32x32.png`, `apple-icon.png`) were **replaced** with the NU artwork
rather than deleted, because `app/layout.tsx` still references those filenames.

Note: browsers cache favicons aggressively. After deploying, hard-refresh
(Ctrl+Shift+R) or open the tab in a private window to see the new icon.

---

# ADDENDUM — Department form cleanup (2026-09-06)

## 1. CAPEX Limit removed

Confirmed by code search: `capex_limit` was **never read by any budget
calculation** anywhere in the system. It was only stored, validated and
displayed. Only OPEX consumes department budget (`Department::opexConsumed()`
and `opexRemaining()`), so the field was dead weight on the form.

Removed from:
- `resources/views/departments/form.blade.php` (input)
- `resources/views/departments/index.blade.php` (table column + "High CAPEX" filter option)
- `app/Http/Controllers/Web/DepartmentController.php` (store + update validation)
- `app/Http/Controllers/Api/DepartmentController.php` (store + update validation)
- `app/Models/Department.php` (`$fillable`)
- `database/seeders/DatabaseSeeder.php` (seed values)

**The database column was intentionally kept.** Dropping it would need a
destructive migration and there is no benefit — it defaults to 0 and nothing
writes to it now. If you later want it gone, that can be a separate migration.

## 2. "OPEX Limit" renamed to "Department Budget"

Label-only change across the UI:
- Department form: **Department Budget (₱)**, with a hint line explaining it is
  the total the department may consume through OPEX requisitions.
- Departments list: columns are now Department Budget / Consumed / Remaining.
- Requisitions page header stat.
- Reports page budget table.

**The column is still named `opex_limit` in the database.** Renaming it would
mean touching `RequisitionController`, `ReportController`,
`Department::opexRemaining()` and the requisition views for no functional gain,
and would risk breaking budget enforcement. The label is what users see; the
column name stays internal.

## 3. "This department does not use OPEX Inventory / Requisitions" removed

This checkbox (`restrict_supply_requests`) predates the account-type system. It
is now redundant: `User::canRequestSupplies()` decides access from
`account_type` first — `student` never gets OPEX, `requestor`/`staff`/
`organization` always do — and migration `..._000018_backfill_account_types...`
assigns an `account_type` to **every** existing user.

Removed from the form, the list hint, and both controllers' validation.

**Deliberately left in place:** the `restrict_supply_requests` column, its cast
on the model, and the final fallback line inside `canRequestSupplies()`. If a
row somehow still has a null `account_type` (e.g. an account created by a script
that bypassed registration), that fallback is the only thing stopping it from
silently gaining OPEX access. It costs nothing to keep and closes a real gap.

## Files changed in this addendum

- `resources/views/departments/form.blade.php`
- `resources/views/departments/index.blade.php`
- `resources/views/requisitions/index.blade.php`
- `resources/views/reports/index.blade.php`
- `app/Http/Controllers/Web/DepartmentController.php`
- `app/Http/Controllers/Api/DepartmentController.php`
- `app/Models/Department.php`
- `database/seeders/DatabaseSeeder.php`

No migration required. No existing department data is altered.

---

# ADDENDUM — Remove Recent Activity Proposals + fix proposal URL access (2026-09-06)

## 1. Panel removed from the Asset Management dashboard

The "Recent Activity Proposals" panel is gone from `dashboard/index.blade.php`,
and `DashboardController` no longer queries `ActivityProposal` at all (the
`$recentProposals` query and its `use` import were removed). Activity Proposals
are a Facilities/requestor concern, so the Asset Management dashboard should
never have been surfacing them.

The "Recent Requisitions" panel now spans full width instead of sitting in a
half-width 2-column grid.

## 2. SECURITY FIX — direct URL access to activity proposals

The concern raised was correct, and the problem was broader than the dashboard.

**What was wrong:** `ActivityProposalController@show()` had **no authorization
check whatsoever**. Any authenticated account could open
`/activity-proposals/{id}` for **any** proposal by typing the URL or changing
the id. That meant:

- Asset Management Super Admin / Admin could read every facility request,
  despite the FMO separation.
- Any requestor could read any **other** requestor's proposal by incrementing
  the id — including organization details, program flow and the full signature
  trail.

The list page (`index()`) was already scoped correctly per role, so this was
only ever reachable by URL — which is exactly why it went unnoticed.

**The fix:** new `ActivityProposal::canBeViewedBy(User $user)`, enforced with
`abort_unless(...)` at the top of `show()`. Access is now limited to people with
an actual stake in the document:

- the requestor who filed it,
- any officer named in its signature routing (adviser, dean/principal, SDAO,
  facilities, academic director, executive director),
- anyone who has already signed it,
- the Facilities side (`isFmoSide()`), which has to act on it.

Everyone else gets a 403. Asset Management accounts are not stakeholders and are
therefore denied.

**Worth noting for the rest of the system:** the same class of bug does not
apply to facility reservations — `fmo.reservations.show` already sits behind the
`fmo_access` middleware. This was specific to the activity proposal route, which
was intentionally left ungated so FMO could still post to `sign-facilities`.

## Files changed in this addendum

- `resources/views/dashboard/index.blade.php`
- `app/Http/Controllers/Web/DashboardController.php`
- `app/Models/ActivityProposal.php` (new `canBeViewedBy()`)
- `app/Http/Controllers/Web/ActivityProposalController.php` (`show()` guard)

No migration required.

---

# ADDENDUM — Program flow attachment + alert dialogs (2026-09-09)

## 1. Program Flow file upload with automatic text extraction

New migration `2026_09_09_000020_add_program_flow_attachment_to_activity_proposals.php`
adds `program_flow_path`, `program_flow_filename`, `program_flow_mime` and
`program_flow_extracted`. Additive and guarded with `hasColumn`.

New `app/Support/DocumentTextExtractor.php` reads uploaded documents with **no
Composer dependencies**, so this works on a fresh clone without anyone running
`composer require`:

- **DOCX** — a .docx is a ZIP; `word/document.xml` is read with the standard
  ZipArchive extension and paragraph markup is converted back into line breaks.
  Exact and reliable.
- **TXT** — read directly.
- **PDF** — best effort. Text-drawing operators (`Tj` / `TJ`) are pulled out of
  the content streams, including Flate-compressed ones. This handles the normal
  case of a PDF exported from Word. It cannot read a scanned/image PDF. If
  `smalot/pdfparser` is ever installed it is used automatically instead, since
  it is far more accurate.

Extracted text is written into the existing `program_flow` column, so every
screen that already reads it — the FMO reservation detail page, the proposal
details page and the printed form — works unchanged. The original file is kept
and linked so the FMO can open the real document either way.

`program_flow` is now `nullable` in validation: the requestor may type it,
upload it, or both. If they do both, the typed text is kept and the extracted
text appended beneath a labelled separator rather than being discarded. If
neither produces text, submission is blocked with a message explaining why the
file could not be read.

**Security:** the file is stored on the private disk (`storage/app/program-flows`),
not the public one, and served through `activity-proposals.program-flow-file`,
which applies the same stakeholder check as `show()`. No `storage:link` is
required and files are not reachable by guessing a URL.

## 2. System alerts replaced with toasts and dialogs

New `resources/views/partials/alerts.blade.php` replaces three things:

1. the flat green "…updated successfully." bar,
2. the bulleted red validation list,
3. the browser's native `confirm()` box on deletes.

**Design choice:** success messages appear as a **toast** that slides in and
dismisses itself; errors open a **modal dialog** that must be acknowledged. A
save confirmation should not block the user's next click, but a failure should
never be missed. To make successes modal too, change `NUAlert.toast` to
`NUAlert.dialog` in the `session('success')` block.

Includes: animated entry, auto-dismiss progress bar, coloured status icons,
keyboard support (Escape cancels, Enter confirms), backdrop-click dismiss,
`aria-live` regions, mobile layout, and a `prefers-reduced-motion` fallback.

All **19** native `confirm()` calls across the views were converted to
`data-confirm` attributes, which the component upgrades into the styled dialog
automatically. Works on both forms and links.

The component is mounted in `layouts/admin.blade.php` and on all three auth
pages, so login, registration and password reset use the same visual language as
the rest of the system.

## Files

New: the migration, `app/Support/DocumentTextExtractor.php`,
`resources/views/partials/alerts.blade.php`

Modified: `app/Models/ActivityProposal.php`,
`app/Http/Controllers/Web/ActivityProposalController.php`, `routes/web.php`,
`resources/views/layouts/admin.blade.php`,
`resources/views/activity_proposals/create.blade.php`,
`resources/views/activity_proposals/show.blade.php`,
`resources/views/fmo/reservations/show.blade.php`,
`resources/views/auth/login.blade.php`, `register.blade.php`,
`forgot-password.blade.php`, plus 14 views whose `confirm()` calls were converted.

## Run

```bash
php artisan migrate
php artisan optimize:clear
```

---

# FIXES — 2026-09-09 (second pass)

## 1. PDF text extraction actually works now

**Reported:** uploading a program flow PDF failed with "No readable text was
found in that file", even though the PDF plainly contained text.

**Cause:** the first implementation only looked for literal `(text) Tj` strings.
PDFs exported from Word embed **subsetted fonts** and write text as hex glyph
IDs instead — e.g. `[<03F40357>] TJ`. Those numbers are glyph indexes inside the
embedded font, not characters, so pulling the strings out gives nothing readable.
The earlier claim that the fallback "handles the normal case of a PDF exported
from Word" was wrong: that is precisely the case it failed on.

**Fix:** `DocumentTextExtractor::parsePdf()` was rewritten to do the job properly,
still with no Composer dependency:

1. Index every indirect object in the file.
2. For each font resource, follow `/ToUnicode` to its CMap stream and parse the
   `beginbfchar` / `beginbfrange` tables into a glyph-code to character map,
   including both the `<lo> <hi> <base>` and `<lo> <hi> [<a> <b> …]` range forms.
3. Walk each page's content stream tracking the active font (`/F1 Tf`), decode
   hex glyph runs through that font's map, and also handle literal strings.
4. Recover line breaks from the vertical coordinate in the text matrix (`Tm`),
   so the program flow keeps one entry per line.

Verified against the uploaded `asd.pdf`, which now extracts exactly:

```
8:00AM REG
7:30AM FLAG CEREMONY
12:00PM Lunch Break
```

Scanned or image-only PDFs still cannot be read — there is no text in them to
find — and those still fall back to the attached file plus a clear message.

## 2. Red text alerts removed — dialogs only

**Reported:** the old red validation bar still appeared even though the dialog
also fired.

**Cause:** the previous pass replaced the flash block in `layouts/admin.blade.php`
but missed five views that rendered their **own** inline copies. Those pages were
therefore showing both.

**Fix:** the duplicate flash and validation blocks were removed from
`activity_proposals/create.blade.php`, `activity_proposals/show.blade.php`,
`asset_scans/index.blade.php`, `forecast/index.blade.php` and
`reference-data/index.blade.php`. Every `session('success')` and `$errors` render
in the application now goes through `partials/alerts.blade.php` and nothing else.

Contextual alerts that are page content rather than system notifications were
deliberately left alone: the requisition rejection reason, the live budget
warning, the unresolved-mismatch count, and the QR match/mismatch verdict.

## 3. Selected items no longer look like errors

**Reported:** Table, Microphone and ITSO Services appeared highlighted while the
error was actually about Program Flow.

**Cause:** `.req-card.is-on` used an amber border and glow to mean "ticked".
Amber reads as a warning, and sitting beside a red error banner it looked as
though those fields were the problem.

**Fix:** the selected state is now a green border on a pale green background,
which reads as "chosen" rather than "wrong". Nothing about the behaviour changed.

---

# FIX — "Pre-Plotted" appeared on every pending reservation (2026-09-09)

**Reported:** a venue showed as Pre-Plotted even when only one person had
requested it.

**Cause:** `FacilityReservation::isPrePlotted()` was literally identical to
`isPending()`:

```php
public function isPrePlotted(): bool
{
    return $this->status === 'pending';
}
```

So every reservation awaiting review was labelled Pre-Plotted, which made the
label meaningless — it never distinguished anything.

**Fix:** pre-plotted now means what it is supposed to mean — the slot is only
tentatively held **because someone else is competing for it**:

```php
public function isPrePlotted(): bool
{
    return $this->status === 'pending' && $this->competingCount() > 0;
}
```

New `competingReservations()` finds other reservations for the same venue whose
time range overlaps and whose status is pending or approved. Rejected requests
are excluded — they are not competing for anything. The count is memoised per
model instance so list pages do not re-run the query on every render.

A lone pending request now simply reads **Pending**, which is accurate.

**Also updated:**

- The submit confirmation no longer claims every slot is pre-plotted. It says
  the slot is held while routing, and only mentions pre-plotting when there is a
  genuine overlap — in which case it explains that Facilities Management will
  decide between the requests.
- The FMO reservation detail page now lists the competing requests by reference
  number, requestor, schedule and status, each linked, so the FMO can see exactly
  what it is choosing between instead of just being told a conflict exists.

Affects the label on the FMO reservation list, the FMO reservation detail page,
the venue detail page and the activity proposal detail page — all of which call
the same method.

---

# CHANGE — Pre-Plotted is now first-come (2026-09-09)

**Requested:** the person who reserves a free venue first should stay plain
**Pending**. Only requests that arrive afterwards, onto an already-taken slot,
should show **Pre-Plotted**.

Previously contention was mutual — once a second request overlapped, both the
earlier and the later reservation were flagged Pre-Plotted.

**Implementation:** `FacilityReservation::priorReservations()` returns the
overlapping reservations that outrank this one:

- any overlapping reservation that is already **approved** (the venue is
  genuinely committed, regardless of submission order), or
- any overlapping **pending** reservation created earlier, using `created_at`
  with the record `id` as a tiebreaker so two submissions in the same second
  still resolve deterministically.

`isPrePlotted()` is now `status === 'pending' && priorCount() > 0`. The first
requestor has no prior holders, so their reservation reads Pending. Every later
overlapping request reads Pre-Plotted.

`competingReservations()` is kept for cases where all overlaps matter; the count
is memoised per instance so list pages do not re-query per row.

The FMO detail banner now lists the **earlier** requests that hold the slot,
each linked, and states that approving this one means choosing it over them.

## Also changed: the direct Reserve Facility form no longer blocks overlaps

`FacilityController@storeReservation` used to reject an overlapping submission
outright:

> "Schedule conflict detected. This facility already has a pending or approved
> reservation in that time range."

That made pre-plotting impossible on this route — a second requestor could never
submit at all — while the Activity Proposal form allowed it. The two entry
points were enforcing opposite policies.

Overlapping submissions are now accepted here too and become pre-plotted, so
both routes behave the same way. The confirmation message tells the requestor
their slot is pre-plotted when something already holds it.

**Double-booking is still prevented**, just at the point where it matters:
`approve()` continues to refuse a reservation that overlaps an already-approved
one. Nothing can be confirmed twice for the same venue and time.

If you would rather the direct form keep rejecting overlaps, that guard can be
restored on its own without touching the seniority logic.

---

# HOTFIX — 500 error on /activity-proposals/create (2026-09-09)

**Reported:** the Create Activity Proposal page returned HTTP 500.

**Cause:** my own mistake in the previous pass. When removing the duplicate
inline validation banner from `activity_proposals/create.blade.php`, I deleted
the `@if ($errors->any())` line and the `<div>` beneath it but **left the
matching `@endif` behind**. Blade then failed to compile the template, which
surfaces as a 500 on that route only.

**Fix:** the orphaned `@endif` was removed. Directive balance for the file is
now `@if 0 / @endif 0`, `@foreach 3 / @endforeach 3`, `@php 1 / @endphp 1`,
`@section 1 / @endsection 1`.

**Checked the rest:** every Blade template in the project was scanned for
unbalanced directives. `create.blade.php` was the only genuine break. Two other
files flagged by the scan were verified as false positives:

- `layouts/admin.blade.php` — `@hasSection('page-actions')` is correctly closed
  with `@endif`, which is valid Blade.
- `access-vouchers/index.blade.php` — uses the inline
  `@section('title', 'Access Vouchers')` form, which takes no `@endsection`.

The other four views I edited in that pass removed single-line blocks where the
`@endif` was on the same line, so nothing was left dangling in them.

---

# BUILD FIX — Railway deploy failed on composer install (2026-09-09)

**Symptom:**

```
Failed to download doctrine/lexer from dist: The
"https://api.github.com/repos/doctrine/lexer/zipball/..." file could not be
downloaded (HTTP/2 504)
Source fallback is disabled. Not trying alternative sources.
```

**This is not a code problem.** HTTP 504 is a gateway timeout returned by
GitHub's own API. Composer asked GitHub for the `doctrine/lexer` zipball,
GitHub failed to answer in time, and because source fallback was disabled
Composer gave up and aborted the entire install. Nothing in the application
caused it and no source file needed changing.

The build was fragile rather than wrong: a single flaky download out of 80
packages killed the whole deploy.

**Dockerfile hardening:**

- `ENV COMPOSER_ALLOW_SUPERUSER=1` — Composer runs as root inside the image, and
  without this it disables its own plugins and warns on every build. This is the
  warning at the top of the log.
- `ENV COMPOSER_PROCESS_TIMEOUT=600` — more room before a slow response is
  treated as dead.
- The install now **retries up to three times** with a pause between attempts,
  and on the final attempt falls back to `--prefer-source`. That path clones
  from `github.com` over git rather than fetching zipballs from
  `api.github.com`, which is a different service and usually still works while
  the API is degraded.
- `--no-interaction --no-progress` for cleaner, quieter build logs.

**Optional but recommended:** add a `COMPOSER_AUTH` environment variable in
Railway containing a GitHub personal access token:

```json
{"github-oauth":{"github.com":"ghp_yourtokenhere"}}
```

Anonymous requests to `api.github.com` are rate-limited and are the first to be
throttled when GitHub is under load. An authenticated token raises the limit
substantially and makes this class of failure much rarer.

**Not changed:** `mbstring` was briefly added to the extension list and then
reverted. Laravel already boots on this image, so mbstring is clearly present,
and running `docker-php-ext-install` on an already-bundled extension can fail
and break a build that currently works.

**First thing to try:** just redeploy. A 504 is transient, and the same commit
will very likely build on a retry even before these changes take effect.

---

## 2026-09-10 — Pre-Plotted Venue / Approval Trail / Synchronized Delete Fix

See `PATCH_NOTES_2026-09-10.md` for full details. This update persists the Pre-Plotted state only on later same-venue, same-date requests, separates **Approve Venue** from **Approve Request**, moves **Venue Slot Approved** to the first trail position only for Pre-Plotted requests, separates Pending vs Pre-Plotted filters, and permanently removes linked proposal/reservation ghost records when FMO Super Admin deletes an activity proposal.

## 2026-09-10 — Date-Based Pre-Plotted + Strict Sequential Routing

See `PATCH_NOTES_2026-09-10_DATE_ROUTING.md`. Pre-Plotted is now based on **same venue + shared calendar date regardless of time**. The FMO dashboard's top Pre-Plotted shortcut was removed. Approval visibility is strictly staged: Venue (when Pre-Plotted) -> FMO -> Adviser/Program Chair -> Dean/Principal -> SDAO -> Academic Director -> Executive Director. `Venue Slot — Final Confirmation` was renamed to **Venue Slot Approved**. A new one-time TiDB-safe migration recalculates existing active reservations under the date rule, and the temporary `pdo_sqlite` Docker addition was removed so the image stays aligned with Railway/TiDB through `pdo_mysql`.

## 2026-09-10 — FMO Users + Signature Routing cleanup
See `PATCH_NOTES_2026-09-10_FMO_USERS_ROUTING.md` for details. FMO First Review is auto-assigned, SDAO is school-wide, Housekeeping is managed only by FMO Super Admin, Requestor is removed from FMO assignable roles, and the `fmo` role is displayed as **FMO Staff**.
