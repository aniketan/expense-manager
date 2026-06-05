# Expense Manager

Expense Manager is a single-user personal finance app built with Laravel, Inertia, and React. The product direction is chat-first money management: fast expense capture, reliable category mapping, statement import cleanup, and auditable balances.

## Stack

- Laravel 13
- PHP 8.4 in CI
- Inertia Laravel 3
- React 19
- Vite 7
- Tailwind CSS 4
- Bootstrap 5
- SQLite by default for local development and CI
- Prism PHP for AI-assisted chat and categorization workflows

## Core Workflows

- Manage accounts with opening and current balances.
- Manage parent and child categories for income and expense tracking.
- Create, edit, filter, export, and review transactions.
- Track budgets by category and period.
- Import and reconcile bank statement rows.
- Use AI-assisted categorization and chat-first money workflows as active product lanes.
- Sync data from a private external SQLite database with `php artisan expense:sync`.

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

Run the backend, queue worker, and Vite dev server together:

```bash
composer run dev
```

For front-end hot reload only:

```bash
npm run dev
```

## Build

Install PHP dependencies, install JS dependencies, then compile the Vite/React front end:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Use `npm run build` any time you change files under `resources/js` or related assets before deploying. The compiled assets are written to `public/build`.

## Verification

Use these commands before opening or merging a PR when practical:

```bash
npm run build
composer test
```

GitHub Actions also runs the frontend build, migrations, and PHP test suite on pull requests to `main`.

## Expense Sync

The sync command accepts a local SQLite database path. Use your own private database path and keep it out of version control.

```bash
php artisan expense:sync --db-path="/path/to/private/expense-data.sqlite" --dry-run
php artisan expense:sync --db-path="/path/to/private/expense-data.sqlite"
php artisan expense:sync --db-path="/path/to/private/expense-data.sqlite" --force
```

You can also set `EXTERNAL_DB_PATH` in your local `.env`. Do not commit local `.env` files, private databases, bank statements, machine-specific assistant context, or real financial data.

## Documentation

- [Architecture](docs/architecture.md)
- [AI Tools](docs/ai-tools.md)
- [Statement Import](docs/statement-import.md)
- [AI-Assisted Development](docs/ai-assisted-development.md)
- [Roadmap](docs/roadmap.md)

## Project Status

Expense Manager is in active development. The current priority is the core money-entry workflow: chat-based capture, trustworthy categorization, statement import cleanup, and balance auditability. Broader dashboard and portfolio polish are intentionally secondary until that core workflow is reliable.
