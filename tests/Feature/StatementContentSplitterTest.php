<?php

namespace Tests\Feature;

use App\Services\StatementContentSplitter;
use Tests\TestCase;

class StatementContentSplitterTest extends TestCase
{
    public function test_split_csv_finds_preamble_and_table(): void
    {
        $raw = (string) file_get_contents(base_path('tests/fixtures/statement_demo_sample.csv'));
        $splitter = new StatementContentSplitter;
        $split = $splitter->splitCsvContent($raw);

        $this->assertStringContainsString('TEST0001234', $split['preamble']);
        $this->assertStringContainsString('Sr.No.', $split['table_csv'] ?? '');
        $this->assertNotNull($split['header_line_index']);
    }

    public function test_split_pdf_text_uses_same_header_detection(): void
    {
        $raw = (string) file_get_contents(base_path('tests/fixtures/statement_demo_sample.csv'));
        $splitter = new StatementContentSplitter;
        $split = $splitter->splitPdfText($raw);

        $this->assertStringContainsString('TEST0001234', $split['preamble']);
        $this->assertStringContainsString('Sr.No.', $split['body']);
    }
}
