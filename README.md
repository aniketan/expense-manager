# Expense Manager

Expense Manager is a Laravel and React application for tracking personal finances, managing accounts, and reviewing spending patterns.

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build
composer test
```

## Development

Run the backend and frontend during local development:

```bash
composer run dev
```

## Expense Sync

The sync command accepts a local SQLite database path. Use your own private database path and keep it out of version control.

```bash
php artisan expense:sync --db-path="/path/to/private/expense-data.sqlite" --dry-run
php artisan expense:sync --db-path="/path/to/private/expense-data.sqlite"
php artisan expense:sync --db-path="/path/to/private/expense-data.sqlite" --force
```

Never commit local `.env` files, private databases, bank statements, or machine-specific assistant context.
