<?php

declare(strict_types=1);

namespace Matma\Php\Analysis;

final class StructureValidator
{
    public function __construct(private readonly Psr4Resolver $psr4)
    {
    }

    /**
     * @param list<array<string, mixed>> $files
     * @return list<array{severity: string, code: string, source_file: string, line: int, message: string}>
     */
    public function validate(array $files): array
    {
        $diagnostics = [];
        $symbols = [];

        foreach ($files as $file) {
            $namedTargets = [];

            foreach ($file['declarations'] as $declaration) {
                $className = $declaration['class_name'];

                if ($className !== null) {
                    $key = strtolower($className);
                    $symbols[$key][] = [
                        'source_file' => $file['source_file'],
                        'line' => $declaration['start_line'],
                        'name' => $className,
                    ];
                }

                if (in_array($declaration['kind'], ['class', 'abstract_class', 'interface'], true)) {
                    $namedTargets[] = $declaration;
                }
            }

            if (count($namedTargets) > 1) {
                $diagnostics[] = $this->diagnostic(
                    'multiple_primary_symbols',
                    $file['source_file'],
                    $namedTargets[1]['start_line'],
                    'Each PHP source file may declare only one named testable class or interface.',
                );
            }

            if (count($namedTargets) !== 1) {
                continue;
            }

            $primary = $namedTargets[0];
            $name = $primary['class_name'];
            $shortName = substr($name, (strrpos($name, '\\') ?: -1) + 1);
            $stem = substr(basename($file['relative_path']), 0, -4);

            if ($stem !== $shortName) {
                $diagnostics[] = $this->diagnostic(
                    'filename_mismatch',
                    $file['source_file'],
                    $primary['start_line'],
                    sprintf('File name "%s.php" must match declared symbol "%s" exactly (PSR-4 case sensitive).', $stem, $shortName),
                );
            }

            $expected = $this->psr4->expectedName($file['path']);
            if ($expected !== null && $expected !== $name) {
                $diagnostics[] = $this->diagnostic(
                    'psr4_mismatch',
                    $file['source_file'],
                    $primary['start_line'],
                    sprintf('Composer PSR-4 mapping expects "%s", but source declares "%s".', $expected, $name),
                );
            }
        }

        foreach ($symbols as $records) {
            if (count($records) <= 1) {
                continue;
            }

            $locations = array_unique(array_column($records, 'source_file'));
            sort($locations, SORT_STRING);

            foreach ($records as $record) {
                $diagnostics[] = $this->diagnostic(
                    'duplicate_symbol',
                    $record['source_file'],
                    $record['line'],
                    sprintf('Symbol "%s" appears more than once: %s.', $record['name'], implode(', ', $locations)),
                );
            }
        }

        usort($diagnostics, static fn (array $a, array $b): int =>
            strcmp($a['source_file'], $b['source_file'])
                ?: $a['line'] <=> $b['line']
                ?: strcmp($a['code'], $b['code']));

        return $diagnostics;
    }

    /** @return array{severity: string, code: string, source_file: string, line: int, message: string} */
    private function diagnostic(string $code, string $file, int $line, string $message): array
    {
        return [
            'severity' => 'error',
            'code' => $code,
            'source_file' => $file,
            'line' => $line,
            'message' => $message,
        ];
    }
}
