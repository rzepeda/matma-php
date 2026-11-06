<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;

class ScaffoldCommandInteractionTest extends TestCase
{
    public function test_scaffold_shell_never_claims_to_have_generated_a_test(): void
    {
        $exit = Artisan::call('matma:scaffold', [
            '--scaffold' => true,
            '--target' => 'App\\Services\\DocumentParser',
            '--format' => 'json',
        ]);

        self::assertSame(2, $exit);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('not_implemented', $payload['status']);
        self::assertTrue($payload['options']['scaffold']);
        self::assertSame('App\\Services\\DocumentParser', $payload['options']['target']);
    }
}
