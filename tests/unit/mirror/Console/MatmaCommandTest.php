<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Console;

use Matma\Php\Console\MatmaCommand;
use Matma\Php\Tests\Mocks\Console\MockMatmaCommand;
use PHPUnit\Framework\TestCase;

class MatmaCommandTest extends TestCase
{
    public function test_shared_command_registers_all_seven_flags_and_shortcuts(): void
    {
        $command = new MockMatmaCommand();

        self::assertInstanceOf(MatmaCommand::class, $command);
        self::assertSame('matma:mock', $command->getName());

        foreach ([
            'source-root' => 's',
            'tests-root' => 't',
            'scaffold' => 'S',
            'level' => 'l',
            'target' => 'f',
            'check' => 'c',
            'format' => 'o',
        ] as $flag => $shortcut) {
            self::assertTrue($command->getDefinition()->hasOption($flag), $flag);
            self::assertSame($shortcut, $command->getDefinition()->getOption($flag)->getShortcut());
        }
    }
}
