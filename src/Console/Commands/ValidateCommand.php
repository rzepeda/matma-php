<?php

declare(strict_types=1);

namespace Matma\Php\Console\Commands;

use InvalidArgumentException;
use Matma\Php\Analysis\ProjectInspector;
use Matma\Php\Console\MatmaCommand;
use Matma\Php\Console\MatmaOptions;

class ValidateCommand extends MatmaCommand
{
    protected const MATMA_NAME = 'matma:validate';
    protected const MATMA_DESCRIPTION = 'Audit PHP class-file invariants, Composer PSR-4 naming and mirrored paths.';

    public function handle(): int
    {
        try {
            $options = MatmaOptions::fromArray($this->options());
        } catch (InvalidArgumentException $exception) {
            return $this->report(
                'invalid_options',
                $exception->getMessage(),
                self::EXIT_INVALID_OPTIONS,
                null,
            );
        }

        try {
            $result = (new ProjectInspector(base_path()))->inspect(
                $options->sourceRoot,
                $options->testsRoot,
                $options->target,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->report('invalid_source_root', $exception->getMessage(), 1, $options);
        }

        $errors = $result['summary']['errors'];
        $missingTarget = count(array_filter(
            $result['diagnostics'],
            static fn (array $d): bool => $d['code'] === 'target_not_found',
        )) > 0;

        $exitCode = $missingTarget || ($options->check && $errors > 0) ? 1 : 0;

        return $this->report(
            $missingTarget ? 'error' : ($errors > 0 ? 'violations' : 'ok'),
            sprintf(
                'Inspected %d PHP files; %d errors, %d warnings. %s',
                $result['summary']['source_files'],
                $errors,
                $result['summary']['warnings'],
                $options->check ? 'Strict check enabled.' : 'Use --check to fail on violations.',
            ),
            $exitCode,
            $options,
            [
                'summary' => $result['summary'],
                'files' => array_map(static fn (array $file): array => [
                    'source_file' => $file['source_file'],
                    'relative_path' => $file['relative_path'],
                    'declarations' => $file['declarations'],
                    'imports' => $file['imports'],
                    'references' => $file['references'],
                    'mirrors' => $file['mirrors'],
                ], $result['files']),
                'symbol_to_source' => $result['symbol_to_source'],
                'diagnostics' => $result['diagnostics'],
            ],
        );
    }
}
