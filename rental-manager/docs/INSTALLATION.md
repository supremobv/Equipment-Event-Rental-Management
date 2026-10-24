# Installation with Laragon

## Requirements

- PHP 8.2 or newer with PDO MySQL, mbstring, OpenSSL, fileinfo, DOM, XML, ctype, tokenizer and GD. The tested PHP version is 8.2.28.
- Composer 2; MySQL 8; Laragon Apache or Nginx optional when using `artisan serve`.
- Node/npm are not required: browser assets in `public/` are served directly, with no CDN dependency.

## Existing setup on this computer

The application lives in `rental-manager/`. `.env` points to MySQL at `127.0.0.1:3306`, database `gather_rentals`, using Laragon's local root account with a blank password. This is a local development setting. Migrations, default settings and fictional demonstration records have already been installed. Do not reseed to start the server.

1. Start MySQL in Laragon.
2. Open `start-local.cmd` in the parent project folder, or open Terminal in `rental-manager/` and run `php artisan serve --host=127.0.0.1 --port=8000`.
3. Visit <http://127.0.0.1:8000>.
4. Read the temporary login in `.local/DEMO-LOGIN.txt`. Sign in and choose a new password. Share no demo credentials outside local development.
5. Use **Business settings** to set your branding, contact details, tax and terms. Demo currency is PHP.

## Install a fresh business database

Do not use destructive reset commands against an existing business database. Keep demo and real records in separate databases.

1. Copy this project to your chosen folder. Install dependencies:

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   ```

2. Create a dedicated MySQL database in HeidiSQL, phpMyAdmin or the MySQL console:

   ```sql
   CREATE DATABASE your_rental_business CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Set `.env`:

   ```dotenv
   APP_NAME="Your Rental Business"
   APP_ENV=local
   APP_DEBUG=false
   APP_URL=http://127.0.0.1:8000
   APP_TIMEZONE=Asia/Singapore
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_rental_business
   DB_USERNAME=root
   DB_PASSWORD=
   SESSION_DRIVER=database
   SESSION_LIFETIME=60
   ```

4. Prepare database, public uploads and your administrator:

   ```powershell
   php artisan config:clear
   php artisan migrate --seed
   php artisan storage:link
   php artisan rental:admin
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   `rental:admin` prompts for a name, unique email and hidden temporary password. Use at least 12 characters, uppercase/lowercase, a number and a symbol. First sign-in requires changing it.

5. For fictional sample data **instead of creating an administrator**, run `php artisan db:seed --class=DemoSeeder` on the new empty database. It prints a randomly generated temporary password. It refuses to add samples when business data or users already exist. The default seeder only installs business settings and categories; it never creates a shared default password.

## Laragon virtual host

Point Apache/Nginx's document root to **`rental-manager/public`**, never the project root. Choose a local hostname such as `gather.test`, set `APP_URL=http://gather.test`, then run `php artisan config:clear`. Apache needs `mod_rewrite` and `AllowOverride All` for `public/.htaccess`. Nginx needs `try_files $uri $uri/ /index.php?$query_string` and PHP FastCGI. Restart the web server after changing its configuration.

If a link cannot be created on Windows, enable Windows Developer Mode or run the link command in an elevated terminal. If Laragon serves under a different hostname, use only that hostname for the entire session; mixing `localhost` and `127.0.0.1` breaks session cookies.

## Common issues

| Symptom | Resolution |
| --- | --- |
| Connection refused | Start MySQL; verify `DB_HOST` and `DB_PORT`. |
| Access denied | Verify database user/password and database grants. |
| Missing tables | Run `php artisan migrate --seed` on the intended database. |
| Login redirects repeatedly | Use one hostname; ensure `storage/` and `bootstrap/cache/` are writable; verify database sessions table. |
| 419 session expired | Reopen the form, sign in again if needed; inspect the record before repeating a payment. |
| Logo not displaying | Run `php artisan storage:link`; verify public storage URL and file permissions. |
| Address already in use | Choose `--port=8001` and change `APP_URL` accordingly. |
| Changes not showing | Run `php artisan optimize:clear`; restart the server. |
| Forgotten password | Run `php artisan rental:reset-password your@email.example` from the trusted server console. |

## Verification

```powershell
php artisan test --compact
composer audit
php artisan view:cache
```

Tests use in-memory SQLite by default. To test MySQL, create a **separate database ending in `_test`** and set `DB_CONNECTION=mysql`, `DB_DATABASE=gather_rentals_test`, with the appropriate connection variables in the test process before running. `tests/TestCase.php` refuses any other database name. Never point test execution at real business data. The test suite creates and resets its own schema in the dedicated test database.
