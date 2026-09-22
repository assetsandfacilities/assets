# Patch Notes — 2026-09-13
## Campus-wide signatory routing + dashboard budget utilization

Two changes in this release. Neither one alters the order of the approval
chain, the statuses, the routes, the form field names stored in the database,
or any existing business rule. What changes is **who decides** a signatory, and
**what the department chart measures**.

---

## 1. Academic Director and Executive Director are now auto-assigned

### The problem

NU Clark has exactly one Academic Director and one Executive Director for the
whole campus, and neither of them belongs to a department. The seeded accounts,
however, were created with `department_id = 2` (Accounting Office). Because the
forms used the department-based approver picker, requestors were being asked to
"open a department, then choose a director" — choosing from a list of one, in a
department those people do not actually belong to.

### What changed

**New file — `app/Support/SignatoryResolver.php`**

A single place that answers "who is the Academic Director / Executive
Director". Every module asks this class instead of writing its own query, so
there is one definition of campus-wide routing.

```
SignatoryResolver::academicDirector()   -----> the one active academic_director
SignatoryResolver::executiveDirector()  -----> the one active executive
SignatoryResolver::isTaken($type, $ignoreId)
SignatoryResolver::isCampusWide($type)
SignatoryResolver::missingMessage($type)
```

**Routing, before and after**

```
ACTIVITY PROPOSAL (FMO)

  Requestor --> FMO Review --> Adviser --> Dean --> SDAO --> Academic Dir --> Exec Dir --> APPROVED
                |||auto|||      ^pick^     ^pick^   ^pick^   |||auto|||       |||auto|||
                                                             (was ^pick^)     (was ^pick^)

CHARGE SLIP (Asset Management)

  Requestor --> Asset Management --> College Dean --> Executive Director --> APPROVED
                |||auto|||            ^pick^          |||auto|||
                                                      (was ^pick^)
```

**Files touched**

- `app/Http/Controllers/Web/ActivityProposalController.php`
  - `create()` no longer builds `$academicDirectors` / `$executiveDirectors`
    lists; it passes a single `$academicDirector` and `$executiveDirector`.
  - `store()` no longer validates `academic_director_id` /
    `executive_director_id` from the request. Both are resolved server-side
    after validation. An id posted by a tampered form is ignored.
- `app/Http/Controllers/Web/RequisitionController.php`
  - `create()` passes `$executiveDirector` instead of `$executiveApprovers`.
  - `store()` drops the `executive_approver_id` rule and resolves it
    server-side.
- `resources/views/activity_proposals/create.blade.php`
  - Steps 5 and 6 now render an **Auto Assigned** badge and a read-only name
    panel, matching how step 1 (Facilities Management) already worked.
- `resources/views/requisitions/create.blade.php`
  - Card 3 (Approved By) is now auto-assigned and read-only.
- `resources/views/layouts/admin.blade.php`
  - New `.premium-locked-field` style for read-only signatory panels.

**SDAO was deliberately left as a dropdown** — there is more than one SDAO
account, so that choice still belongs to the requestor.

### Requested By is no longer editable

On the charge slip form, card 1 ("Requested By — Requestor") was a free-text
input pre-filled with the signed-in user's name, meaning anyone could type a
different name onto a document they were filing themselves.

It now displays the account name as a locked panel, and
`RequisitionController@store` overwrites `requested_by_name` with
`Auth::user()->name` regardless of what was submitted. The `requested_by_name`
column and the printed charge slip are unchanged.

### Guards so "exactly one" stays true

- `app/Http/Controllers/Web/AdminUserController.php` — assigning the Academic
  Director or Executive Director approver type clears the department, and
  activating a second holder of either role is refused with a clear message.
- `app/Http/Controllers/Web/AccessVoucherController.php` — a voucher for those
  roles never carries a department into the account created at signup.
- `database/seeders/DatabaseSeeder.php` — both accounts are seeded with
  `department_id => null`.

### Migration

`database/migrations/2026_09_13_000024_detach_campus_wide_directors_from_departments.php`

Clears `department_id` on existing `academic_director` and `executive` approver
accounts, and on any pending voucher for those roles. **Submitted proposals and
charge slips are untouched** — whoever is recorded on an existing document stays
recorded there.

