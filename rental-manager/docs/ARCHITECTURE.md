# Architecture and development

## Stack and folders

Laravel 12.70.1 / PHP 8.2+ / MySQL 8 / Blade templates / local CSS and JavaScript. Laravel's session authentication, validation, query builder and database transactions provide the foundation. There is no React bundle, SPA hydration, npm installation, external font, CDN or third-party logo dependency.

```text
app/Http/Controllers/   Authentication, workspace, catalog, bookings, administration
app/Http/Middleware/    Active-account/RBAC gate and security headers
app/Models/             Relational business models
app/Services/           RentalService: booking, availability and financial rules
app/Support/            Money conversions and audit writer
database/migrations/    Versioned MySQL-compatible schema
database/seeders/       Safe default settings; optional fictional sample dataset
resources/views/        Blade layouts, components and feature views
public/                 Web root; static CSS, JS and original SVG assets
routes/web.php          Session-authenticated routes, including availability JSON
routes/console.php      Trusted admin creation and password recovery commands
tests/Feature/          Business workflows, authorization and rendering tests
docs/                   Installation, manual, API, security and scope
```

## Data model

- Users have a fixed role, active flag and first-login password-change flag. Roles map to capabilities in `User::allows()`; server middleware and action gates enforce them, and views hide unavailable actions.
- Equipment belongs to categories. Owned stock is pooled; maintenance removes quantities from availability.
- Customers have many bookings. Each booking has immutable-on-confirmation equipment line snapshots and an append-only payment ledger.
- Booking items snapshot the name, rate, days, quantity and extended total. Dates and tax calculations are server controlled.
- Business settings are a single row. The default seeder creates it. Currency changes are rejected after any booking exists.
- Foreign keys restrict deletion of historical business records; no destructive CRUD interface is exposed. Archiving and account deactivation preserve history.
- Audit events are committed in the same transaction as key business mutations. Audit does not store passwords, full forms or uploaded files.

## Workflow and locking

`quote → confirmed → out → returned`, with `quote/confirmed → cancelled`. Only quote is editable. Return is whole-booking and one-time; cancellation is not allowed after checkout.

`RentalService` controls pricing, transitions and payments. It locks the booking and relevant equipment rows in sorted order during confirmation/checkout/return. Inventory and maintenance mutations lock the same equipment rows. This serializes stock promises in MySQL. The date sweep measures peak reservations over inclusive intervals, instead of adding non-overlapping rentals. All out stock remains held regardless of future query dates until returned; open maintenance holds also have no automatic end. Transactions retry deadlocks up to three times. Transition item reads use current locking reads before equipment acquisition; the first consistent-read snapshot opens only after equipment locks are held, preventing stale availability after a wait under MySQL REPEATABLE READ.

Money is stored as integer cents in unsigned BIGINT columns; rate/amount form values accept at most two decimals. Quote line rates come from the catalog, not the browser. Tax uses integer basis points and rounds half-up once on the taxable total. Damage/late fees are entered as final tax-inclusive amounts and added once at return. The deposit is a separate liability-style ledger, not part of the rental price.

Payment submissions use a unique UUID request key and serialize on the booking row. Repeating the same key on that booking returns without a duplicate ledger row. Business rules cap collections and refunds. A fresh form represents a new transaction, so reconciliation and references still matter.

## Customization

- Branding, contact info, tax and printed terms: **Business settings**.
- Inventory categories, equipment, pricing and locations: **Equipment**.
- Roles/capabilities: `app/Models/User.php`; keep server guards and UI permissions aligned.
- UI styling: `public/app.css`; interactive item builder: `public/app.js`.
- Business workflow: `app/Services/RentalService.php`; protect changes with tests.
- Business timezone: `APP_TIMEZONE` in `.env`; default Asia/Singapore. Store/use dates consistently when changing timezone.

## Testing

`php artisan test --compact` runs isolated SQLite tests by default. `tests/TestCase.php` fails before migration unless the database is `:memory:` or its name ends `_test`. Real MySQL verification uses a separate `gather_rentals_test` database. Database tests reset their schema; never run them on business data. A dedicated two-process MySQL regression test deliberately makes competing confirmations wait for the same equipment lock and asserts that exactly one succeeds.

The suite covers the full rental/payment/deposit/refund cycle, conflicting reservations, disjoint dates, inclusive boundaries, missing deposits, payment limits/idempotency, maintenance, inventory reductions, role escalation attempts, inactive users, forced password changes, last-admin protection, every authenticated view, JSON availability, input escaping and login limits. Laravel disables CSRF verification inside tests; verify the real HTTP 419 response separately, as done during local QA.

The default PHP development server is single-process. A real two-process MySQL lock-contention regression is included; this is not a production stress/load test. Use Apache/Nginx and representative load testing before a high-volume deployment.

## Performance

Operational lists use pagination, relationships are eager loaded for booking/customer lists, and static assets use no external network requests. Indices cover rental dates, statuses, foreign keys and audit dates. Availability batches reservation and maintenance queries across equipment. Finance reports paginate transactions and open balances, calculate totals in SQL, and stream CSV exports in chunks of 200 records. Branding is resolved once per request. The calendar and availability endpoint still return all relevant records for the selected range/catalog; benchmark and add range limits, catalog pagination or caching with correct invalidation for very large businesses. No production-scale load benchmark is claimed.

## Framework references

- [Laravel authentication](https://laravel.com/docs/12.x/authentication)
- [Laravel middleware and CSRF](https://laravel.com/docs/12.x/middleware)
- [Laravel database transactions](https://laravel.com/docs/12.x/database)

These references informed the framework integration; the application behavior and limitations are documented here and in the manual.
