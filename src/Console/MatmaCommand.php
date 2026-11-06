<?php

declare(strict_types=1);

namespace Matma\Php\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Phase 1 command shell. Deliberately returns a nonzero code until its engine
 * is implemented. No index, tests or mocks are generated in this phase.
 */
abstract class MatmaCommand extends Command
{
    public const EXIT_NOT_IMPLEMENTED = 2;
    public const EXIT_INVALID_OPTIONS = 64;

    protected const MATMA_NAME = '';
    protected const MATMA_DESCRIPTION = '';

    public function __construct()
    {
        $this->signature = static::MATMA_NAME . MatmaOptions::SIGNATURE;
        $this->description = static::MATMA_DESCRIPTION;
        parent::__construct();
    }

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

        return $this->report(
            'not_implemented',
            'Command shell available; MATMA engine is not implemented yet.',
            self::EXIT_NOT_IMPLEMENTED,
            $options,
        );
    }

    private function report(
        string $status,
        string $message,
        int $exitCode,
        ?MatmaOptions $options,
    ): int {
        $format = $this->option('format');
        $format = is_string($format) && in_array($format, ['text', 'json', 'yaml'], true)
            ? $format
            : 'text';

        $payload = [
            'command' => (string) $this->getName(),
            'status' => $status,
            'message' => $message,
            'exit_code' => $exitCode,
        ];

        if ($options !== null) {
            $payload['options'] = $options->toArray();
        }

        $this->line(MatmaOutput::render($format, $payload));

        return $exitCode;
    }
}
