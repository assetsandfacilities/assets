# Panel Revisions #3, #4, and #15 — 2026-09-22

Only the three requested panel revisions were implemented in this patch.

## #3 Procurement / ERP integration
- Added secure, read-only ERP requisition JSON endpoint.
- Added ERP-compatible CSV requisition export.
- Added dedicated API-key authentication, optional IP allow-list, rate limiting, filtering, and export audit logging.

## #4 Mobile JWT security
- Reduced JWT default lifetime to 30 minutes.
- Added unique JWT `jti` values.
- Added server-side token blacklist and user-wide revocation timestamp.
- `/api/logout` now revokes the server token instead of relying only on local deletion.
- Added `/api/logout-all` for immediate invalidation of all older mobile sessions.
- Mobile token storage upgraded to Android Keystore-backed encrypted preferences.
- Mobile logout now calls the server first, then clears local credentials.
- Android backup and clear-text HTTP were disabled.

## #15 Database security
- Existing and future e-signatures are encrypted at rest with Laravel application encryption.
- E-signature data is hidden from JSON/API serialization.
- MySQL/TiDB TLS certificate verification is explicitly configurable.
- Session encryption is enabled in the packaged/example environment.

## Deployment requirement
Run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Set `ERP_API_KEY`, keep `JWT_SECRET` and `APP_KEY` secret, and confirm the TiDB TLS CA environment variable on the deployment host.
