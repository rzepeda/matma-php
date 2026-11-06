<?php

declare(strict_types=1);

namespace Matma\Php;

use Illuminate\Support\ServiceProvider;
use Matma\Php\Console\Commands\ImpactCommand;
use Matma\Php\Console\Commands\IndexCommand;
use Matma\Php\Console\Commands\ScaffoldCommand;
use Matma\Php\Console\Commands\ValidateCommand;

class MatmaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            IndexCommand::class,
            ScaffoldCommand::class,
            ImpactCommand::class,
            ValidateCommand::class,
        ]);
    }
}
