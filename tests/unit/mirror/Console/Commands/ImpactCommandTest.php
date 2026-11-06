<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Console\Commands;

use Matma\Php\Console\Commands\ImpactCommand;
use Matma\Php\Console\MatmaCommand;
use PHPUnit\Framework\TestCase;

class ImpactCommandTest extends TestCase
{
    public function test_impact_shell_is_named_and_has_common_options(): void
    {
        $command = new ImpactCommand();

        self::assertInstanceOf(MatmaCommand::class, $command);
        self::assertSame('matma:impact', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('level'));
    }
}
