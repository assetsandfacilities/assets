# Patch Notes — 2026-09-12

## Schedule fields reworked (School Facilities Reservation, Section 02)

### The bug

`ActivityProposalController::store()` never stored the date the requestor picked.
It stored a weekday *name* and rebuilt the date at submission time:

```php
$weekStart = Carbon::now('Asia/Manila')->startOfWeek(Carbon::MONDAY);
$startDate = $weekStart->copy()->addDays($dayIndex['Monday']);
```

`startOfWeek(MONDAY)` always returns the Monday of the week **currently in
progress**. On Saturday 09/12/2026 that Monday is 09/07/2026, so an advance
booking for Monday 09/14/2026 was written to the database as 09/07/2026 — a
date in the past. `create.blade.php` line 5 built the same map for the preview
panel, which is why the summary card agreed with the wrong date instead of
catching it.

The only escape hatch was `if ($endDate->lt($startDate)) $endDate->addWeek();`,
which pushed the **end** forward a week while leaving the start behind. That
made some multi-day requests span a range that had never been requested.

### The fix

The form now collects two real datetimes and the server stores exactly what was
submitted.

**`resources/views/activity_proposals/create.blade.php`**

- Removed the `$days` / `$weekStart` / `$dayDates` block.
- `Start Day` + `End Day` dropdowns and the two separate `Start Time` / `End Time`
  inputs are replaced by two `<input type="datetime-local">` fields:
  `activity_start_at` and `activity_end_at`. Date and time now live in one
  control per side, as requested.
- `min` on the start field blocks past dates in the picker itself.
- `min` on the end field is re-pointed to the start value on every change, so an
  end earlier than the start cannot be selected.
- Setting a start auto-fills the end at start + 1 hour. The common single-day
  booking is therefore two clicks, and multi-day is still available by editing
  the end date.
- The "Selected schedule" panel now derives the weekday, the time range, and the
  duration from the chosen values, and no longer claims dates are generated from
  the current week. Inline `@error` output was added to both fields.

**`app/Http/Controllers/Web/ActivityProposalController.php`**

- Validation replaced:
  ```php
  'activity_start_at' => ['required','date','after_or_equal:<now + lead days>'],
  'activity_end_at'   => ['required','date','after:activity_start_at'],
  ```
- The `$dayIndex` / `$weekStart` / `addWeek()` reconstruction is gone. Values are
  parsed with `Carbon::parse($value, 'Asia/Manila')`.
- `activity_days` is now derived from the stored datetimes
  (`Monday, September 14, 2026`), so the printed proposal can no longer disagree
  with the reservation record.

**`config/fmo.php` (new) and `.env`**

- `FMO_RESERVATION_LEAD_DAYS` sets the minimum advance notice in days. Default
  `0` (same-day booking allowed, past dates still blocked). One value drives both
  the picker's `min` attribute and the server-side rule, so client and server
  cannot drift apart.

### Not changed

Pre-plotting remains **date based** — an existing active request on the same
venue on any overlapping calendar date still marks the later request as
pre-plotted, even at different clock times. That behaviour is documented in the
controller as deliberate, so it was left alone. Now that real datetimes are
stored, switching to true time-based overlap is a one-line change if FMO wants
it:

```php
->where('start_at', '<', $endAt)->where('end_at', '>', $startAt)
```

### Database

No migration needed. `activity_proposals.start_at` / `end_at` and
`facility_reservations.start_at` / `end_at` already exist as `dateTime` columns.
Rows created before this patch keep whatever dates the old logic wrote; review
any pending request whose `start_at` is in the past.

---

## Logout error page fixed

### The bug

Reported as an occasional 404 on the Logout button. It was a **419 Page Expired**.

`SESSION_LIFETIME` is 120 minutes. A tab left open past that point keeps the CSRF
token rendered in its HTML while the session behind it is already gone. The next
POST from that page fails `VerifyCsrfToken` with a `TokenMismatchException`.
Because `withExceptions()` in `bootstrap/app.php` was empty and
`resources/views/errors/` contained only `403` and `500`, the failure rendered as
a bare framework error page. Logout is the control most likely to be pressed
after a tab sits idle, which is why it surfaced there and only intermittently.

### The fix

**`bootstrap/app.php`** — added a render handler for `TokenMismatchException`:

- On the logout route: redirect to login with a "You have been signed out"
  notice. Logout is idempotent; an expired session means the user is already out.
- On any other stale POST: redirect to login with an explicit "session expired"
  message instead of a dead end.
- JSON requests get a 419 with the same message.

**`routes/web.php`** — `POST /logout` moved outside the `auth` middleware group.
An already-expired session no longer routes the sign-out through the auth
redirect before finishing.

**`resources/views/errors/419.blade.php`** (new) — styled "Session Expired" page
matching the existing 403 layout, with a link back to sign-in.

**`resources/views/errors/404.blade.php`** (new) — the app had no 404 page at
all, so any mistyped URL rendered the raw framework page. Now matches the rest.

---

## Audit notes

Checked while looking for related issues:

- **Route names** — every `route('...')` reference across views and controllers
  resolves to a defined route or resource. No broken links.
- **Timezone** — `config/app.php` is `Asia/Manila` and the two remaining
  `startOfWeek` call sites were both in the schedule code removed above. No other
  week-relative date derivation exists in the codebase.
- **`Permissions-Policy: geolocation=()`** in `SecurityHeaders` blocks the
  browser geolocation API. The scans table has GPS columns but no view currently
  calls `navigator.geolocation`, so nothing is broken today. If GPS capture is
  ever wired into the scan UI, that header must be relaxed first or the call will
  fail silently.
- **`APP_DEBUG=true`** in `.env`. Correct for development; set to `false` before
  the defense demo or any hosted deployment, otherwise stack traces with file
  paths are shown to anyone who triggers an error.

## Testing

1. `php artisan config:clear` (a new config file was added).
2. Open the reservation form on a Saturday. Pick the coming Monday. Confirm the
   summary panel and the saved reservation both read that Monday's date.
3. Try selecting yesterday — the picker should refuse it. Submit a crafted past
   date directly and confirm the server rejects it.
4. Set an end earlier than the start and confirm both the picker and the server
   block it.
5. Create a multi-day request and confirm start and end match what was chosen.
6. Sign in, leave the tab idle past `SESSION_LIFETIME` (or clear the session
   file), then press Logout. Expect the login page with a sign-out notice, not an
   error page.
