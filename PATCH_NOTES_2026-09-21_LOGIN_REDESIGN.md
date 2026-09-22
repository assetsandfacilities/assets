# Login Page UI/UX Redesign — 2026-09-21

## Scope
UI/UX-only redesign of the web login screen. Existing authentication routes, credentials, login controller behavior, password-reset route and registration flow are unchanged.

## Changes
- Replaced the unbalanced narrow/empty login layout with a balanced desktop split-screen design.
- Added a deep NU-style navy hero area and retained the NU red accent for the primary Sign In action.
- Added platform branding and concise feature summaries for Asset & Inventory, CAPEX/OPEX Requests and Facility Reservations.
- Centered the authentication card in the right panel and increased usable form width.
- Added modern input icons, improved spacing, focus states and a clearer Forgot Password link.
- Restyled the password visibility control while retaining the existing shared password-toggle script.
- Added a clearer Create Account action with guidance about verified email/access voucher registration.
- Added responsive behavior: the large hero panel is removed on tablet/mobile and replaced by a compact NU Clark header.
- Added subtle decorative background elements without adding image dependencies.

## Files changed
- `resources/views/auth/login.blade.php`
- `public/css/nuclark-auth.css`

## Backend impact
None. No route, controller, database, authentication, or authorization logic was changed.
