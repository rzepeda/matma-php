<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Analysis;

use Matma\Php\Analysis\ProjectInspector;
use PHPUnit\Framework\TestCase;

class ProjectInspectorTest extends TestCase
{
    private function inspector(): ProjectInspector
    {
        return new ProjectInspector(dirname(__DIR__, 4).'/tests/fixtures/analysis-project');
    }

    public function test_valid_project_builds_in_memory_symbol_index_and_mirrors(): void
    {
        $result = $this->inspector()->inspect('app', 'tests');

        self::assertSame(8, $result['summary']['source_files']);
        self::assertSame(0, $result['summary']['errors']);
        self::assertArrayHasKey('App\Services\DocumentParser', $result['symbol_to_source']);

        $service = array_values(array_filter(
            $result['files'],
            static fn (array $file): bool => $file['relative_path'] === 'Services/DocumentParser.php',
        ))[0];

        self::assertSame('tests/mirror/Services/DocumentParser.auto.yaml', $service['mirrors']['manifest']);
        self::assertSame('App\Services\DocumentParser', $service['declarations'][0]['class_name']);
    }

    public function test_multiple_symbols_duplicates_namespaces_and_syntax_errors_are_reported(): void
    {
        $result = $this->inspector()->inspect('invalid');

        $codes = array_column($result['diagnostics'], 'code');
        self::assertContains('multiple_primary_symbols', $codes);
        self::assertContains('duplicate_symbol', $codes);
        self::assertContains('filename_mismatch', $codes);
        self::assertContains('psr4_mismatch', $codes);
        self::assertContains('parse_error', $codes);
        self::assertGreaterThan(5, $result['summary']['errors']);
    }

    public function test_target_class_and_file_filter_the_report(): void
    {
        $byClass = $this->inspector()->inspect('app', 'tests', 'App\Services\DocumentParser');
        $byFile = $this->inspector()->inspect('app', 'tests', 'Services/DocumentParser.php');

        self::assertSame(1, $byClass['summary']['source_files']);
        self::assertSame(1, $byFile['summary']['source_files']);
        self::assertSame('Services/DocumentParser.php', $byClass['files'][0]['relative_path']);
    }

    public function test_unknown_target_returns_an_error_instead_of_an_empty_success(): void
    {
        $result = $this->inspector()->inspect('app', 'tests', 'Unknown\Missing');
        self::assertSame('target_not_found', $result['diagnostics'][0]['code']);
        self::assertSame(1, $result['summary']['errors']);
    }

    public function test_trait_and_enum_are_visible_but_not_primary_test_targets(): void
    {
        $result = $this->inspector()->inspect('app');

        self::assertArrayHasKey('App\Domain\HasState', $result['symbol_to_source']);
        self::assertArrayHasKey('App\Domain\Status', $result['symbol_to_source']);
        self::assertSame(0, $result['summary']['errors']);
    }
}
