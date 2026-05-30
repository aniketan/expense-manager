# Expense Manager

Expense Manager is a single-user personal finance app built with Laravel, Inertia, and React. The long-term product direction is chat-first money management: fast expense capture, reliable category mapping, statement import cleanup, and auditable balances.

The public `main` branch currently contains the foundation: accounts, categories, transactions, budgets, dashboard analytics, external SQLite sync, CI, and the portfolio/docs workflow. Chat entry, AI-assisted categorization, and statement import reconciliation are active product lanes and should be treated as roadmap/WIP until merged.

## Stack

- Laravel 13
- PHP 8.4 in CI
- Inertia Laravel 3
- React 19
- Vite 7
- Tailwind CSS 4
- Bootstrap 5
- SQLite by default for local development and CI
- Prism PHP is available for future AI integration work

## Core Workflows

- Manage accounts with opening and current balances.
- Manage parent and child categories for income and expense tracking.
- Create, edit, filter, and review transactions.
- Track budgets by category and period.
- View dashboard and analytics summaries.
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
