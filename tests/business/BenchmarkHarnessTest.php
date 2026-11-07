<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Business;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BenchmarkHarnessTest extends TestCase
{
    public function test_benchmark_reports_separate_measured_ast_index_and_manifest_lookup_times(): void
    {
        $root = dirname(__DIR__, 2);
        $command = new Process([
            PHP_BINARY,
            $root.'/benchmarks/benchmark.php',
            '--classes=12',
            '--iterations=2',
        ], $root);
        $command->run();

        self::assertSame(0, $command->getExitCode(), $command->getOutput().$command->getErrorOutput());
        $result = json_decode($command->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('measured', $result['status']);
        self::assertSame(12, $result['source_classes']);
        self::assertSame(2, $result['iterations']);
        self::assertSame('selected', $result['impact_status']);
        self::assertFalse($result['ast_reparsed_during_impact']);
        self::assertGreaterThanOrEqual(0, $result['index_ast_and_write_median_ms']);
        self::assertGreaterThanOrEqual(0, $result['impact_manifest_and_hash_median_ms']);
        self::assertSame('milliseconds', $result['unit']);
    }

    public function test_benchmark_rejects_unsafe_unbounded_sizes(): void
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([
            PHP_BINARY,
            $root.'/benchmarks/benchmark.php',
            '--classes=100000',
        ], $root);
        $process->run();

        self::assertSame(64, $process->getExitCode());
        self::assertStringContainsString('classes must be', $process->getErrorOutput());
    }
}
