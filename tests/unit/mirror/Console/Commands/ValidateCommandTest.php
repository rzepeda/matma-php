<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Console\Commands;

use Matma\Php\Console\Commands\ValidateCommand;
use Matma\Php\Console\MatmaCommand;
use PHPUnit\Framework\TestCase;

class ValidateCommandTest extends TestCase
{
    public function test_validate_shell_is_named_and_has_common_options(): void
    {
        $command = new ValidateCommand();

        self::assertInstanceOf(MatmaCommand::class, $command);
        self::assertSame('matma:validate', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('check'));
    }
}
