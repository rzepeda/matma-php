<?php

declare(strict_types=1);

namespace Matma\Php\Console\Commands;

use Matma\Php\Console\MatmaCommand;

class ValidateCommand extends MatmaCommand
{
    protected const MATMA_NAME = 'matma:validate';
    protected const MATMA_DESCRIPTION = 'Validate structural mapping (Phase 2, not implemented).';
}
