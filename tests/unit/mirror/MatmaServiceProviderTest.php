<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\MatmaServiceProvider;
use Matma\Php\Tests\TestCase;

class MatmaServiceProviderTest extends TestCase
{
    public function test_provider_registers_all_four_artisan_commands(): void
    {
        self::assertInstanceOf(MatmaServiceProvider::class, $this->app->getProvider(MatmaServiceProvider::class));

        foreach (['matma:index', 'matma:scaffold', 'matma:impact', 'matma:validate'] as $name) {
            self::assertTrue(Artisan::has($name), $name);
        }
    }
}
