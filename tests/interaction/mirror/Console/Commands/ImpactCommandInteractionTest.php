<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

class ImpactCommandInteractionTest extends TestCase
{
    public function test_impact_shell_produces_machine_readable_yaml(): void
    {
        $exit = Artisan::call('matma:impact', ['--level' => '1', '--format' => 'yaml']);

        self::assertSame(2, $exit);
        $payload = Yaml::parse(Artisan::output());
        self::assertSame('matma:impact', $payload['command']);
        self::assertSame('not_implemented', $payload['status']);
        self::assertSame(1, $payload['options']['level']);
    }
}
