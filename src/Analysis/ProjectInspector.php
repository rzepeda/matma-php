<?php

declare(strict_types=1);

namespace Matma\Php\Analysis;

final class ProjectInspector
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * An AST-backed, read-only snapshot. Phase 3 consumes the extracted
     * references to construct a graph; Phase 2 never generates manifests.
     *
     * @return array{files: array, symbol_to_source: array, diagnostics: array, summary: array}
     */
    public function inspect(string $sourceRoot = 'app/', string $testsRoot = 'tests/', ?string $target = null): array
    {
        $inventory = (new SourceInventory($this->projectRoot))->collect($sourceRoot);
        $analyzer = new PhpAstAnalyzer();
        $mapper = new MirrorMapper();
        $files = [];
        $symbols = [];

        foreach ($inventory as $item) {
            $parsed = $analyzer->analyze($item['path'], $item['source_file']);
            $record = [
                'path' => $item['path'],
                'source_file' => $item['source_file'],
                'relative_path' => $item['relative_path'],
                'declarations' => $parsed['declarations'],
                'imports' => $parsed['imports'],
                'references' => $parsed['references'],
                'mirrors' => $mapper->map($item['relative_path'], $testsRoot),
                'diagnostics' => $parsed['diagnostics'],
            ];
            $files[] = $record;

            foreach ($parsed['declarations'] as $declaration) {
                if ($declaration['class_name'] !== null) {
                    $symbols[$declaration['class_name']][] = $item['source_file'];
                }
            }
        }

        ksort($symbols, SORT_STRING);
        foreach ($symbols as &$sourceFiles) {
            $sourceFiles = array_values(array_unique($sourceFiles));
            sort($sourceFiles, SORT_STRING);
        }
        unset($sourceFiles);

        $diagnostics = (new StructureValidator(new Psr4Resolver($this->projectRoot)))->validate($files);

        foreach ($files as $file) {
            foreach ($file['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
        }

        if ($target !== null) {
            $matching = array_filter($files, static function (array $file) use ($target): bool {
                $normalized = SourceInventory::normalize($target);
                if (in_array($normalized, [
                    $file['source_file'], $file['relative_path'], $file['path'],
                ], true)) {
                    return true;
                }

                foreach ($file['declarations'] as $declaration) {
                    if ($declaration['class_name'] !== null
                        && strcasecmp(ltrim($target, '\\'), $declaration['class_name']) === 0) {
                        return true;
                    }
                }

                return false;
            });

            $files = array_values($matching);
            if ($files === []) {
                $diagnostics = [[
                    'severity' => 'error',
                    'code' => 'target_not_found',
                    'source_file' => $target,
                    'line' => 0,
                    'message' => 'Target not found in the configured source root.',
                ]];
            } else {
                $selectedPaths = array_column($files, 'source_file');
                $diagnostics = array_values(array_filter(
                    $diagnostics,
                    static fn (array $diagnostic): bool =>
                        in_array($diagnostic['source_file'], $selectedPaths, true),
                ));
            }
        }

        usort($diagnostics, static fn (array $a, array $b): int =>
            strcmp($a['source_file'], $b['source_file'])
                ?: $a['line'] <=> $b['line']
                ?: strcmp($a['code'], $b['code']));

        $errors = count(array_filter($diagnostics, static fn (array $d): bool => $d['severity'] === 'error'));
        $warnings = count($diagnostics) - $errors;

        return [
            'files' => $files,
            'symbol_to_source' => $symbols,
            'diagnostics' => $diagnostics,
            'summary' => [
                'source_files' => count($files),
                'symbols' => array_sum(array_map(
                    static fn (array $file): int => count($file['declarations']),
                    $files,
                )),
                'errors' => $errors,
                'warnings' => $warnings,
            ],
        ];
    }
}
