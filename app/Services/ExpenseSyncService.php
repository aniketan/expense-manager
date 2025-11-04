<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Exception;

class ExpenseSyncService
{
    private PDO $externalDb;
    private string $externalDbPath;
    private array $accountMapping = [];
    private array $categoryMapping = [];
    private array $stats = [
        'categories_synced' => 0,
        'accounts_synced' => 0,
        'transactions_synced' => 0,
        'errors' => 0
    ];

    public function __construct(string $externalDbPath = null)
    {
        $this->externalDbPath = $externalDbPath ?: config('sync.external_db_path', '/mnt/c/Users/rohit/Dropbox/ExpenseManager/Database/personal_finance.db');
        $this->initializeExternalDb();
    }

    /**
     * Initialize connection to external SQLite database
     */
    private function initializeExternalDb(): void
    {
        if (!file_exists($this->externalDbPath)) {
            throw new Exception("External database not found at: {$this->externalDbPath}");
        }

        try {
            $this->externalDb = new PDO("sqlite:{$this->externalDbPath}");
            $this->externalDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            Log::info("Connected to external database: {$this->externalDbPath}");
        } catch (Exception $e) {
            Log::error("Failed to connect to external database: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Main sync method - orchestrates the entire sync process
     */
    public function sync(bool $dryRun = false): array
    {
        Log::info("Starting expense sync process", ['dry_run' => $dryRun]);

        try {
            DB::beginTransaction();

            // Step 1: Sync categories
            $this->syncCategories($dryRun);

            // Step 2: Sync accounts (extract from transactions)
            $this->syncAccounts($dryRun);

            // Step 3: Sync transactions
            $this->syncTransactions($dryRun);

            if (!$dryRun) {
                DB::commit();
                Log::info("Sync completed successfully", $this->stats);
            } else {
                DB::rollBack();
                Log::info("Dry run completed", $this->stats);
            }

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Sync failed: " . $e->getMessage());
            $this->stats['errors']++;
            throw $e;
        }

        return $this->stats;
    }

    /**
     * Sync categories from external database
     */
    private function syncCategories(bool $dryRun): void
    {
        Log::info("Starting category sync");

        // Get categories from actual transaction data instead of expense_category table
        $stmt = $this->externalDb->query("
            SELECT DISTINCT category, subcategory
            FROM expense_report
            WHERE category IS NOT NULL AND category != ''
            ORDER BY category, subcategory
        ");

        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categories as $categoryData) {
            $categoryName = trim($categoryData['category']);
            $subcategoryName = trim($categoryData['subcategory'] ?? '');

            if (empty($categoryName)) continue;

            // Create or find parent category
            $parentCategory = $this->findOrCreateCategory($categoryName, null, $dryRun);

            // Create or find subcategory if exists
            if (!empty($subcategoryName)) {
                $subcategory = $this->findOrCreateCategory($subcategoryName, $parentCategory->id, $dryRun);
                $this->categoryMapping[$categoryName . '|' . $subcategoryName] = $subcategory->id;
            } else {
                $this->categoryMapping[$categoryName . '|'] = $parentCategory->id;
            }
        }

        Log::info("Category sync completed", ['count' => $this->stats['categories_synced']]);
    }    /**
     * Find or create category
     */
    private function findOrCreateCategory(string $name, ?int $parentId, bool $dryRun): Category
    {
        // Generate a unique code for the category
        $code = strtoupper(str_replace([' ', '-', '_'], '', substr($name, 0, 10)));

        $category = Category::where('name', $name)
            ->where('parent_id', $parentId)
            ->first();

        if (!$category) {
            if (!$dryRun) {
                $category = Category::create([
                    'name' => $name,
                    'code' => $code,
                    'parent_id' => $parentId,
                    'description' => "Synced from external database",
                    'is_active' => true
                ]);
            } else {
                // For dry run, create a mock category object
                $category = new Category();
                $category->id = rand(1000, 9999); // Mock ID
                $category->name = $name;
                $category->parent_id = $parentId;
            }

            $this->stats['categories_synced']++;
            Log::debug("Created category: {$name}", ['parent_id' => $parentId]);
        }

        return $category;
    }

    /**
     * Sync accounts from transaction data
     */
    private function syncAccounts(bool $dryRun): void
    {
        Log::info("Starting account sync");

        $stmt = $this->externalDb->query("
            SELECT DISTINCT account
            FROM expense_report
            WHERE account IS NOT NULL AND account != ''
            ORDER BY account
        ");

        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($accounts as $accountData) {
            $accountCode = trim($accountData['account']);

            if (empty($accountCode)) continue;

            $account = Account::where('code', $accountCode)->first();

            if (!$account) {
                if (!$dryRun) {
                    $account = Account::create([
                        'code' => $accountCode,
                        'name' => $this->generateAccountName($accountCode),
                        'type' => $this->guessAccountType($accountCode),
                        'bank_name' => $this->getBankName($accountCode),
                        'opening_balance' => 0,
                        'current_balance' => 0,
                        'is_active' => true
                    ]);
                } else {
                    // For dry run, create a mock account object
                    $account = new Account();
                    $account->id = rand(1000, 9999); // Mock ID
                    $account->code = $accountCode;
                }

                $this->stats['accounts_synced']++;
                Log::debug("Created account: {$accountCode}");
            }

            $this->accountMapping[$accountCode] = $account->id;
        }

        Log::info("Account sync completed", ['count' => $this->stats['accounts_synced']]);
    }

    /**
     * Sync transactions from external database
     */
    private function syncTransactions(bool $dryRun): void
    {
        Log::info("Starting transaction sync");

        $stmt = $this->externalDb->query("
            SELECT _id, account, amount, category, subcategory, payment_method,
                   description, expensed, reference_number, expense_tag
            FROM expense_report
            WHERE account IS NOT NULL AND account != ''
            ORDER BY expensed DESC
        ");

        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($transactions as $transactionData) {
            try {
                $this->syncSingleTransaction($transactionData, $dryRun);
            } catch (Exception $e) {
                Log::error("Failed to sync transaction ID: " . $transactionData['_id'], [
                    'error' => $e->getMessage(),
                    'data' => $transactionData
                ]);
                $this->stats['errors']++;
            }
        }

        Log::info("Transaction sync completed", ['count' => $this->stats['transactions_synced']]);
    }

    /**
     * Sync a single transaction
     */
    private function syncSingleTransaction(array $data, bool $dryRun): void
    {
        $externalId = $data['_id'];
        $accountCode = trim($data['account']);
        $amount = floatval($data['amount']);
        $category = trim($data['category']);
        $subcategory = trim($data['subcategory'] ?? '');
        $description = trim($data['description'] ?? '');
        $paymentMethod = trim($data['payment_method'] ?? '');
        $referenceNumber = trim($data['reference_number'] ?? '');
        $expensed = $data['expensed'];
        $tags = trim($data['expense_tag'] ?? '');

        // Skip if already synced (check by external ID in description or reference)
        $existingTransaction = Transaction::where('reference_number', 'EXT_' . $externalId)->first();
        if ($existingTransaction) {
            return; // Already synced
        }

        // Get account ID
        $accountId = $this->accountMapping[$accountCode] ?? null;
        if (!$accountId) {
            throw new Exception("Account not found: {$accountCode}");
        }

        // Get category ID
        if (empty($category)) {
            // Handle empty category - create/use Uncategorized
            $categoryId = $this->getUncategorizedCategory($dryRun);
        } else {
            $categoryKey = $category . '|' . $subcategory;
            $categoryId = $this->categoryMapping[$categoryKey] ?? null;
            if (!$categoryId) {
                // Try without subcategory
                $categoryKey = $category . '|';
                $categoryId = $this->categoryMapping[$categoryKey] ?? null;
            }
            if (!$categoryId) {
                throw new Exception("Category not found: {$category} -> {$subcategory}");
            }
        }

        // Determine transaction type
        $transactionType = $this->determineTransactionType($category, $amount);

        // Convert timestamp
        $transactionDate = $this->convertTimestamp($expensed);

        if (!$dryRun) {
            Transaction::create([
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'transaction_type' => $transactionType,
                'amount' => abs($amount), // Store as positive amount
                'description' => $description ?: "Synced from external DB",
                'transaction_date' => $transactionDate,
                'payment_method' => $paymentMethod ?: 'unknown',
                'reference_number' => 'EXT_' . $externalId, // Mark as external sync
                'tags' => $tags
            ]);
        }

        $this->stats['transactions_synced']++;
    }

    /**
     * Generate account name from code
     */
    private function generateAccountName(string $code): string
    {
        $mappings = config('sync.account_mappings', []);

        return $mappings[$code]['name'] ?? ucfirst(strtolower($code)) . ' Account';
    }

    /**
     * Get bank name from code
     */
    private function getBankName(string $code): ?string
    {
        $mappings = config('sync.account_mappings', []);

        return $mappings[$code]['bank_name'] ?? null;
    }

    /**
     * Guess account type from code
     */
    private function guessAccountType(string $code): string
    {
        $mappings = config('sync.account_mappings', []);

        return $mappings[$code]['type'] ?? 'savings';
    }    /**
     * Determine transaction type based on category and amount
     */
    private function determineTransactionType(string $category, float $amount): string
    {
        $categoryMappings = config('sync.category_mappings', []);

        $incomeCategories = $categoryMappings['income_categories'] ?? ['Income', 'Salary', 'Bonus', 'Interest', 'Refund', 'Cashback'];
        $transferCategories = $categoryMappings['transfer_categories'] ?? ['Account Transfer', 'Transfer'];

        if (in_array($category, $incomeCategories) || $amount < 0) {
            return 'income';
        } elseif (in_array($category, $transferCategories)) {
            return 'transfer';
        } else {
            return 'expense';
        }
    }

    /**
     * Convert timestamp to date
     */
    private function convertTimestamp($timestamp): string
    {
        if (strlen($timestamp) > 10) {
            // Milliseconds timestamp
            $timestamp = substr($timestamp, 0, 10);
        }

        return date('Y-m-d', intval($timestamp));
    }

    /**
     * Get sync statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get or create Uncategorized category
     */
    private function getUncategorizedCategory(bool $dryRun): int
    {
        static $uncategorizedId = null;

        if ($uncategorizedId === null) {
            $uncategorized = $this->findOrCreateCategory('Uncategorized', null, $dryRun);
            $uncategorizedId = $uncategorized->id;
            $this->categoryMapping['Uncategorized|'] = $uncategorizedId;
        }

        return $uncategorizedId;
    }

    /**
     * Reset sync statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'categories_synced' => 0,
            'accounts_synced' => 0,
            'transactions_synced' => 0,
            'errors' => 0
        ];
    }
}
