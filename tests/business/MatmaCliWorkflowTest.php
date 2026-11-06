<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Business;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;

class MatmaCliWorkflowTest extends TestCase
{
    public function test_command_shells_stay_explicit_while_validation_runs_real_ast_checks(): void
    {
        foreach (['matma:index', 'matma:scaffold', 'matma:impact'] as $name) {
            $exit = Artisan::call($name, ['--format' => 'json']);
            self::assertSame(2, $exit, $name);

            $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($name, $payload['command']);
            self::assertSame('not_implemented', $payload['status']);
        }

        $sourceRoot = dirname(__DIR__, 2).'/src';
        self::assertSame(0, Artisan::call('matma:validate', [
            '--source-root' => $sourceRoot,
            '--check' => true,
            '--format' => 'json',
        ]));
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('matma:validate', $payload['command']);
        self::assertSame('ok', $payload['status']);
        self::assertGreaterThan(0, $payload['summary']['source_files']);
    }
}
