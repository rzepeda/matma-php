<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Analysis;

use Matma\Php\Analysis\PhpAstAnalyzer;
use PHPUnit\Framework\TestCase;

class PhpAstAnalyzerTest extends TestCase
{
    public function test_names_aliases_and_actual_resolved_references_are_discovered(): void
    {
        $path = dirname(__DIR__, 4).'/tests/fixtures/analysis-project/app/Services/DocumentParser.php';
        $result = (new PhpAstAnalyzer())->analyze($path, 'app/Services/DocumentParser.php');

        self::assertSame([], $result['diagnostics']);
        self::assertSame('App\Services\DocumentParser', $result['declarations'][0]['class_name']);
        self::assertSame('class', $result['declarations'][0]['kind']);
        self::assertSame('anonymous', $result['declarations'][1]['kind']);
        self::assertSame('anonymous#1', $result['declarations'][1]['symbol_id']);

        self::assertContains([
            'alias' => 'Contract',
            'class_name' => 'App\Contracts\ParserContract',
            'kind' => 'class',
        ], $result['imports']);
        self::assertContains([
            'alias' => 'Log',
            'class_name' => 'App\Domain\Tools\Logger',
            'kind' => 'class',
        ], $result['imports']);
        foreach ([
            'App\Contracts\ParserContract',
            'App\Domain\BaseService',
            'App\Domain\Tools\Logger',
            'App\Domain\Tools\Formatter',
            'App\Domain\HasState',
        ] as $name) {
            self::assertContains($name, $result['references'], $name);
        }
    }

    public function test_imports_without_actual_use_are_not_dependencies(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'matma_ast_');
        self::assertNotFalse($path);
        file_put_contents($path, '<?php namespace App; use Vendor\Unused; class A {}');

        try {
            $parsed = (new PhpAstAnalyzer())->analyze($path, 'app/A.php');
            self::assertCount(1, $parsed['imports']);
            self::assertNotContains('Vendor\Unused', $parsed['references']);
        } finally {
            unlink($path);
        }
    }

    public function test_anonymous_only_file_has_stable_file_local_identifiers(): void
    {
        $path = dirname(__DIR__, 4).'/tests/fixtures/analysis-project/app/Anonymous/Factory.php';
        $parsed = (new PhpAstAnalyzer())->analyze($path, 'app/Anonymous/Factory.php');

        self::assertCount(2, $parsed['declarations']);
        self::assertSame(['anonymous#1', 'anonymous#2'], array_column($parsed['declarations'], 'symbol_id'));
        self::assertSame([null, null], array_column($parsed['declarations'], 'class_name'));
    }

    public function test_invalid_php_returns_a_diagnostic_instead_of_throwing(): void
    {
        $path = dirname(__DIR__, 4).'/tests/fixtures/analysis-project/invalid/Broken.php';
        $parsed = (new PhpAstAnalyzer())->analyze($path, 'invalid/Broken.php');

        self::assertSame('parse_error', $parsed['diagnostics'][0]['code']);
        self::assertGreaterThan(0, $parsed['diagnostics'][0]['line']);
    }
}
