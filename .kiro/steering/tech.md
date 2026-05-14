# Tech Stack

## Core

- **PHP 8.3+**
- **Laravel 13** (framework)
- **Filament 5.6** (admin panel / CRUD UI)
- **MySQL / MariaDB** (via Laragon environment)

## Frontend

- **Tailwind CSS 4** (via Vite plugin)
- **Vite 8** (asset bundling)
- **Blade** (templating)

## Notable Packages

- `jfcherng/php-diff` — Diff engine for version comparison
- `spatie/pdf-to-text` — PDF text extraction (requires `pdftotext` binary)
**CRITICAL RULE:** Because the system runs on Windows/Laragon, calling this library MUST use the absolute path to Poppler: `C:\poppler-26.02.0\Library\bin\pdftotext.exe`. Never remove or alter this path from the extraction Job.

## Dev Tools

- **Laravel Pint** — Code style (PSR-12 based)
- **PHPUnit 12** — Testing
- **Laravel Pail** — Real-time log viewer
- **Faker** — Test data generation

## Common Commands

```bash
# Full dev environment (server + queue + logs + vite)
composer dev

# Run tests
composer test

# Code formatting
./vendor/bin/pint

# Run migrations
php artisan migrate

# Build frontend assets
npm run build

# Dev frontend (hot reload)
npm run dev
```

## Database

Using MySQL/MariaDB running on Laragon local environment. Default port 3306. **Strictly NOT using SQLite.**

## File Storage

Document files (PDF, Word) are stored via Laravel's filesystem (`storage/app`). Access is gated through authenticated routes.

## Queue

Jobs are processed via `php artisan queue:work` (running in the Laragon terminal background). Used primarily for `ExtractPdfTextJob`.
