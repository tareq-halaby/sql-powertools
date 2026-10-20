<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * Builds a machine-readable JSON payload for clone / masking runs.
 *
 * Useful for CI pipelines and automation that prefer structured output
 * alongside the HTML copy report.
 */
final class CloneReportBuilder
{
    /**
     * @param list<array{table: string, created?: bool, copied?: int, error?: ?string}> $tablesReport
     * @param array<string, list<string>> $maskedColumns table => masked column names
     * @param array<string, int|float|string|null> $rowEstimates
     * @return array<string, mixed>
     */
    public function build(
        string $sourceDb,
        string $targetDb,
        int|string $rowLimit,
        array $tablesReport,
        array $maskedColumns = [],
        array $rowEstimates = [],
    ): array {
        $tables = [];
        $ok = 0;
        $errors = 0;
        $rowsCopied = 0;

        foreach ($tablesReport as $row) {
            $table = (string) ($row['table'] ?? '');
            if ($table === '') {
                continue;
            }

            $hasError = !empty($row['error']);
            $copied = (int) ($row['copied'] ?? 0);
            $masked = array_values(array_unique(array_map('strval', (array) ($maskedColumns[$table] ?? []))));

            $entry = [
                'table' => $table,
                'created' => (bool) ($row['created'] ?? false),
                'rows_copied' => $copied,
                'rows_estimated' => array_key_exists($table, $rowEstimates)
                    ? (int) $rowEstimates[$table]
                    : null,
                'masked_columns' => $masked,
                'status' => $hasError ? 'error' : 'ok',
                'error' => $hasError ? (string) $row['error'] : null,
            ];

            $tables[] = $entry;
            $rowsCopied += $copied;
            if ($hasError) {
                $errors++;
            } else {
                $ok++;
            }
        }

        $tablesMasked = 0;
        foreach ($maskedColumns as $cols) {
            if (!empty($cols)) {
                $tablesMasked++;
            }
        }

        return [
            'generated_at' => gmdate('c'),
            'tool' => 'sql-powertools',
            'source_db' => $sourceDb,
            'target_db' => $targetDb,
            'row_limit' => $rowLimit,
            'summary' => [
                'tables' => count($tables),
                'ok' => $ok,
                'errors' => $errors,
                'rows_copied' => $rowsCopied,
                'tables_masked' => $tablesMasked,
            ],
            'tables' => $tables,
            'masked_columns' => $this->normalizeMaskedMap($maskedColumns),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function toJson(array $payload, bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($payload, $flags);
    }

    /**
     * @param array<string, list<string>|mixed> $maskedColumns
     * @return array<string, list<string>>
     */
    private function normalizeMaskedMap(array $maskedColumns): array
    {
        $out = [];
        foreach ($maskedColumns as $table => $cols) {
            $table = (string) $table;
            if ($table === '') {
                continue;
            }
            $list = array_values(array_unique(array_filter(array_map('strval', (array) $cols))));
            if ($list === []) {
                continue;
            }
            $out[$table] = $list;
        }

        return $out;
    }
}
