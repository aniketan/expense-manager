# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- Every way of changing a transaction (web form, statement import and enrichment, AI chat tools, MCP, sync and dedupe commands) now goes through shared actions in `app/Actions/Transactions`, so they all apply the same rules ([#61](https://github.com/aniketan/expense-manager/issues/61)).
- Transaction filtering and income/expense totals are defined once (`app/Reporting`: `TransactionFilters`, `TransactionQuery`, `FinancialSummary`) and used by the transaction list, CSV export, home page, analytics, AI tools, and MCP ([#63](https://github.com/aniketan/expense-manager/issues/63)). Filtering by a parent category now also includes rows filed directly on the parent.
- The category must fit the type everywhere. Income needs a category under Income; expenses can't use Income or Account Transfer categories. The web form previously accepted any category. Older rows stay editable as long as their category and type aren't changed.

### Fixed

- A statement enrichment that changed a transfer's date moved only one leg. Both legs now move together.
- "Last month" in the AI tools and MCP reported the current month when run on the 29th–31st, and the analytics trend chart repeated or skipped a month on those days.
- The AI and MCP "both"/"all" totals counted transfers, so the total mixed income, spending, and transfers into one number.
- The AI search's category filter ignored subcategories, and its payment-method options (`cash`, `card`, `netbanking`) never matched stored values (`Cash`, `Credit Card`, `Bank Transfer`).
- MCP "this week" had no end date, so it included future-dated rows.
- `expense:sync --fresh` deleted synced rows without loading their category, so a row filed under Transfer Incoming was reversed with the wrong sign.

## [1.1.0] - 2026-09-24

Adds a login, a Docker runtime, and more balance and category fixes. Verified by manual testing of v1.1.0-rc.2.

### Added

- Docker runtime: `Dockerfile` + `compose.yml` (PHP 8.4 + Apache, SQLite on a named volume, automatic migrations and first-run seeding). `docker compose up -d --build` runs the app on http://localhost:8080 without PHP installed. CI now builds and boots the image.
- Single-user login ([#79](https://github.com/aniketan/expense-manager/issues/79)). Every route now requires `APP_LOGIN_PASSWORD` from `.env` (plain text or a bcrypt hash from `php artisan auth:hash-password`). Login attempts are limited to 5 per minute, and there is a Log out button in the navbar. With no password set, nobody can log in.

### Fixed

- Categories can no longer be nested more than two levels deep, which hid their transactions from parent budgets and filters ([#76](https://github.com/aniketan/expense-manager/issues/76)). Clearing a category's parent no longer errors.
- The balance update after a transaction's category changes now uses the new category, not the stale loaded one ([#77](https://github.com/aniketan/expense-manager/issues/77)).
- An account's current balance is now always opening balance + transactions. Editing the opening balance shifts it by the same amount, and it can no longer be typed over directly, which made balances drift from `accounts:recalculate-balances`.
- The opening balance may be negative, for example a credit card that already carries dues.
- Non-numeric `page`/`per_page` query values no longer cause errors on the Categories and Budgets pages.

### Upgrading

- Add `APP_LOGIN_PASSWORD` to `.env`, then run `php artisan config:clear`.
- If you previously edited an account's current balance by hand, run `php artisan accounts:recalculate-balances` to bring it back in line with its transactions.

## [1.0.0] - 2026-09-23

The first stable baseline: account balances can no longer be corrupted through side paths, list and report totals are correct at date boundaries, and builds are reproducible.

### Upgrading

- Run `php artisan migrate`. A new migration makes `transactions.account_id` restrict deletes, so deleting an account that still has transactions is refused instead of cascading.
- Run `composer install` and `npm ci`. Both lock files are now committed.
- The unauthenticated JSON endpoints `api/accounts`, `api/categories/*`, `api/budgets/summary`, and `api/stats/*` were removed because nothing in the app called them.

### Fixed

Balance integrity ([#73](https://github.com/aniketan/expense-manager/pull/73)):

- Deleting an account cascade-deleted its transactions without reversing their balance impact, which stranded transfer legs in other accounts. Deletion is now refused while transactions exist.
- Regular income and expense transactions could use the transfer categories, which flipped the balance sign. They are now rejected in the web form, statement import, AI tools, and MCP, with a model-level backstop.
- The AI chat tool deleted or edited only one leg of a transfer. Both legs now change together.
- The AI and MCP `create_transaction` tools accepted `transfer` and could pick a category of the wrong type.
- Legacy transfers without a linked counterpart crashed on edit or delete. They can now be deleted, individually or in bulk.
- `transactions:dedupe-by-description` could delete one leg of a transfer.
- The MCP server wrote a warning into its JSON-RPC stdout and replied to notifications.

Lists and reports ([#74](https://github.com/aniketan/expense-manager/pull/74)):

- The "to" date filter dropped the last day from the transaction list, its totals, and the CSV export.
- The per-page selector on Transactions always fell back to 15.
- Searching while sorted by category caused a SQL error (`ambiguous column name`).
- Budget alerts were never shown. They now also fire for budgets on the parent category and on a budget's first day.
- The budget detail page omitted child-category transactions that its spent total included.
- The budget overlap check missed ranges touching on a boundary day, and budgets ending today stopped counting as current.

### Changed

- Inertia pages are lazy-loaded. The initial JavaScript bundle dropped from 1,154 kB to 519 kB ([#75](https://github.com/aniketan/expense-manager/pull/75)).
- `composer.lock` and `package-lock.json` are committed; CI installs with `npm ci`.
- The PHP test suite no longer requires `npm run build` to run.
- The home page is served by `DashboardController::index` instead of a route closure.

### Removed

- Unused dependencies: `laravel/ui`, `laravel/sail`, `tailwindcss`, `@tailwindcss/vite`, `axios`, `@popperjs/core`, `font-awesome@4`, and three unused `@fortawesome/*` packages. The duplicate Font Awesome CDN link was also removed; the bundled Font Awesome 6 renders the same icons.
- Dead code: unused controller methods and model accessors, the non-functional Duplicate/Delete/History buttons on the transaction edit page, and a debug `console.log`.

### Known limitations

- There is no authentication, so the app is meant for local use only ([#79](https://github.com/aniketan/expense-manager/issues/79)).
- The roadmap for the next releases is tracked in issues #61–#67 and #76–#79.

[1.1.0]: https://github.com/aniketan/expense-manager/releases/tag/v1.1.0
[1.0.0]: https://github.com/aniketan/expense-manager/releases/tag/v1.0.0
