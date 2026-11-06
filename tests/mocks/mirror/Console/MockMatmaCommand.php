<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Mocks\Console;

use Matma\Php\Console\MatmaCommand;

/** A controlled command double for isolating shared command wiring. */
class MockMatmaCommand extends MatmaCommand
{
    protected const MATMA_NAME = 'matma:mock';
    protected const MATMA_DESCRIPTION = 'Shared command test double.';
}
