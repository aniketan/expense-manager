# Architecture

Expense Manager is a Laravel 13 application with an Inertia/React frontend. The public `main` branch is a single-user personal finance app focused on accounts, categories, transactions, budgets, dashboard analytics, and external SQLite sync.

## Application Shape

- Laravel routes live in `routes/web.php`.
- Server-rendered page responses are handled through Inertia.
- React pages live under `resources/js/Pages`.
- Shared frontend bootstrapping lives in `resources/js/app.jsx` and `resources/js/bootstrap.js`.
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

`Transaction` model events update account balances when transactions are created, updated, or deleted. Income adds to the account balance, expenses subtract from it, and transfer handling creates paired transaction entries through `TransactionController`.

`Account::recalculateBalance()` can rebuild an account balance from its opening balance and related transactions when needed.

## External Sync

`php artisan expense:sync` imports categories, accounts, and transactions from a private external SQLite database. The external database path must be supplied with `--db-path` or `EXTERNAL_DB_PATH`. Private databases and local paths must stay out of version control.

## CI

GitHub Actions runs on pushes and pull requests to `main`. The workflow installs PHP and Node dependencies, builds frontend assets, prepares SQLite, runs migrations, and executes `composer test`.

## Current Limitations

- The public `main` branch does not yet include the active chat-first workflow.
- Statement import and reconciliation are active WIP lanes and are documented separately as roadmap work.
- There is no public multi-user/auth workflow in the current branch.
- Audit history beyond transaction timestamps is not yet a complete product surface.
