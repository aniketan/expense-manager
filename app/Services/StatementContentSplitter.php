<?php

namespace App\Services;

class StatementContentSplitter
{
    /**
     * @return array{preamble: string, table_csv: string|null, header_line_index: int|null}
     */
    public function splitCsvContent(string $raw): array
    {
        $lines = $this->splitLines($raw);
        $headerIndex = $this->findTransactionHeaderLineIndex($lines);

        if ($headerIndex === null) {
            return [
                'preamble' => $this->capPreamble(mb_substr($raw, 0, config('statement.preamble_max_chars'))),
                'table_csv' => null,
                'header_line_index' => null,
            ];
        }

        $preambleLines = array_slice($lines, 0, $headerIndex);
        $preamble = $this->capPreamble(implode("\n", $preambleLines));

        $tableLines = array_slice($lines, $headerIndex);
        $tableCsv = implode("\n", $tableLines);

        return [
            'preamble' => $preamble,
            'table_csv' => $tableCsv !== '' ? $tableCsv : null,
            'header_line_index' => $headerIndex,
        ];
    }

    /**
     * @return array{preamble: string, body: string}
     */
    public function splitPdfText(string $text): array
    {
        $lines = $this->splitLines($text);
        $headerIndex = $this->findTransactionHeaderLineIndex($lines);

        if ($headerIndex === null) {
            $max = config('statement.preamble_max_chars', 8192);

            return [
                'preamble' => $this->capPreamble(mb_substr($text, 0, $max)),
                'body' => mb_substr($text, $max),
            ];
        }

        $preamble = $this->capPreamble(implode("\n", array_slice($lines, 0, $headerIndex)));
        $body = implode("\n", array_slice($lines, $headerIndex));

        return [
            'preamble' => $preamble,
            'body' => $body,
        ];
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function findTransactionHeaderLineIndex(array $lines): ?int
    {
        $required = array_map(strtolower(...), config('statement.csv_header_required_substrings', ['date', 'debit', 'credit']));
        $optional = array_map(strtolower(...), config('statement.csv_header_optional_substrings', []));

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $norm = mb_strtolower($trimmed);
            foreach (config('statement.csv_transaction_start_markers', []) as $marker) {
                if (str_contains($norm, mb_strtolower($marker))) {
                    continue 2;
                }
            }

            $hasRequired = true;
            foreach ($required as $sub) {
                if (! str_contains($norm, $sub)) {
                    $hasRequired = false;

                    break;
                }
            }
            if (! $hasRequired) {
                continue;
            }

            if ($optional !== []) {
                $hasOptional = false;
                foreach ($optional as $sub) {
                    if (str_contains($norm, $sub)) {
                        $hasOptional = true;

                        break;
                    }
                }
                if (! $hasOptional) {
                    continue;
                }
            }

            return $i;
        }

        foreach ($lines as $i => $line) {
            $norm = mb_strtolower(trim($line));
            if ($norm === '') {
                continue;
            }
            $hasRequired = true;
            foreach ($required as $sub) {
                if (! str_contains($norm, $sub)) {
                    $hasRequired = false;

                    break;
                }
            }
            if ($hasRequired) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function splitLines(string $raw): array
    {
        $raw = preg_replace("/^\xEF\xBB\xBF/", '', $raw) ?? $raw;

        return preg_split('/\R/u', $raw) ?: [];
    }

    private function capPreamble(string $preamble): string
    {
        $max = config('statement.preamble_max_chars', 8192);

        if (mb_strlen($preamble) <= $max) {
            return $preamble;
        }

        return mb_substr($preamble, 0, $max);
    }
}
