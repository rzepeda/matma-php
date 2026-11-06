<?php

declare(strict_types=1);

namespace Matma\Php\Console\Commands;

class ScaffoldCommand extends IndexCommand
{
    protected const MATMA_NAME = 'matma:scaffold';
    protected const MATMA_DESCRIPTION = 'Generate missing mirrored tests, centralized mocks and refreshed YAML manifests.';

    public function handle(): int
    {
        return $this->executeIndex(true);
    }
}
