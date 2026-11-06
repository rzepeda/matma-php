<?php

declare(strict_types=1);

namespace Matma\Php\Console;

use InvalidArgumentException;

/**
 * Common, intentionally side-effect-free input contract for MATMA commands.
 * File discovery and index generation are later-phase responsibilities.
 */
final class MatmaOptions
{
    public const SIGNATURE =
        ' {--s|source-root=app/ : Production source root}' .
        ' {--t|tests-root=tests/ : Tests and manifest root}' .
        ' {--S|scaffold : Generate missing mirrors}' .
        ' {--l|level=0 : Inclusive reverse-dependency depth}' .
        ' {--f|target= : Source file or class to inspect}' .
        ' {--c|check : Fail when structural invariants are violated}' .
        ' {--o|format=text : Output format: text, json or yaml}';

    public function __construct(
        public readonly string $sourceRoot,
        public readonly string $testsRoot,
        public readonly bool $scaffold,
        public readonly int $level,
        public readonly ?string $target,
        public readonly bool $check,
        public readonly string $format,
    ) {
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        $sourceRoot = self::requiredText($values['source-root'] ?? 'app/', '--source-root');
        $testsRoot = self::requiredText($values['tests-root'] ?? 'tests/', '--tests-root');

        $rawLevel = $values['level'] ?? 0;
        if (! is_int($rawLevel) && ! is_string($rawLevel)) {
            throw new InvalidArgumentException('--level must be a non-negative integer.');
        }

        $level = filter_var($rawLevel, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        if ($level === false) {
            throw new InvalidArgumentException('--level must be a non-negative integer.');
        }

        $rawTarget = $values['target'] ?? null;
        $target = $rawTarget === null ? null : self::requiredText($rawTarget, '--target');

        $format = self::requiredText($values['format'] ?? 'text', '--format');
        if (! in_array($format, ['text', 'json', 'yaml'], true)) {
            throw new InvalidArgumentException('--format must be one of: text, json, yaml.');
        }

        return new self(
            $sourceRoot,
            $testsRoot,
            self::flag($values['scaffold'] ?? false, '--scaffold'),
            $level,
            $target,
            self::flag($values['check'] ?? false, '--check'),
            $format,
        );
    }

    /** @return array<string, string|bool|int|null> */
    public function toArray(): array
    {
        return [
            'source_root' => $this->sourceRoot,
            'tests_root' => $this->testsRoot,
            'scaffold' => $this->scaffold,
            'level' => $this->level,
            'target' => $this->target,
            'check' => $this->check,
            'format' => $this->format,
        ];
    }

    private static function requiredText(mixed $value, string $flag): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s must contain a non-empty value.', $flag));
        }

        return trim($value);
    }

    private static function flag(mixed $value, string $name): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a boolean flag.', $name));
        }

        return $value;
    }
}
