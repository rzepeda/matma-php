<?php

declare(strict_types=1);

namespace Matma\Php\Console\Commands;

use InvalidArgumentException;
use Matma\Php\Console\MatmaCommand;
use Matma\Php\Console\MatmaOptions;
use Matma\Php\Impact\ImpactException;
use Matma\Php\Impact\ImpactResolver;
use Matma\Php\Impact\ManifestLocator;

class ImpactCommand extends MatmaCommand
{
    protected const MATMA_NAME = 'matma:impact';
    protected const MATMA_DESCRIPTION = 'Select impacted tests from persisted YAML without parsing PHP source.';

    public function handle(): int
    {
        try {
            $options = MatmaOptions::fromArray($this->options());
        } catch (InvalidArgumentException $exception) {
            return $this->report('invalid_options', $exception->getMessage(), self::EXIT_INVALID_OPTIONS, null);
        }

        if ($options->target === null || $options->scaffold) {
            return $this->report(
                'invalid_options',
                '--target is required for impact; --scaffold is not valid for read-only impact queries.',
                self::EXIT_INVALID_OPTIONS,
                $options,
            );
        }

        try {
            $locator = new ManifestLocator(base_path(), $options->sourceRoot, $options->testsRoot);
            $result = (new ImpactResolver(base_path()))->resolve(
                $locator,
                $options->target,
                $options->level,
            );
            return $this->report(
                'selected',
                sprintf(
                    'Selected %d unit tests and %d interaction tests for %d affected sources (inclusive level %d); no tests executed.',
                    $result['summary']['unit_tests'],
                    $result['summary']['interaction_tests'],
                    $result['summary']['affected_sources'],
                    $options->level,
                ),
                0,
                $options,
                $result,
            );
        } catch (ImpactException $exception) {
            return $this->report($exception->status, $exception->getMessage(), 1, $options);
        }
    }
}
