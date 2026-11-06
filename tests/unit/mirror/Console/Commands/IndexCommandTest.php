<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Console\Commands;

use Matma\Php\Console\Commands\IndexCommand;
use Matma\Php\Console\MatmaCommand;
use PHPUnit\Framework\TestCase;

class IndexCommandTest extends TestCase
{
    public function test_index_shell_is_named_and_has_common_options(): void
    {
        $command = new IndexCommand();

        self::assertInstanceOf(MatmaCommand::class, $command);
        self::assertSame('matma:index', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('source-root'));
    }
}
