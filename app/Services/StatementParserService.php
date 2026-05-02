<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use League\Csv\Reader;
use League\Csv\SyntaxError;
use Prism\Prism\Facades\Prism;
use Smalot\PdfParser\Parser as PdfParser;

class StatementParserService
{
    public function __construct(
        private ?StatementContentSplitter $splitter = null,
    ) {
        $this->splitter = $splitter ?? new StatementContentSplitter;
    }

    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        return match ($extension) {
            'csv' => $this->parseCsv($file),
            'pdf' => $this->parsePdf($file),
            default => throw new \InvalidArgumentException("Unsupported file type: {$extension}. Use PDF or CSV."),
        };
    }

    /**
     * @return array{account_info: array<string, mixed>, transactions: array<int, array<string, mixed>>}
     */
    private function parseCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $raw = (string) file_get_contents($path);
        $split = $this->splitter->splitCsvContent($raw);

        $accountInfo = $this->extractAccountInfoWithLlm($split['preamble']);
        $accountInfo = $this->enrichAccountInfoFromPreambleKeyValues($split['preamble'], $accountInfo);
        $transactions = $split['table_csv'] !== null
            ? $this->parseTransactionsFromTableCsv($split['table_csv'])
            : [];

        return $this->mergeResult($accountInfo, $transactions);
    }

    /**
     * @return array{account_info: array<string, mixed>, transactions: array<int, array<string, mixed>>}
     */
    private function parsePdf(UploadedFile $file): array
    {
        $text = $this->extractFromPdf($file);
        $split = $this->splitter->splitPdfText($text);

        $accountInfo = $this->extractAccountInfoWithLlm($split['preamble']);
        $accountInfo = $this->enrichAccountInfoFromPreambleKeyValues($split['preamble'], $accountInfo);
        $transactions = $this->parseTransactionsFromTableCsv($split['body']);

        return $this->mergeResult($accountInfo, $transactions);
    }

    private function extractFromPdf(UploadedFile $file): string
    {
        $parser = new PdfParser;
        $pdf = $parser->parseFile($file->getRealPath() ?: $file->getPathname());

        return $pdf->getText() ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractAccountInfoWithLlm(string $preamble): array
    {
        if (trim($preamble) === '') {
            return $this->emptyAccountInfo();
        }

        $provider = config('ai.provider');
        $model = config('ai.model');
        $prompt = $this->buildPreambleAccountPrompt($preamble);

        try {
            $response = Prism::text()
                ->using($provider, $model)
                ->withSystemPrompt('You are a bank statement metadata parser. Respond with valid JSON only. No markdown.')
                ->withPrompt($prompt)
                ->withMaxTokens(2048)
                ->asText();
        } catch (\Throwable $e) {
            report($e);

            return $this->emptyAccountInfo();
        }

        $json = trim($response->text);
        $json = preg_replace('/^```(?:json)?\s*/i', '', $json) ?? $json;
        $json = preg_replace('/\s*```$/i', '', $json) ?? $json;
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return $this->emptyAccountInfo();
        }

        $accountInfo = $decoded['account_info'] ?? $decoded;
        if (! is_array($accountInfo)) {
            return $this->emptyAccountInfo();
        }

        return [
            'account_holder_name' => $this->stringOrNull($accountInfo['account_holder_name'] ?? null),
            'bank_name' => $this->stringOrNull($accountInfo['bank_name'] ?? null),
            'account_number' => $this->stringOrNull($accountInfo['account_number'] ?? null),
            'ifsc_code' => $this->stringOrNull($accountInfo['ifsc_code'] ?? null),
            'account_type' => $this->stringOrNull($accountInfo['account_type'] ?? null),
            'statement_period' => $this->stringOrNull($accountInfo['statement_period'] ?? null),
        ];
    }

    /**
     * Fill gaps from "Label ,value" lines in the preamble when the LLM returns nulls.
     *
     * @param  array<string, mixed>  $accountInfo
     * @return array<string, mixed>
     */
    private function enrichAccountInfoFromPreambleKeyValues(string $preamble, array $accountInfo): array
    {
        $fromFile = $this->parseKeyValuePreambleLines($preamble);
        foreach ($fromFile as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $current = $accountInfo[$key] ?? null;
            if ($current === null || (is_string($current) && trim($current) === '')) {
                $accountInfo[$key] = $value;
            }
        }

        return $accountInfo;
    }

    /**
     * @return array<string, string|null>
     */
    private function parseKeyValuePreambleLines(string $preamble): array
    {
        $out = [
            'account_holder_name' => null,
            'bank_name' => null,
            'account_number' => null,
            'ifsc_code' => null,
            'account_type' => null,
            'statement_period' => null,
        ];

        foreach (preg_split('/\R/u', $preamble) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ',')) {
                continue;
            }
            $parts = explode(',', $line, 2);
            if (count($parts) < 2) {
                continue;
            }
            $labelRaw = trim($parts[0]);
            $value = trim($parts[1]);
            if ($labelRaw === '' || $value === '') {
                continue;
            }

            $label = mb_strtolower($labelRaw);

            if ($label === 'name') {
                $out['account_holder_name'] = $value;

                continue;
            }
            if (str_contains($label, 'account') && str_contains($label, 'number')) {
                $out['account_number'] = preg_replace('/\s+/', '', $value) ?: $value;

                continue;
            }
            if (str_contains($label, 'ifsc')) {
                $out['ifsc_code'] = strtoupper(preg_replace('/\s+/', '', $value) ?: $value);

                continue;
            }
            if (str_contains($label, 'account') && str_contains($label, 'type')) {
                $out['account_type'] = $value;

                continue;
            }
            if (str_contains($label, 'transaction') && str_contains($label, 'from')) {
                $out['statement_period'] = $value;
            }
        }

        return $out;
    }

    private function buildPreambleAccountPrompt(string $preamble): string
    {
        return <<<PROMPT
Extract account metadata from this Indian bank statement preamble (header section only). Return JSON only.

PREAMBLE:
{$preamble}

Return EXACTLY this JSON (null for unknown fields):
{
  "account_info": {
    "account_holder_name": "string or null",
    "bank_name": "string or null",
    "account_number": "string or null",
    "ifsc_code": "string or null",
    "account_type": "savings|current|credit_card|null",
    "statement_period": "string or null"
  }
}
PROMPT;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseTransactionsFromTableCsv(string $tableCsv): array
    {
        $tableCsv = trim($tableCsv);
        if ($tableCsv === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $tableCsv, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($lines === []) {
            return [];
        }

        $headerLine = $lines[0];
        if ($this->isIndusStyleTrailingAmountHeader($headerLine)) {
            return $this->parseIndusStyleDataLines(array_slice($lines, 1));
        }

        try {
            $csv = Reader::createFromString($tableCsv);
            $csv->setHeaderOffset(0);
        } catch (SyntaxError) {
            return [];
        }

        $maxRows = config('statement.max_parsed_transaction_rows', 5000);
        $out = [];
        $count = 0;

        try {
            foreach ($csv->getRecords() as $row) {
                if ($count >= $maxRows) {
                    break;
                }
                if (! is_array($row)) {
                    continue;
                }
                $norm = $this->normalizeCsvRowKeys($row);

                $parsed = $this->mapRowToTransaction($norm);
                if ($parsed !== null) {
                    $out[] = $parsed;
                    $count++;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * IndusInd-style export: last 3 columns are Debit, Credit, Balance;
     * description may contain unquoted commas (League\Csv would mis-align).
     *
     * @param  array<int, string>  $dataLines
     * @return array<int, array<string, mixed>>
     */
    private function parseIndusStyleDataLines(array $dataLines): array
    {
        $maxRows = config('statement.max_parsed_transaction_rows', 5000);
        $out = [];
        $count = 0;

        foreach ($dataLines as $line) {
            if ($count >= $maxRows) {
                break;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parsed = $this->parseIndusStyleRow($line);
            if ($parsed !== null) {
                $out[] = $parsed;
                $count++;
            }
        }

        return $out;
    }

    private function isIndusStyleTrailingAmountHeader(string $headerLine): bool
    {
        $l = mb_strtolower($headerLine);

        return str_contains($l, 'sr.no')
            && str_contains($l, 'date')
            && (str_contains($l, 'debit') && str_contains($l, 'credit') && str_contains($l, 'balance'));
    }

    /**
     * Parse one data line: SrNo, Date, Type, Description[, more desc...], Debit, Credit, Balance
     */
    private function parseIndusStyleRow(string $line): ?array
    {
        $parts = array_map('trim', explode(',', $line));
        if (count($parts) < 6) {
            return null;
        }

        array_pop($parts); // balance (not used for classification)
        $creditRaw = (string) array_pop($parts);
        $debitRaw = (string) array_pop($parts);

        if (count($parts) < 3) {
            return null;
        }

        $reference = (string) array_shift($parts);
        $dateRaw = (string) array_shift($parts);
        array_shift($parts); // Type column (e.g. Transfer Debit); amounts use Debit/Credit columns

        $description = implode(',', $parts);

        if ($dateRaw === '') {
            return null;
        }

        try {
            $date = Carbon::parse($dateRaw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }

        $debit = $this->parseAmount($debitRaw);
        $credit = $this->parseAmount($creditRaw);

        $incomeExpense = null;
        $amount = null;

        if ($debit !== null && $debit > 0) {
            $incomeExpense = 'expense';
            $amount = $debit;
        } elseif ($credit !== null && $credit > 0) {
            $incomeExpense = 'income';
            $amount = $credit;
        }

        if ($incomeExpense === null || $amount === null || $amount <= 0) {
            return null;
        }

        $ref = $reference !== '' ? $reference : null;

        return [
            'date' => $date,
            'description' => $description,
            'amount' => round($amount, 2),
            'type' => $incomeExpense,
            'reference' => $ref,
        ];
    }

    /**
     * Keys: alphanumeric-only lowercase (e.g. srno, date, debit, credit, description).
     *
     * @param  array<string|int, mixed>  $row
     * @return array<string, string>
     */
    private function normalizeCsvRowKeys(array $row): array
    {
        $norm = [];
        foreach ($row as $k => $v) {
            $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $k));
            if ($key === '') {
                continue;
            }
            $norm[$key] = is_string($v) ? trim($v) : trim((string) $v);
        }

        return $norm;
    }

    /**
     * @param  array<string, string>  $norm
     * @return array{date: string, description: string, amount: float, type: string, reference: string|null}|null
     */
    private function mapRowToTransaction(array $norm): ?array
    {
        $dateRaw = $norm['date'] ?? $norm['txndate'] ?? $norm['transactiondate'] ?? $norm['valuedate'] ?? null;
        if ($dateRaw === null || $dateRaw === '') {
            return null;
        }

        try {
            $date = Carbon::parse($dateRaw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }

        $debit = $this->parseAmount($norm['debit'] ?? $norm['dr'] ?? $norm['withdrawal'] ?? null);
        $credit = $this->parseAmount($norm['credit'] ?? $norm['cr'] ?? $norm['deposit'] ?? null);

        $type = null;
        $amount = null;
        if ($debit !== null && $debit > 0) {
            $type = 'expense';
            $amount = $debit;
        } elseif ($credit !== null && $credit > 0) {
            $type = 'income';
            $amount = $credit;
        }

        if ($type === null || $amount === null || $amount <= 0) {
            return null;
        }

        $description = $norm['description'] ?? $norm['narration'] ?? $norm['particulars'] ?? $norm['remarks'] ?? '';
        if ($description === '') {
            $description = $norm['details'] ?? '';
        }

        $reference = $norm['srno'] ?? $norm['sno'] ?? $norm['serialno'] ?? $norm['ref'] ?? $norm['referenceno'] ?? null;

        return [
            'date' => $date,
            'description' => $description,
            'amount' => round($amount, 2),
            'type' => $type,
            'reference' => $reference !== null && $reference !== '' ? $reference : null,
        ];
    }

    private function parseAmount(?string $raw): ?float
    {
        if ($raw === null || $raw === '' || $raw === '-') {
            return null;
        }
        $clean = preg_replace('/[^\d.\-]/', '', $raw);
        if ($clean === null || $clean === '' || $clean === '-') {
            return null;
        }
        if (! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    /**
     * @param  array<string, mixed>  $accountInfo
     * @param  array<int, array<string, mixed>>  $transactions
     * @return array{account_info: array<string, mixed>, transactions: array<int, array<string, mixed>>}
     */
    private function mergeResult(array $accountInfo, array $transactions): array
    {
        return [
            'account_info' => array_merge($this->emptyAccountInfo(), $accountInfo),
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAccountInfo(): array
    {
        return [
            'account_holder_name' => null,
            'bank_name' => null,
            'account_number' => null,
            'ifsc_code' => null,
            'account_type' => null,
            'statement_period' => null,
        ];
    }

    private function stringOrNull(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (! is_string($v)) {
            return null;
        }
        $t = trim($v);

        return $t === '' ? null : $t;
    }
}
