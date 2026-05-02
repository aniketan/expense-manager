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

];
