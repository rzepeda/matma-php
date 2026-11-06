<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Console;

use Matma\Php\Tests\Mocks\Console\MockMatmaCommand;
use Matma\Php\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MatmaCommandInteractionTest extends TestCase
{
    public function test_shared_shell_uses_real_options_and_output_without_an_engine(): void
    {
        $command = new MockMatmaCommand();
        $command->setLaravel($this->app);

        $tester = new CommandTester($command);
        $exit = $tester->execute(['--format' => 'json', '--level' => '3']);

        self::assertSame(2, $exit);
        $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('matma:mock', $payload['command']);
        self::assertSame(3, $payload['options']['level']);
    }
}
