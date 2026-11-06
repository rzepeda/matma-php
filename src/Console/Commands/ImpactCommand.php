<?php

declare(strict_types=1);

namespace Matma\Php\Console\Commands;

use Matma\Php\Console\MatmaCommand;

class ImpactCommand extends MatmaCommand
{
    protected const MATMA_NAME = 'matma:impact';
    protected const MATMA_DESCRIPTION = 'Query indexed reverse dependencies (Phase 5, not implemented).';
}
