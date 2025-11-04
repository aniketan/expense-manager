<?php

return [
    /*
    |--------------------------------------------------------------------------
    | External Database Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration is used for syncing data from external databases
    | into the Laravel application.
    |
    */

    'external_db_path' => env('EXTERNAL_DB_PATH', '/mnt/c/Users/rohit/Dropbox/ExpenseManager/Database/personal_finance.db'),

    /*
    |--------------------------------------------------------------------------
    | Sync Options
    |--------------------------------------------------------------------------
    */
    'sync_options' => [
        // Whether to skip duplicate transactions (based on reference_number)
        'skip_duplicates' => true,

        // Maximum number of transactions to sync in one batch
        'batch_size' => 1000,

        // Whether to update account balances after sync
        'update_balances' => true,

        // Date range for syncing (null means sync all)
        'sync_from_date' => null, // e.g., '2025-01-01'
        'sync_to_date' => null,   // e.g., '2025-12-31'
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Mappings
    |--------------------------------------------------------------------------
    |
    | Map external account codes to account types and names
    |
    */
    'account_mappings' => [
        'INDB' => [
            'name' => 'IndusInd Bank Savings',
            'type' => 'savings',
            'bank_name' => 'IndusInd Bank'
        ],
        'ICIC' => [
            'name' => 'ICICI Bank Savings',
            'type' => 'savings',
            'bank_name' => 'ICICI Bank'
        ],
        'HDFC' => [
            'name' => 'HDFC Bank Savings',
            'type' => 'savings',
            'bank_name' => 'HDFC Bank'
        ],
        'SBI' => [
            'name' => 'State Bank of India',
            'type' => 'savings',
            'bank_name' => 'State Bank of India'
        ],
        'AXIS' => [
            'name' => 'Axis Bank Savings',
            'type' => 'savings',
            'bank_name' => 'Axis Bank'
        ],
        'CASH' => [
            'name' => 'Cash Account',
            'type' => 'cash',
            'bank_name' => null
        ],
        'PAYTM' => [
            'name' => 'Paytm Wallet',
            'type' => 'cash',
            'bank_name' => 'Paytm'
        ],
        'GPAY' => [
            'name' => 'Google Pay',
            'type' => 'cash',
            'bank_name' => 'Google'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Category Mappings
    |--------------------------------------------------------------------------
    |
    | Define how external categories should be mapped to internal categories
    |
    */
    'category_mappings' => [
        'income_categories' => [
            'Income',
            'Salary',
            'Bonus',
            'Interest',
            'Refund',
            'Cashback',
            'Investment Returns'
        ],
        'transfer_categories' => [
            'Account Transfer',
            'Transfer'
        ],
        'expense_categories' => [
            // All other categories are considered expenses
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Mappings
    |--------------------------------------------------------------------------
    |
    | Map external database fields to internal fields
    |
    */
    'field_mappings' => [
        'transaction' => [
            'external_id' => '_id',
            'account' => 'account',
            'amount' => 'amount',
            'category' => 'category',
            'subcategory' => 'subcategory',
            'payment_method' => 'payment_method',
            'description' => 'description',
            'date' => 'expensed',
            'reference_number' => 'reference_number',
            'tags' => 'expense_tag',
            'status' => 'status'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'log_file' => 'sync.log'
    ]
];
