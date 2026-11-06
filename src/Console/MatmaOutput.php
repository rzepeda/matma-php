<?php

declare(strict_types=1);

namespace Matma\Php\Console;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\Yaml\Yaml;

final class MatmaOutput
{
    /** @param array<string, mixed> $payload
     * @throws JsonException
     */
    public static function render(string $format, array $payload): string
    {
        return match ($format) {
            'json' => json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            'yaml' => Yaml::dump($payload, 4, 2),
            'text' => sprintf(
                '%s [%s] %s',
                (string) ($payload['command'] ?? 'matma'),
                (string) ($payload['status'] ?? 'unknown'),
                (string) ($payload['message'] ?? ''),
            ),
            default => throw new InvalidArgumentException('Unsupported output format.'),
        };
    }
}
