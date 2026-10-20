<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use SqlPowertools\CloneReportBuilder;

/**
 * Lightweight unit tests for CloneReportBuilder (no PHPUnit required).
 */
final class CloneReportBuilderTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int
    {
        $this->testBuildSummaryAndMaskedColumns();
        $this->testToJsonIsValid();
        $this->testSkipsEmptyTableNames();

        echo PHP_EOL . "Passed: {$this->passed}; Failed: {$this->failed}" . PHP_EOL;

        return $this->failed === 0 ? 0 : 1;
    }

    private function testBuildSummaryAndMaskedColumns(): void
    {
        $builder = new CloneReportBuilder();
        $payload = $builder->build(
            'app_prod',
            'app_sample',
            100,
            [
                ['table' => 'users', 'created' => true, 'copied' => 100, 'error' => null],
                ['table' => 'orders', 'created' => true, 'copied' => 50, 'error' => 'FK fail'],
            ],
            [
                'users' => ['password', 'email'],
                'orders' => [],
            ],
            [
                'users' => 5000,
                'orders' => 200,
            ]
        );

        $this->assertSame('app_prod', $payload['source_db']);
        $this->assertSame('app_sample', $payload['target_db']);
        $this->assertSame(100, $payload['row_limit']);
        $this->assertSame(2, $payload['summary']['tables']);
        $this->assertSame(1, $payload['summary']['ok']);
        $this->assertSame(1, $payload['summary']['errors']);
        $this->assertSame(150, $payload['summary']['rows_copied']);
        $this->assertSame(1, $payload['summary']['tables_masked']);
        $this->assertSame(['password', 'email'], $payload['tables'][0]['masked_columns']);
        $this->assertSame('error', $payload['tables'][1]['status']);
        $this->assertSame(5000, $payload['tables'][0]['rows_estimated']);
        $this->assertArrayHasKey('generated_at', $payload);
    }

    private function testToJsonIsValid(): void
    {
        $builder = new CloneReportBuilder();
        $payload = $builder->build('a', 'b', 'all', [
            ['table' => 't1', 'created' => true, 'copied' => 3, 'error' => null],
        ]);
        $json = $builder->toJson($payload);
        $decoded = json_decode($json, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertSame('all', $decoded['row_limit']);
        $this->assertSame(3, $decoded['summary']['rows_copied']);
    }

    private function testSkipsEmptyTableNames(): void
    {
        $builder = new CloneReportBuilder();
        $payload = $builder->build('a', 'b', 10, [
            ['table' => '', 'created' => true, 'copied' => 1, 'error' => null],
            ['table' => 'ok', 'created' => true, 'copied' => 2, 'error' => null],
        ]);
        $this->assertSame(1, $payload['summary']['tables']);
        $this->assertSame('ok', $payload['tables'][0]['table']);
    }

    private function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            $this->passed++;
            echo '.';

            return;
        }

        $this->failed++;
        $msg = $message !== '' ? $message : 'assertSame failed';
        echo 'F';
        fwrite(STDERR, PHP_EOL . $msg . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true) . PHP_EOL);
    }

    private function assertArrayHasKey(string|int $key, array $array): void
    {
        if (array_key_exists($key, $array)) {
            $this->passed++;
            echo '.';

            return;
        }

        $this->failed++;
        echo 'F';
        fwrite(STDERR, PHP_EOL . "Missing key: {$key}" . PHP_EOL);
    }
}

exit((new CloneReportBuilderTest())->run());
