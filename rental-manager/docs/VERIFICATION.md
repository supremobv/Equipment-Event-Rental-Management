# Local verification record

Verified on **25 October 2026**, using the Asia/Singapore business timezone.

## Automated checks

| Check | Result |
| --- | --- |
| SQLite isolated suite | 22 passed, 115 assertions; 1 MySQL-only concurrency test skipped |
| Real MySQL isolated suite | 23 passed, 121 assertions |
| Two-process stock contention | Exactly one of two confirmations for the same last unit succeeds; the other receives a validation rejection |
| Blade compilation | `php artisan view:cache` successful |
| Production route compilation | `php artisan route:cache` successful; cache cleared for local editing |
| Composer manifest/lock | `composer validate --no-check-publish` successful |
| Dependency advisory scan | No known security vulnerability advisories reported by `composer audit` |
| Formatting | PHP source formatted with Laravel Pint |

The MySQL suite uses `gather_rentals_test`, separate from the sample `gather_rentals` business database. The test guard rejects normal business database names. The test schema is disposable. No production load benchmark or independent penetration test is claimed.

## HTTP and browser QA

- Local login returns HTTP 200 with frame protection, nosniff and a restrictive same-origin Content Security Policy.
- A real POST to login without CSRF token returns HTTP 419. This is checked outside Laravel's test-mode CSRF bypass.
- Signed in using a dedicated local QA fixture account and verified the dashboard visually.
- Selected a customer and equipment, added a second equipment row, observed the updated estimate and saved a quote through the browser. The saved two-line quotation totaled PHP 630.00 as expected.
- Viewed the paginated finance report with net receipts, all-time balances, deposit holdings and transaction rows.
- Automated rendering checks cover all 22 authenticated screen routes plus the availability JSON endpoint; automated tests also cover replaceable raster branding and rejected executable/SVG uploads.
- Saved a dashboard screenshot in the local ignored `.local/dashboard-preview.png` file for this installation.
- Cancelled the browser-created QA quotation, signed out the QA fixture, and disabled its account. Its audit history remains intact.

## Business database and handoff

The dedicated MySQL database contains fictional demonstration equipment, customers, historical receipts, reservations and a maintenance hold. The demo administrator's generated temporary password is in the local ignored `.local/DEMO-LOGIN.txt`; the administrator must replace it at first sign-in. The QA account is inactive. Real business use should start with a separate fresh database as described in `INSTALLATION.md`.

Start MySQL in Laragon, then run the parent folder's `start-local.cmd` or `composer dev` inside the app. Open <http://127.0.0.1:8000>. No npm build is needed. The verification server is stopped after QA; the startup script starts it again.
