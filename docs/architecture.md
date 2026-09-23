# Architecture

Expense Manager is a Laravel 13 application with an Inertia/React frontend. The public `main` branch is a single-user personal finance app focused on accounts, categories, transactions, budgets, dashboard analytics, and external SQLite sync.

## Application Shape

- Laravel routes live in `routes/web.php`.
- Server-rendered page responses are handled through Inertia.
- React pages live under `resources/js/Pages`.
- Frontend bootstrapping lives in `resources/js/app.jsx`; pages are lazy-loaded, so each page ships as its own chunk.
- Core domain models are `Account`, `Category`, `Transaction`, and `Budget`.
- Local development and CI use SQLite by default.

## Main Data Model

- `accounts` store balance-bearing places such as savings, credit card, cash, current, and investment accounts.
- `categories` support parent and child category hierarchies.
- `transactions` belong to an account and category, and store type, amount, date, description, payment method, reference number, tags, and location.
- `budgets` belong to categories and track spend within a configured date range.

## Request Flow

1. A browser request hits Laravel routes in `routes/web.php`.
2. A controller loads Eloquent models and prepares page props.
3. Inertia renders the matching React page.
4. Form submissions return through Laravel validation and redirects.

## Balance Updates

`Transaction` model events update account balances when transactions are created, updated, or deleted. Income adds to the account balance and expenses subtract from it.

Account transfers are two linked legs (`TRANSFER_OUTGOING` and `TRANSFER_INCOMING`) that share a `transfer_group_id`. They are created, updated, and deleted only as a pair through `AccountTransferService`, whether the change comes from the web, the AI chat tools, or bulk delete. Transfer categories are reserved for transfer legs, and `Transaction::saving` rejects them on regular rows.

Because database cascades bypass model events, deleting a category or an account that still has transactions is refused by the controller and by `restrict` foreign keys.

`Account::recalculateBalance()` (`php artisan accounts:recalculate-balances`) can rebuild an account balance from its opening balance and related transactions when needed.

## External Sync

`php artisan expense:sync` imports categories, accounts, and transactions from a private external SQLite database. The external database path must be supplied with `--db-path` or `EXTERNAL_DB_PATH`. Private databases and local paths must stay out of version control.

## CI

GitHub Actions runs on pushes and pull requests to `main`. The workflow installs PHP and Node dependencies from the committed `composer.lock` and `package-lock.json` (`npm ci`), checks formatting with Pint, builds frontend assets, prepares SQLite, runs migrations, and executes `composer test`. The PHP test suite does not need built assets (`withoutVite()` in `tests/TestCase.php`).

## Current Limitations

- **No authentication.** Every route is open to anyone who can reach the server, so run it locally only (see issue #79).
- The AI chat, the AI categorization, and the `mcp:serve` MCP server are available but still evolving; see [AI Tools](ai-tools.md).
- Statement import and reconciliation are active lanes; see [Statement Import](statement-import.md).
- Audit history beyond transaction timestamps is not yet a complete product surface.
