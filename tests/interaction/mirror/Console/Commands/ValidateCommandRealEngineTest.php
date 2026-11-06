<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

class ValidateCommandRealEngineTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 5).'/tests/fixtures/analysis-project';
    }

    public function test_strict_check_passes_on_real_valid_ast(): void
    {
        $exit = Artisan::call('matma:validate', [
            '--source-root' => $this->root().'/app',
            '--tests-root' => 'test-tree',
            '--check' => true,
            '--format' => 'json',
        ]);

        self::assertSame(0, $exit);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ok', $payload['status']);
        self::assertSame(8, $payload['summary']['source_files']);
        self::assertSame(0, $payload['summary']['errors']);
    }

    public function test_strict_check_returns_one_for_structural_errors(): void
    {
        $exit = Artisan::call('matma:validate', [
            '--source-root' => $this->root().'/invalid',
            '--check' => true,
            '--format' => 'yaml',
        ]);

        self::assertSame(1, $exit);
        $payload = Yaml::parse(Artisan::output());
        self::assertSame('violations', $payload['status']);
        self::assertGreaterThan(0, $payload['summary']['errors']);
        self::assertContains('parse_error', array_column($payload['diagnostics'], 'code'));
    }

    public function test_non_strict_reports_errors_without_failing_the_command(): void
    {
        $exit = Artisan::call('matma:validate', [
            '--source-root' => $this->root().'/invalid',
            '--format' => 'json',
        ]);
        self::assertSame(0, $exit);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('violations', $payload['status']);
        self::assertGreaterThan(0, $payload['summary']['errors']);
    }

    public function test_text_mode_lists_specific_files_and_line_numbers(): void
    {
        $exit = Artisan::call('matma:validate', [
            '--source-root' => $this->root().'/invalid',
            '--check' => true,
        ]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('[parse_error]', Artisan::output());
        self::assertStringContainsString('Broken.php:', Artisan::output());
    }

    public function test_unknown_class_target_fails_even_without_check(): void
    {
        $exit = Artisan::call('matma:validate', [
            '--source-root' => $this->root().'/app',
            '--target' => 'Unknown\Missing',
            '--format' => 'json',
        ]);

        self::assertSame(1, $exit);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('target_not_found', $payload['diagnostics'][0]['code']);
    }

    public function test_missing_source_root_is_an_error(): void
    {
        $exit = Artisan::call('matma:validate', [
            '--source-root' => $this->root().'/missing',
            '--format' => 'json',
        ]);

        self::assertSame(1, $exit);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('invalid_source_root', $payload['status']);
    }
}
