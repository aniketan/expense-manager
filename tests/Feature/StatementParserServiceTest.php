<?php

namespace Tests\Feature;

use App\Services\StatementParserService;
use Illuminate\Http\UploadedFile;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class StatementParserServiceTest extends TestCase
{
    public function test_parse_csv_merges_llm_account_info_and_php_transactions(): void
    {
        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: json_encode([
                    'account_info' => [
                        'account_holder_name' => 'Sample Holder',
                        'bank_name' => 'Sample Bank',
                        'account_number' => '100036608561',
                        'ifsc_code' => 'INDB0000732',
                        'account_type' => 'savings',
                        'statement_period' => 'Mar 2026',
                    ],
                ]),
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $path = base_path('tests/fixtures/statement_indus_sample.csv');
        $file = new UploadedFile($path, 'statement.csv', 'text/csv', null, true);

        $service = new StatementParserService;
        $result = $service->parse($file);

        $this->assertSame('Sample Holder', $result['account_info']['account_holder_name']);
        $this->assertSame('INDB0000732', $result['account_info']['ifsc_code']);
        $this->assertCount(2, $result['transactions']);

        $this->assertSame('2026-03-22', $result['transactions'][0]['date']);
        $this->assertSame('income', $result['transactions'][0]['type']);
        $this->assertEqualsWithDelta(23250.0, $result['transactions'][0]['amount'], 0.01);

        $this->assertSame('expense', $result['transactions'][1]['type']);
        $this->assertEqualsWithDelta(624.29, $result['transactions'][1]['amount'], 0.01);
    }

    public function test_comma_inside_description_does_not_shift_debit_into_credit(): void
    {
        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: json_encode(['account_info' => ['account_holder_name' => null, 'bank_name' => null, 'account_number' => null, 'ifsc_code' => null, 'account_type' => null, 'statement_period' => null]]),
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $csv = <<<'CSV'
Transaction List

Sr.No.,Date,Type, Description, Debit ,Credit ,Balance
3,Mar 22  2026,Transfer Debit,MC POS TXN AT US/CURSOR, AI POWERED IDENEW YORK,2305.63,-,249828.32
CSV;

        $tmp = tempnam(sys_get_temp_dir(), 'stmt');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $csv);

        try {
            $file = new UploadedFile($tmp, 'statement.csv', 'text/csv', null, true);
            $service = new StatementParserService;
            $result = $service->parse($file);
        } finally {
            @unlink($tmp);
        }

        $this->assertCount(1, $result['transactions']);
        $row = $result['transactions'][0];
        $this->assertSame('expense', $row['type']);
        $this->assertEqualsWithDelta(2305.63, $row['amount'], 0.01);
        $this->assertStringContainsString('CURSOR', $row['description']);
        $this->assertStringContainsString('AI POWERED', $row['description']);
    }

    public function test_preamble_key_value_lines_fill_account_info_when_llm_returns_nulls(): void
    {
        Prism::fake([
            new TextResponse(
                steps: collect([]),
                text: json_encode([
                    'account_info' => [
                        'account_holder_name' => null,
                        'bank_name' => null,
                        'account_number' => null,
                        'ifsc_code' => null,
                        'account_type' => null,
                        'statement_period' => null,
                    ],
                ]),
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                usage: new Usage(0, 0),
                meta: new Meta('fake', 'fake'),
                messages: collect([]),
            ),
        ]);

        $path = base_path('tests/fixtures/statement_indus_sample.csv');
        $file = new UploadedFile($path, 'statement.csv', 'text/csv', null, true);

        $service = new StatementParserService;
        $result = $service->parse($file);

        $this->assertSame('SAMPLE USER', $result['account_info']['account_holder_name']);
        $this->assertSame('100036608561', $result['account_info']['account_number']);
        $this->assertSame('INDB0000732', $result['account_info']['ifsc_code']);
        $this->assertStringContainsString('Mar 22', (string) $result['account_info']['statement_period']);
    }
}
