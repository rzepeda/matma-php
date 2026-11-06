<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;

class MatmaServiceProviderInteractionTest extends TestCase
{
    public function test_commands_are_registered_and_impact_requires_explicit_target(): void
    {
        self::assertSame(0, Artisan::call('list', ['--raw' => true]));
        $available = Artisan::output();

        foreach (['matma:index', 'matma:scaffold', 'matma:impact', 'matma:validate'] as $name) {
            self::assertStringContainsString($name, $available);
        }

        foreach (['matma:impact'] as $name) {
            self::assertSame(64, Artisan::call($name));
            self::assertStringContainsString('[invalid_options]', Artisan::output());
        }

        $sourceRoot = dirname(__DIR__, 3).'/src';
        self::assertSame(0, Artisan::call('matma:validate', [
            '--source-root' => $sourceRoot,
            '--check' => true,
            '--format' => 'json',
        ]));
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ok', $payload['status']);
        self::assertSame(0, $payload['summary']['errors']);
    }
}
