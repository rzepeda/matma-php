<?php

declare(strict_types=1);

namespace Matma\Php\Tests;

use Matma\Php\MatmaServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /** @param \Illuminate\Foundation\Application $app
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MatmaServiceProvider::class];
    }
}
