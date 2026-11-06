<?php

declare(strict_types=1);

namespace MatmaFixture\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MatmaInstalledTest extends TestCase
{
    private const ARTISAN_COMMANDS = [
        'matma:index',
        'matma:scaffold',
        'matma:impact',
        'matma:validate',
    ];

    public function test_all_four_commands_are_available_via_artisan(): void
    {
        $result = $this->artisanProcess('list', '--raw');

        self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());

        foreach (self::ARTISAN_COMMANDS as $command) {
            self::assertStringContainsString($command, $result->getOutput());
        }
    }

    public function test_the_installed_shell_does_not_pretend_to_implement_indexing(): void
    {
        $result = $this->artisanProcess('matma:index', '--format=json', '--level=2');

        self::assertSame(2, $result->getExitCode(), $result->getErrorOutput());
        $payload = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('not_implemented', $payload['status']);
        self::assertSame(2, $payload['options']['level']);
    }

    public function test_bad_options_fail_with_an_actionable_status(): void
    {
        $result = $this->artisanProcess('matma:validate', '--level=-1', '--format=json');

        self::assertSame(64, $result->getExitCode(), $result->getErrorOutput());
        $payload = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('invalid_options', $payload['status']);
    }

    private function artisanProcess(string ...$args): Process
    {
        $projectRoot = dirname(__DIR__, 2);
        $process = new Process([PHP_BINARY, 'artisan', ...$args], $projectRoot);
        $process->run();

        return $process;
    }
}
