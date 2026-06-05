<?php

namespace Tests\Unit;

use App\Services\StatementMatchKeyService;
use App\Services\StatementReconciliationService;
use Tests\TestCase;

class StatementReconciliationServiceTest extends TestCase
{
    public function test_balance_chain_detects_break(): void
    {
        $svc = new StatementReconciliationService(new StatementMatchKeyService);

        $parsed = [
            [
                'date' => '2026-03-22',
                'description' => 'A',
                'amount' => 23250.0,
                'type' => 'income',
                'reference' => '1',
                'balance_after' => 252758.24,
                'statement_sequence' => 1,
                'debit_amount' => 0.0,
                'credit_amount' => 23250.0,
            ],
            [
                'date' => '2026-03-22',
                'description' => 'B',
                'amount' => 624.29,
                'type' => 'expense',
                'reference' => '2',
                'balance_after' => 252000.00,
                'statement_sequence' => 2,
                'debit_amount' => 624.29,
                'credit_amount' => 0.0,
            ],
        ];

        $result = $svc->analyze($parsed, null);

        $this->assertFalse($result['summary']['balance']['chain_ok']);
        $this->assertSame(2, $result['summary']['balance']['broken_at_statement_sequence']);
    }

    public function test_derived_period_from_transactions(): void
    {
        $svc = new StatementReconciliationService(new StatementMatchKeyService);

        $parsed = [
            [
                'date' => '2026-03-01',
                'description' => 'x',
                'amount' => 10,
                'type' => 'expense',
                'debit_amount' => 10,
                'credit_amount' => 0,
            ],
            [
                'date' => '2026-03-31',
                'description' => 'y',
                'amount' => 5,
                'type' => 'income',
                'debit_amount' => 0,
                'credit_amount' => 5,
            ],
        ];

        $result = $svc->analyze($parsed, null);

        $this->assertSame('2026-03-01', $result['period']['start']);
        $this->assertSame('2026-03-31', $result['period']['end']);
    }
}
