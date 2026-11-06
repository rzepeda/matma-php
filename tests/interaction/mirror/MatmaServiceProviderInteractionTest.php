<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;

class MatmaServiceProviderInteractionTest extends TestCase
{
    public function test_provider_and_commands_work_together_without_fake_success(): void
    {
        foreach (['matma:index', 'matma:scaffold', 'matma:impact', 'matma:validate'] as $name) {
            self::assertTrue(Artisan::has($name));
            self::assertSame(2, Artisan::call($name));
            self::assertStringContainsString('[not_implemented]', Artisan::output());
        }
    }
}
