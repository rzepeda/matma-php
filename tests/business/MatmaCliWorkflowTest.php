<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Business;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;

class MatmaCliWorkflowTest extends TestCase
{
    public function test_four_commands_are_discoverable_but_no_engine_reports_success(): void
    {
        foreach (['matma:index', 'matma:scaffold', 'matma:impact', 'matma:validate'] as $name) {
            $exit = Artisan::call($name, ['--format' => 'json']);
            self::assertSame(2, $exit, $name);

            $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($name, $payload['command']);
            self::assertSame('not_implemented', $payload['status']);
        }
    }
}
