<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;

class IndexCommandInteractionTest extends TestCase
{
    public function test_real_options_and_output_are_wired_to_index_command(): void
    {
        $exit = Artisan::call('matma:index', [
            '--source-root' => 'app/Domain',
            '--level' => '2',
            '--format' => 'json',
        ]);

        self::assertSame(2, $exit);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('matma:index', $payload['command']);
        self::assertSame('not_implemented', $payload['status']);
        self::assertSame('app/Domain', $payload['options']['source_root']);
        self::assertSame(2, $payload['options']['level']);
    }
}
