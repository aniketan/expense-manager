# Expense Manager

Expense Manager is a single-user personal finance app built with Laravel, Inertia, and React. The product direction is chat-first money management: fast expense capture, reliable category mapping, statement import cleanup, and auditable balances.

## Stack

- Laravel 13
- PHP 8.4 in CI
- Inertia Laravel 3
- React 19
- Vite 7
- Bootstrap 5 and Font Awesome 6 (bundled)
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

## Run with Docker (no PHP needed)

1. Add a login password to `.env` next to `compose.yml`: `APP_LOGIN_PASSWORD=your-password`
2. Run `docker compose up -d --build`
3. Open http://localhost:8080 and log in

Data lives in the `expense-data` Docker volume and survives rebuilds. `docker compose down -v` erases it. Set `APP_PORT` to change the port. The AI chat talks to Ollama on your machine at `host.docker.internal:11434`.

## Local Setup

> **Set a login password.** Every page requires `APP_LOGIN_PASSWORD` from `.env`. Until it is set, nobody can log in. Run `php artisan auth:hash-password` to get a hashed value to paste in instead of plain text. Serve the app over HTTPS if it is reachable beyond your own machine.


```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan auth:hash-password   # paste the printed APP_LOGIN_PASSWORD line into .env
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
npm run lint
npm test
npm run build
composer test
```

GitHub Actions also runs the frontend lint and test suites, the frontend build, migrations, and the PHP test suite on pull requests to `main`.

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

Expense Manager is in active development. `v1.0.0` is the first stable baseline; see [CHANGELOG.md](CHANGELOG.md). The current priority is the core money-entry workflow: chat-based capture, trustworthy categorization, statement import cleanup, and balance auditability. Broader dashboard and portfolio polish are intentionally secondary until that core workflow is reliable.
