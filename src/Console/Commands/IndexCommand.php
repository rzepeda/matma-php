<?php

declare(strict_types=1);

namespace Matma\Php\Console\Commands;

use Matma\Php\Console\MatmaCommand;

class IndexCommand extends MatmaCommand
{
    protected const MATMA_NAME = 'matma:index';
    protected const MATMA_DESCRIPTION = 'Build reverse dependency manifests (Phase 2/3, not implemented).';
}
