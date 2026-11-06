<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;

class ValidateCommandInteractionTest extends TestCase
{
    public function test_invalid_arguments_report_a_nonzero_exit_code(): void
    {
        $exit = Artisan::call('matma:validate', [
            '--level' => '-1',
            '--format' => 'json',
        ]);

        self::assertSame(64, $exit);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('invalid_options', $payload['status']);
        self::assertSame(64, $payload['exit_code']);
    }
}
