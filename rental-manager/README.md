# Gather Rentals · Equipment & Event Rental Management

A local-first rental management MVP built with Laravel 12, PHP 8.2+, MySQL 8, Blade and lightweight JavaScript. No npm build, cloud account or external CDN is required.

## Fresh installation

Follow [the installation guide](docs/INSTALLATION.md): run `composer install`, copy `.env.example` to `.env`, generate an application key, configure a new MySQL database, run `php artisan migrate --seed`, create the public storage link and run `php artisan rental:admin`. Optional demo seeding prints a uniquely generated temporary password. No credentials or database contents are included in Git.

## Run the original local installation

Start MySQL in Laragon, then run `php artisan serve --host=127.0.0.1 --port=8000` from this directory. Open http://127.0.0.1:8000. The dedicated `gather_rentals` MySQL database already contains fictional samples. Temporary administrator credentials are in the ignored `.local/DEMO-LOGIN.txt` file. Change the password at first sign-in.

## Included

Custom business branding; staff and server-enforced RBAC; equipment/categories; customers; quotes; conflict-checked reservations; pickup/delivery information; check-out and whole-booking returns; maintenance holds; payments/deposits/refunds; invoices and browser PDF printing; calendar; dashboard; reports/CSV; audit activity; documented session-authenticated availability JSON endpoint.

## Documentation

- [Laragon installation and fresh setup](docs/INSTALLATION.md)
- [User manual and role matrix](docs/USER-MANUAL.md)
- [Architecture, customization and tests](docs/ARCHITECTURE.md)
- [Security and operations](docs/SECURITY.md)
- [API reference](docs/API.md) and [OpenAPI](docs/openapi.yaml)
- [MVP scope and boundaries](docs/MVP.md)
- [Verification record](docs/VERIFICATION.md)

## Verify

```powershell
php artisan test --compact
composer audit
php artisan view:cache
```

Tests use in-memory SQLite unless explicitly configured for a separate MySQL database ending in `_test`. The guard refuses business database names. Before production, configure HTTPS, a dedicated database user, backups, monitoring and an independent security review. No system is guaranteed immune to every attack.
