# Security / Panel Revisions Implemented

## 3. Secure Procurement / ERP requisition export

The system now exposes a **read-only machine-to-machine ERP API** for requisition data:

- `GET /api/erp/requisitions` — normalized JSON headers + line items.
- `GET /api/erp/requisitions/export.csv` — CSV export for ERP import/connectors.
- Supported filters: `status`, `updated_since`, `updated_until`, `department_code`, `limit`.
- Authentication uses a dedicated `X-ERP-API-Key` rather than a staff/mobile password.
- Requests are rate-limited and can optionally be restricted with `ERP_ALLOWED_IPS`.
- Export access is written to the application log for audit purposes.
- The endpoints are read-only: the ERP cannot change NU Clark requisition records.

Production environment variables:

```text
ERP_API_KEY=<long-random-secret>
ERP_ALLOWED_IPS=<optional-comma-separated-fixed-IPs>
```

## 4. Short-lived JWT + server-side revocation

- API/mobile JWTs contain a unique `jti` identifier.
- Default token lifetime is now **30 minutes** (`JWT_TTL=30`) instead of 120 minutes.
- `/api/logout` places the JWT identifier in the server-side `revoked_jwt_tokens` blacklist.
- `/api/logout-all` sets a user-wide revocation timestamp, immediately invalidating all older mobile tokens for that account.
- JWT middleware rejects expired tokens, blacklisted `jti` values, and tokens issued before `jwt_revoked_before`.
- Old JWTs without a `jti` are rejected after this upgrade, forcing a fresh secure login.
- The mobile app stores the JWT in Android encrypted preferences backed by Android Keystore, calls server logout before deleting its local session, disables clear-text HTTP, and disables Android backup of authentication data.

Production environment variables:

```text
JWT_SECRET=<long-random-secret>
JWT_TTL=30
```

After deployment, run `php artisan migrate --force` so the blacklist/revocation schema exists.

## 15. Database encryption / stronger data protection

Security is applied in layers:

1. **Passwords** remain one-way bcrypt hashes; access vouchers remain SHA-256 hashes. These values are not reversibly encrypted.
2. **E-signatures are now application-layer encrypted at rest** using Laravel encryption (`APP_KEY`). Migration `2026_09_22_000028_encrypt_sensitive_database_fields.php` encrypts existing signature records. The `User` model transparently decrypts them only inside the application and hides the field from JSON serialization.
3. **MySQL/TiDB transport uses TLS** with CA verification support through `MYSQL_ATTR_SSL_CA` and `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=true`.
4. `SESSION_ENCRYPT=true` is recommended/enabled in the packaged environment example so serialized session data is also encrypted.

Production variables/checklist:

```text
APP_KEY=<keep-this-secret-and-backed-up; changing it loses ability to decrypt encrypted fields>
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=true
SESSION_ENCRYPT=true
```

> Important: application-layer encryption protects sensitive fields stored by this application. Infrastructure-level disk/backup encryption is controlled by the TiDB hosting provider and should also remain enabled in the database service configuration.
