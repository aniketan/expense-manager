<?php

return [

    'preamble_max_chars' => (int) env('STATEMENT_PREAMBLE_MAX_CHARS', 8192),

    'max_parsed_transaction_rows' => (int) env('STATEMENT_MAX_PARSED_ROWS', 5000),

    /*
     * Lines containing any of these (case-insensitive) start the search for a transaction table header.
     */
    'csv_transaction_start_markers' => [
        'Transaction List',
        'transaction list',
    ],

    /*
     * Header row must contain all of these substrings (case-insensitive).
     */
    'csv_header_required_substrings' => ['date', 'debit', 'credit'],

    /*
     * Optional: header row should match at least one of these (e.g. serial column).
     */
    'csv_header_optional_substrings' => ['sr.no', 'sr no', 's.no'],

    /*
     * When reconciling statement rows to ledger, widen the DB query window by ±N calendar days so
     * amount+description matches still resolve when sync/import dates disagree slightly from the bank file.
     */
    'reconcile_date_skew_days' => max(0, (int) env('STATEMENT_RECONCILE_DATE_SKEW_DAYS', 3)),

    /*
     * Optional max calendar distance between statement row date and thin-placeholder ledger rows when picking among
     * multiple candidates (null = no extra radius filter beyond skew query window; ties broken by nearest date).
     */
    'reconcile_thin_proximity_max_days' => env('STATEMENT_RECONCILE_THIN_PROXIMITY_MAX_DAYS') !== null
        ? max(0, (int) env('STATEMENT_RECONCILE_THIN_PROXIMITY_MAX_DAYS'))
        : null,

    /*
     * When true, each reconcile row includes match_attempt_reason for troubleshooting (avoid in production UX noise).
     */
    'reconcile_debug' => (bool) env('STATEMENT_RECONCILE_DEBUG', false),

    /*
     * Informational only: descriptions matching these substrings (case-insensitive) suggest an opening-balance adjustment row.
     */
    'initial_balance_description_patterns' => [
        'initial balance',
        'opening balance',
    ],

];
