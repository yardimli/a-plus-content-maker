# A+ Content Maker

A Laravel 10 authoring studio for planning, writing, illustrating, previewing, and exporting Amazon KDP A+ Content. Authors can start blank or clone an admin-curated template, link book context by ASIN, compose with 17 KDP-style modules, and prepare a structured manual-transfer package.

The app is independent from Amazon and does not publish directly to KDP.

## Stack

- Laravel 10 / PHP 8.1+
- Laravel Breeze authentication with conventional Blade/HTML views (no `<x-...>` components)
- Tailwind CSS + Vite
- Vanilla JavaScript and Fetch-based AJAX
- MariaDB/MySQL in development; in-memory SQLite for tests

## Local setup

```bash
composer install
npm install
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
npm run build
php artisan serve
```

Copy `.env.example` to `.env` first and configure the database, RapidAPI, OpenRouter, and admin values. The database name defaults to `a_plus_content`; credentials are intentionally omitted from `.env.example`.

The seeded admin account is controlled by:

```dotenv
ADMIN_NAME="A+ Content Admin"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=
```

Set a strong local password before running the seeder. Production seeding refuses a missing password.

## Provider configuration

ASIN requests are proxied through Laravel so the RapidAPI key never reaches the browser. OpenRouter text and image models are also selected server-side.

```dotenv
RAPIDAPI_KEY=
OPENROUTER_API_KEY=
OPENROUTER_TEXT_MODEL=
OPENROUTER_IMAGE_MODEL=
```

## Verification

```bash
php artisan test
npm run build
composer audit
```

See [PROJECT_BLUEPRINT.md](PROJECT_BLUEPRINT.md) for the product/data plan and [SECURITY_NOTES.md](SECURITY_NOTES.md) for the Laravel 10 support caveat.
