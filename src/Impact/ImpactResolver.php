<?php

declare(strict_types=1);

namespace Matma\Php\Impact;

use Matma\Php\Analysis\SourceInventory;

/**
 * Reads precomputed reverse-dependency levels from one target manifest.
 * No graph traversal, source AST traversal or test execution happens here.
 */
final class ImpactResolver
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(
        ManifestLocator $locator,
        string $target,
        int $radius,
    ): array {
        if ($radius < 0) {
            throw new ImpactException('invalid_target', 'Impact level must be non-negative.');
        }

        $read = (new ManifestReader($this->projectRoot))->read($locator, $target);
        $manifest = $read['manifest'];
        $source = $manifest['source_file'];

        $affected = [[
            'depth' => 0,
            'source_file' => $source,
            'class_name' => $manifest['class_name'] ?? null,
            'unit_test' => $manifest['mirrors']['unit_test'] ?? null,
            'interaction_test' => $manifest['mirrors']['interaction_test'] ?? null,
        ]];
        $visited = [$source => true];
        $validSources = [];
        foreach ($read['index']['source_inputs'] as $input) {
            $validSources[$input[0]] = true;
        }

        for ($depth = 1; $depth <= $radius; ++$depth) {
            $level = $manifest['dependents']['level_'.$depth] ?? [];
            if (! is_array($level) || ! array_is_list($level)) {
                throw new ImpactException('invalid_manifest', 'Invalid dependents level_'.$depth.' in generated YAML.');
            }

            foreach ($level as $dependent) {
                if (! is_array($dependent) || ! is_string($dependent['source_file'] ?? null)) {
                    throw new ImpactException('invalid_manifest', 'Malformed reverse-dependency record.');
                }
                $file = $dependent['source_file'];
                if (! isset($validSources[$file]) || isset($visited[$file])) {
                    if (! isset($validSources[$file])) {
                        throw new ImpactException('stale_index', 'Dependent not in index inventory: '.$file.'. Reindex.');
                    }
                    continue;
                }
                $visited[$file] = true;
                $affected[] = [
                    'depth' => $depth,
                    'source_file' => $file,
                    'class_name' => $dependent['class_name'] ?? null,
                    'unit_test' => $dependent['unit_test'] ?? null,
                    'interaction_test' => $dependent['interaction_test'] ?? null,
                ];
            }
        }

        usort($affected, static fn (array $a, array $b): int =>
            $a['depth'] <=> $b['depth'] ?: strcmp($a['source_file'], $b['source_file']));

        $unit = [];
        $interaction = [];
        $missing = [];
        $testsRoot = rtrim(SourceInventory::normalize(
            SourceInventory::absolutePath($this->projectRoot, $this->testsRoot($locator)),
        ), '/');

        foreach ($affected as $item) {
            foreach (['unit_test' => 'unit', 'interaction_test' => 'interaction'] as $field => $tier) {
                if ($tier === 'interaction' && $radius === 0) {
                    continue;
                }
                $test = $item[$field];
                if ($test === null) {
                    continue;
                }
                if (! is_string($test)) {
                    throw new ImpactException('invalid_manifest', 'Non-string test path in indexed impact records.');
                }
                $absolute = SourceInventory::normalize(
                    SourceInventory::absolutePath($this->projectRoot, $test),
                );
                if (preg_match('~(^|/)\.\.(/|$)~', $absolute) === 1
                    || ! str_starts_with($absolute, $testsRoot.'/'.$tier.'/mirror/')
                    || ! str_ends_with($absolute, 'Test.php')) {
                    throw new ImpactException('invalid_manifest', 'Test path outside mirrored '.$tier.' tree: '.$test);
                }
                if ($tier === 'unit') {
                    $unit[$test] = true;
                } else {
                    $interaction[$test] = true;
                }

                if (! is_file($absolute)) {
                    $missing[$test] = true;
                }
            }
        }

        $units = array_keys($unit);
        $interactions = array_keys($interaction);
        $notFound = array_keys($missing);
        sort($units, SORT_STRING);
        sort($interactions, SORT_STRING);
        sort($notFound, SORT_STRING);

        $workflows = (new VerificationRegistry($this->projectRoot, $this->testsRoot($locator)))
            ->matching($locator->verificationRegistryPath(), $affected);

        return [
            'target' => [
                'class_name' => $manifest['class_name'] ?? null,
                'source_file' => $source,
                'kind' => $manifest['kind'] ?? null,
                'manifest' => $read['target']['manifest'],
            ],
            'level' => $radius,
            'index_fingerprint' => $manifest['index_fingerprint'],
            'affected_sources' => $affected,
            'tests' => [
                'unit' => $units,
                'interaction' => $interactions,
            ],
            'missing_tests' => $notFound,
            'registered_workflows' => $workflows,
            'summary' => [
                'affected_sources' => count($affected),
                'unit_tests' => count($units),
                'interaction_tests' => count($interactions),
                'missing_tests' => count($notFound),
                'business_workflows' => count($workflows['business']),
                'regression_workflows' => count($workflows['regression']),
            ],
        ];
    }

    private function testsRoot(ManifestLocator $locator): string
    {
        return dirname(dirname($locator->inventoryPath()));
    }
}
