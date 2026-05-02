# Expense Manager

## Build (production assets)

Install PHP dependencies, install JS dependencies, then compile the Vite/React front end:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Use **`npm run build`** any time you change files under `resources/js` or related assets before deploying (writes to `public/build`).

**Development:** run the Vite dev server with hot reload:

```bash
npm run dev
```

(Usually together with `php artisan serve`, or use `composer run dev` if configured for concurrent processes.)

---

## Expense sync

```bash
php artisan expense:sync --db-path="/mnt/c/Users/rohit/Dropbox/ExpenseManager/Database/personal_finance.db" --dry-run

php artisan expense:sync --db-path="/mnt/c/Users/rohit/Dropbox/ExpenseManager/Database/personal_finance.db"

php artisan expense:sync --db-path="/mnt/c/Users/rohit/Dropbox/ExpenseManager/Database/personal_finance.db" --force
```
