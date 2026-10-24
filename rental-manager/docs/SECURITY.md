# Security and operations

## Implemented controls

- Laravel hashed passwords, no public registration or shared default password, hidden CLI password entry, mandatory first-login/reset password change and a 12-character mixed-case/number/symbol rule.
- Session ID regeneration at login/password change, invalidation and CSRF regeneration at logout, database-backed sessions, 60-minute idle expiry and revocation on staff updates/password recovery. Password changes revoke other sessions.
- Login throttling: 5 failed attempts per normalized email/IP per minute, plus 15 requests per IP per minute. Password changes: 6 per minute; availability endpoint: 60 per minute.
- Active-account checks and server-side capability guards. State-changing booking actions independently verify the relevant role, so changing a URL cannot grant access.
- Explicit field validation and allowlists; Eloquent/query-builder bound values; Blade output escaping; no raw customer-authored HTML. Money, stock and state transitions are calculated on the server.
- CSRF protection on web writes. Same-origin cookie policy; no wildcard cross-origin API access.
- Restrictive same-origin CSP, `frame-ancestors 'none'`, `X-Frame-Options: DENY`, nosniff, same-origin referrer policy, disabled camera/microphone/geolocation. HTTPS responses add HSTS. Static JS/CSS are separate files; no unsafe inline script policy is needed.
- Business uploads accept raster PNG/JPEG/WebP, validate content MIME and pixel dimensions, limit file size and use random storage names. User SVG/HTML and executables are rejected. The trusted bundled sample SVG is code-owned.
- Transactions, current item reads and sorted equipment row locks protect reservations. A two-process MySQL regression verifies contending confirmations cannot double-book the last unit. Booking row locks, payment UUID uniqueness, caps on received/refunded money and immutable confirmed quotes protect financial consistency.
- Last-active-admin safeguards; no hard-delete business UI; audit records for operational changes; payment CSV neutralizes formula-like text.
- `.env`, database contents, logs, vendor dependencies and local credentials are excluded from source control. `APP_DEBUG=false` in the provided configuration; friendly error templates omit secrets.

These controls reduce common risks; no implementation can guarantee protection against every attack. This MVP has not received an independent penetration test or production load/concurrency stress test. Composer audit found no known advisories in the installed dependency set at local verification time; rerun it regularly.

## Production preparation

1. Deploy on a maintained PHP 8.2+ host with HTTPS. Set `APP_ENV=production`, `APP_DEBUG=false`, a correct HTTPS `APP_URL` and `SESSION_SECURE_COOKIE=true`. Use supported proxy trust configuration only for your actual reverse proxy; do not trust arbitrary forwarded headers.
2. Point the web root only to `public/`. Disable directory listing and script execution in uploaded-file directories. Keep `.env`, source, backups and logs inaccessible through the web server. Use a restrictive upload directory configuration with Apache/Nginx and correct MIME responses.
3. Use a dedicated MySQL account with only application-database grants. Use a separate privileged migration credential when possible. Do not deploy with Laragon's blank root password, demo records, test credentials or `.local/` contents.
4. Generate a unique `APP_KEY` for a new installation and preserve it securely for existing installations. Limit write permissions to `storage/` and `bootstrap/cache/`; deployment code should not be writable by the web process. Never regenerate a key casually on a live installation.
5. Install locked dependencies with `composer install --no-dev --optimize-autoloader`; run `php artisan migrate --force`, `php artisan storage:link`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`. The closure console commands do not affect route caching. Restart PHP workers after deployment.
6. Review roles, use personal staff accounts, deactivate departed staff promptly and secure operator access to the host, database and backups. Add MFA/SSO, monitoring/WAF, dependency scanning and an independent security review according to deployment risk.
7. Configure TLS, firewall database access, monitor application/web server logs, schedule backups and test restoring them. Define a retention policy for customer details, IP audit data, old sessions, failed jobs and backups. The MVP does not automatically purge personal data or certify privacy/regulatory compliance.

## Backup and recovery

Back up the MySQL business database, `storage/app/public/branding`, and the application encryption key/configuration in protected storage. Avoid committing or placing backups under `public/`.

Example with the MySQL client (password is prompted; choose a protected path outside the web root):

```powershell
mysqldump --host=127.0.0.1 --user=your_backup_user --password --single-transaction --routines --result-file=C:\protected-backups\rental-backup.sql gather_rentals
```

For restore, use HeidiSQL/MySQL's import facility into a **new** database, copy uploads, preserve the proper `APP_KEY`, set `.env` to that database, run `php artisan config:clear`, and verify record counts, booking balances, invoices and logos before switching service. Do a restore drill. The app does not include a one-click backup/restore UI.

## Recovery commands

`php artisan rental:admin` creates an administrator from the trusted console. `php artisan rental:reset-password email@example.com` sets a temporary password and revokes database sessions. Console access is equivalent to administrative control: protect it. There is no email-based recovery service configured and no email is sent automatically.

## Known boundaries

- This is one business/workspace with pooled inventory. Operational records and booking totals are readable by every active role; viewers are trusted staff. There is no customer login, tenant boundary or field-level financial redaction.
- No MFA, automated breach detection, malware scanner, encrypted PII columns, tamper-proof external audit store, public token API or external payment processing is included.
- Cookies on HTTP localhost cannot use the production Secure flag. Use HTTPS plus `SESSION_SECURE_COOKIE=true` outside local testing.
- Audit records capture successful key actions, not full before/after field diffs, all failed-login identities or every read. Database/host administrators can alter them.
- Uploaded raster logos are public business assets. Do not upload confidential documents. Replaced logo files are retained in storage; apply your own cleanup policy after backups.
- Cancellation/refunds are ledger operations and do not invoke financial providers. Reports are operational and do not assert legal/tax compliance. Adapt terms and accounting practices to your business.
