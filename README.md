# Equipment & Event Rental Management

The application is in **`rental-manager/`**. It uses Laravel 12, PHP 8.2+, MySQL 8 and lightweight Blade/CSS/JavaScript. No frontend build step or cloud service is required.

- [Project README](rental-manager/README.md)
- [Laragon installation](rental-manager/docs/INSTALLATION.md)
- [User manual](rental-manager/docs/USER-MANUAL.md)
- [Architecture and development](rental-manager/docs/ARCHITECTURE.md)
- [Security and operations](rental-manager/docs/SECURITY.md)
- [API reference](rental-manager/docs/API.md)
- [MVP scope and acceptance](rental-manager/docs/MVP.md)

For a fresh clone, follow the [Laragon installation guide](rental-manager/docs/INSTALLATION.md) to install Composer dependencies, configure `.env`, create your MySQL database, run migrations and create an administrator. Optional sample data generates a unique temporary password when seeded.

The original development installation uses database `gather_rentals` with fictional demo records. Its local login information is in the ignored `rental-manager/.local/DEMO-LOGIN.txt` file. Credentials, databases, uploads and runtime data are excluded from this repository. Administrators must choose a new password at first sign-in.

Start Laragon's MySQL, then run `start-local.cmd` or run `php artisan serve --host=127.0.0.1 --port=8000` from `rental-manager/`. Open <http://127.0.0.1:8000>.
