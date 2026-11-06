<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Console\Commands;

use Matma\Php\Console\Commands\ScaffoldCommand;
use Matma\Php\Console\MatmaCommand;
use PHPUnit\Framework\TestCase;

class ScaffoldCommandTest extends TestCase
{
    public function test_scaffold_shell_is_named_and_has_common_options(): void
    {
        $command = new ScaffoldCommand();

        self::assertInstanceOf(MatmaCommand::class, $command);
        self::assertSame('matma:scaffold', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('scaffold'));
    }
}