### When no director is configured

Submitting is blocked with: *No active Executive Director is configured in the
system, so this cannot be submitted yet. Please contact the Super Admin.* The
form also shows a warning panel in place of the name. This is deliberate — a
document with no valid final approver has nowhere to go.

---

## 2. Dashboard: "Resource Allocation by Department" → "OPEX Budget Utilization by Department"

### The problem

The old chart plotted `allocations.max_quantity`, which is a policy ceiling that
**nothing on the web submission path enforces**. The real gate in
`RequisitionController@store` is the department OPEX peso budget
(`departments.opex_limit` minus committed charge slips). Sitting beside charts of
live data, a ceiling reads like consumption. It also only showed departments that
had an allocation row, so two of the four departments were invisible.

### What changed

- `app/Http/Controllers/Web/DashboardController.php` — `$allocationByDepartment`
  replaced with `$budgetByDepartment`, built from every department with
  `opex_limit`, `opexConsumed()`, and remaining. Over-budget departments clamp
  to zero remaining rather than drawing a negative bar.
- `resources/views/dashboard/index.blade.php` — stacked bar chart (Used /
  Remaining) with peso tooltips showing the percentage consumed, plus a caption
  explaining what the number means.
- `resources/views/layouts/admin.blade.php` — new `.chart-note` style.

The Allocations module itself is untouched and still governs the per-request
quantity cap in the API path.

### Note on demo data

The current database has almost no requisition history, so the Used segment will
be near zero. Seed a few charge slips across departments before the demo, or the
chart will be technically correct and visually empty.

---

## How to apply

```
php artisan migrate
php artisan config:clear
php artisan view:clear
```

## What to test

1. Charge slip form — "Requested By" shows your name, locked. "Approved By"
   shows the Executive Director with an Auto Assigned badge. Submit, then open
   the record: `requested_by_name` and `executive_approver_id` are correct.
2. Activity proposal form — steps 5 and 6 are auto-assigned; SDAO still a
   dropdown. Submit and confirm the approval trail names the right people.
3. Account Management — set a second user as Executive Director and activate;
   expect a refusal. Set one as Academic Director; the department field is
   ignored.
4. Dashboard — four departments appear on the utilization chart.
5. Existing pending documents still open, sign, and print as before.

---

## 3. Charge slip form cleanup

### Budget moved into the form header

"Department OPEX Availability" used to sit in the middle of section 01, below
the fields. It is now in the form header beside the Digital Routing badge, so
the remaining balance is visible before any item is typed rather than after.

- `resources/views/requisitions/create.blade.php` — the budget card moved into
  a new `.premium-form-intro-aside` cluster in the header.
- `resources/views/layouts/admin.blade.php` — new `.premium-form-intro-aside`
  and `.premium-budget-card-inline` styles.

The element ids (`deptBudgetRemaining`, `deptBudgetLimit`) are unchanged, so the
existing live-recalculation script and the over-budget warning still work with no
edits. On phones the Digital Routing badge is still hidden, but the budget card
now spans the full width instead of disappearing with it — it is information,
not decoration.

The "Budget Check" pill was dropped; in the header it duplicated the label
directly above it.

### "Charge To (Per Budget Item)" removed

The field is gone from the form and from everything that read it.

- `resources/views/requisitions/create.blade.php` — field removed; Purpose now
  spans the full row.
- `app/Http/Controllers/Web/RequisitionController.php` — validation rule and
  create payload entry removed.
- `app/Models/Requisition.php` — removed from `$fillable`, so nothing can write
  it going forward.
- `resources/views/requisitions/show.blade.php` — "Charge To" row removed.
- `resources/views/requisitions/receipt.blade.php` — removed from the printed
  slip; CSF No. now spans that row.
- `resources/views/reports/index.blade.php` — the "Used For" column now reads
  from `purpose` alone.
- `database/seeders/DatabaseSeeder.php` — no longer seeds a value.

**The database column stays.** It is already nullable, and dropping it would
destroy the values on charge slips that were filed while the field existed. No
migration is needed; the column simply stops being written. If you later decide
those old values are not worth keeping, that can be a separate migration.
