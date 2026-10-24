# MVP scope and acceptance

This is a working, local-first, single-business equipment/event rental MVP with a real MySQL database. It is not a claim to cover every possible rental business model or to be immune to every attack.

## Delivered

| Area | Included |
| --- | --- |
| Business identity | Replaceable raster logo; name, tagline, contacts, address, currency before first booking, tax and terms |
| Access | Personal staff accounts; admin/manager/operations/finance/viewer; active flag; forced password change; CLI recovery; server RBAC |
| Equipment | Categories, SKU, quantity, daily rate, replacement value, location, description, archive, search/filter |
| Customers | Contacts, company/address, internal notes, search and booking history |
| Quotes | Customer/event/venue, inclusive dates, multi-item rows, live estimate, discount, delivery fee, tax, deposit |
| Reservation safety | Availability by dates; peak overlap calculation; atomic MySQL locks; maintenance/out-stock holds |
| Fulfillment | Pickup/delivery designation; deposit-gated check-out; whole-booking return and inspection; manual late/damage fees |
| Booking lifecycle | Quote, confirmation, out, returned, cancellation; immutable confirmed financial lines |
| Finance | Rental/deposit receipts; separate refund kinds; limits; idempotent form retries; external-payment references |
| Documents | Printable quotes/invoices and payment ledger; browser Print-to-PDF |
| Maintenance | Quantity holds, reason, notes, target completion, release and history |
| Planning | Monthly calendar, dashboard, overdue links, active booking views |
| Reporting | Net rental cash receipts, outstanding balances, deposit holdings, date-filtered ledger, streamed CSV |
| Security | Authentication/session controls, login throttling, input validation, CSRF, escaping, CSP, upload restrictions and audit events |
| Developer handoff | Migrations, seeders, optional samples, safe test database guard, business tests, availability JSON/OpenAPI, setup/manual/security docs |

## Explicit MVP boundaries

No individual asset serial/barcode tracking, partial returns, lost-unit write-offs, hourly pricing, packages/bundles, multi-warehouse transfers, purchase orders, suppliers, inventory consumables, e-signature, online customer booking portal, automated email/SMS, payment gateway, full accounting, automated backups, multi-tenancy, MFA or native mobile application. Event management here means customer/event bookings, venue/fulfillment and rental scheduling; it does not include ticketing, guest lists or catering/project task management.

Rental rates are per calendar day, inclusive of start/end. Every current checkout holds stock until returned, even against future availability. Maintenance safety checks are conservative. Cancellation waives charges and requires separate refunds. Whole returns require every item to be accounted for. These choices are deliberate and described in the manual.

## Local acceptance record

- Dependencies installed; Laragon PHP 8.2.28 and MySQL connection verified.
- MySQL business schema migrated and fictional sample data loaded in `gather_rentals`.
- Automated tests run with SQLite and a separate MySQL `gather_rentals_test` database. See `VERIFICATION.md` for final counts and browser checks.
- Browser sign-in, dashboard and dynamic quote builder checked locally. No public deployment requested or performed.
- Composer advisory scan reports no known vulnerabilities in the locked packages at verification time.
- Production operations and security hardening remain deployment responsibilities, including TLS, non-root database credentials, backups, monitoring and independent review.
