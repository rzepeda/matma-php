<?php

declare(strict_types=1);

namespace Matma\Php\Indexing;

use InvalidArgumentException;
use RuntimeException;

/**
 * Build file-owned dependency edges from the Phase 2 AST snapshot.
 * Named symbols resolve case-insensitively (PHP semantics), while paths and
 * manifests retain their exact case. Every source file owns at most one
 * manifest, including anonymous-class-only files.
 */
final class DependencyGraph
{
    /** @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function build(array $snapshot): array
    {
        if (($snapshot['summary']['errors'] ?? 0) > 0) {
            throw new InvalidArgumentException('Refusing to index a project with AST or structural errors.');
        }

        $symbols = [];
        $nodes = [];
        $inputs = [];

        foreach ($snapshot['files'] as $file) {
            $digest = hash_file('sha256', $file['path']);
            if ($digest === false) {
                throw new RuntimeException('Unable to hash source: '.$file['source_file']);
            }
            $inputs[] = [$file['source_file'], $digest];

            foreach ($file['declarations'] as $declaration) {
                if ($declaration['class_name'] !== null) {
                    $symbols[strtolower($declaration['class_name'])] = $file['source_file'];
                }
            }

            if ($file['declarations'] === []) {
                continue; // Helper-only PHP files are not class graph nodes.
            }

            $primary = null;
            foreach (['class', 'abstract_class', 'interface', 'trait', 'enum', 'anonymous'] as $kind) {
                foreach ($file['declarations'] as $declaration) {
                    if ($declaration['kind'] === $kind) {
                        $primary = $declaration;
                        break 2;
                    }
                }
            }

            if ($primary === null) {
                continue;
            }

            $testable = in_array($primary['kind'], ['class', 'abstract_class', 'interface', 'anonymous'], true);
            $mirrors = $file['mirrors'];
            if (! $testable) {
                // Traits and enums participate in impact propagation, not test scaffolding.
                $mirrors['unit_test'] = null;
                $mirrors['interaction_test'] = null;
                $mirrors['mock_file'] = null;
                $mirrors['mock_class'] = null;
            }

            $nodes[$file['source_file']] = [
                'class_name' => $primary['class_name'],
                'source_file' => $file['source_file'],
                'symbol_id' => $primary['class_name'] ?? ('file:'.$file['source_file']),
                'kind' => $primary['kind'],
                'mirrors' => $mirrors,
                'declarations' => $file['declarations'],
                'references' => $file['references'],
                'dynamic_references' => $file['dynamic_references'] ?? [],
            ];
        }

        ksort($nodes, SORT_STRING);
        sort($inputs, SORT_REGULAR);
        $fingerprint = hash('sha256', json_encode(
            ['schema' => 1, 'inputs' => $inputs],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));

        $edges = [];
        $external = [];
        $reverse = array_fill_keys(array_keys($nodes), []);

        foreach ($nodes as $source => $node) {
            $dependencies = [];
            $unresolved = [];
            foreach ($node['references'] as $reference) {
                $resolvedSource = $symbols[strtolower(ltrim($reference, '\\'))] ?? null;

                if ($resolvedSource === null) {
                    $unresolved[$reference] = true;
                } elseif ($resolvedSource !== $source && isset($nodes[$resolvedSource])) {
                    $dependencies[$resolvedSource] = true;
                }
            }

            $edges[$source] = array_keys($dependencies);
            sort($edges[$source], SORT_STRING);
            $external[$source] = array_keys($unresolved);
            sort($external[$source], SORT_STRING);

            foreach ($edges[$source] as $dependencySource) {
                $reverse[$dependencySource][] = $source;
            }
        }

        foreach ($reverse as &$dependents) {
            $dependents = array_values(array_unique($dependents));
            sort($dependents, SORT_STRING);
        }
        unset($dependents);

        $manifests = [];
        foreach ($nodes as $source => $node) {
            $dependencies = [];
            foreach ($edges[$source] as $dependencySource) {
                $dependency = $nodes[$dependencySource];
                $dependencies[] = [
                    'class_name' => $dependency['class_name'],
                    'source_file' => $dependencySource,
                    'kind' => $dependency['kind'],
                    'mock_file' => $dependency['mirrors']['mock_file'],
                    'mock_class' => $dependency['mirrors']['mock_class'],
                ];
            }

            $levels = $this->reverseLevels($source, $reverse, $nodes);
            $manifests[$source] = [
                'schema_version' => 1,
                'index_fingerprint' => $fingerprint,
                'class_name' => $node['class_name'],
                'symbol_id' => $node['symbol_id'],
                'kind' => $node['kind'],
                'source_file' => $source,
                'mirrors' => $node['mirrors'],
                'dependencies' => $dependencies,
                'dynamic_references' => $node['dynamic_references'],
                'external_dependencies' => array_map(
                    static fn (string $name): array => [
                        'class_name' => $name,
                        'source_file' => null,
                        'resolution' => 'external_or_unresolved',
                    ],
                    $external[$source],
                ),
                'dependents' => $levels,
            ];
        }

        return [
            'manifests' => $manifests,
            'nodes' => $nodes,
            'symbol_sources' => $symbols,
            'fingerprint' => $fingerprint,
            'inputs' => $inputs,
            'summary' => [
                'source_files' => count($snapshot['files']),
                'indexed_files' => count($nodes),
                'internal_edges' => array_sum(array_map('count', $edges)),
                'external_references' => array_sum(array_map('count', $external)),
                'dynamic_references' => array_sum(array_map(
                    static fn (array $node): int => count($node['dynamic_references']),
                    $nodes,
                )),
            ],
        ];
    }

    /**
     * The target restricts only the command's response. Indexing must always
     * regenerate the full graph to preserve other files' reverse edges.
     *
     * @param array<string, mixed> $graph
     */
    public function resolveTarget(array $graph, string $target): string
    {
        $normalized = str_replace('\\', '/', $target);

        foreach ($graph['nodes'] as $source => $node) {
            if ($source === $normalized || $node['mirrors']['manifest'] === $normalized) {
                return $source;
            }
            if ($node['class_name'] !== null && strcasecmp(ltrim($target, '\\'), $node['class_name']) === 0) {
                return $source;
            }
        }

        $matches = [];
        foreach ($graph['nodes'] as $source => $node) {
            if (str_ends_with($source, '/'.$normalized) || $source === $normalized) {
                $matches[] = $source;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        throw new InvalidArgumentException(
            count($matches) > 1 ? 'Ambiguous target: '.$target : 'Target not found: '.$target,
        );
    }

    /**
     * @param array<string, list<string>> $reverse
     * @param array<string, array<string, mixed>> $nodes
     * @return array<string, list<array<string, mixed>>>
     */
    private function reverseLevels(string $source, array $reverse, array $nodes): array
    {
        $visited = [$source => true];
        $queue = [[$source, 0]];
        $groups = [];

        for ($cursor = 0; $cursor < count($queue); ++$cursor) {
            [$current, $depth] = $queue[$cursor];
            foreach ($reverse[$current] ?? [] as $dependentSource) {
                if (isset($visited[$dependentSource])) {
                    continue;
                }

                $visited[$dependentSource] = true;
                $nextDepth = $depth + 1;
                $queue[] = [$dependentSource, $nextDepth];
                $node = $nodes[$dependentSource];
                $groups['level_'.$nextDepth][] = [
                    'class_name' => $node['class_name'],
                    'source_file' => $dependentSource,
                    'unit_test' => $node['mirrors']['unit_test'],
                    'interaction_test' => $node['mirrors']['interaction_test'],
                ];
            }
        }

        foreach ($groups as &$records) {
            usort($records, static fn (array $a, array $b): int =>
                strcmp($a['source_file'], $b['source_file']));
        }
        unset($records);

        uksort($groups, static fn (string $a, string $b): int =>
            (int) substr($a, 6) <=> (int) substr($b, 6));

        return $groups;
    }
}
