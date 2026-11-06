<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Middleware $middleware): void {
        // Intentionally empty: this fixture only exercises Artisan commands.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Register Laravel's default exception-handler binding.
    })
    ->create();
