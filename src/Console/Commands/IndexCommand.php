<?php

declare(strict_types=1);

namespace Matma\Php\Console\Commands;

use InvalidArgumentException;
use Matma\Php\Analysis\ProjectInspector;
use Matma\Php\Analysis\SourceInventory;
use Matma\Php\Console\MatmaCommand;
use Matma\Php\Console\MatmaOptions;
use Matma\Php\Indexing\DependencyGraph;
use Matma\Php\Indexing\IndexMetadata;
use Matma\Php\Indexing\ManifestWriter;
use Matma\Php\Scaffolding\ScaffoldGenerator;
use RuntimeException;

class IndexCommand extends MatmaCommand
{
    protected const MATMA_NAME = 'matma:index';
    protected const MATMA_DESCRIPTION = 'Build deterministic YAML dependency manifests, optionally scaffold missing tests.';

    public function handle(): int
    {
        return $this->executeIndex((bool) $this->option('scaffold'));
    }

    /**
     * Shared implementation for matma:index --scaffold and matma:scaffold.
     * Validation and graph construction happen before any file creation.
     */
    protected function executeIndex(bool $scaffold): int
    {
        try {
            $options = MatmaOptions::fromArray($this->options());
        } catch (InvalidArgumentException $exception) {
            return $this->report('invalid_options', $exception->getMessage(), self::EXIT_INVALID_OPTIONS, null);
        }

        try {
            $inspection = (new ProjectInspector(base_path()))->inspect(
                $options->sourceRoot,
                $options->testsRoot,
            );

            if ($inspection['summary']['errors'] > 0) {
                return $this->report(
                    'validation_failed',
                    'Index not written: correct AST/structure errors and retry.',
                    1,
                    $options,
                    [
                        'summary' => $inspection['summary'],
                        'diagnostics' => $inspection['diagnostics'],
                    ],
                );
            }

            $builder = new DependencyGraph();
            $graph = $builder->build($inspection);
            $lookup = $options->target;
            if ($lookup !== null) {
                $normalized = SourceInventory::normalize($lookup);
                $prefix = rtrim(SourceInventory::normalize(base_path()), '/').'/';
                if (str_starts_with($normalized, $prefix)) {
                    $lookup = SourceInventory::projectRelative(base_path(), $normalized);
                }
            }

            $target = $lookup === null ? null : $builder->resolveTarget($graph, $lookup);
            $metadata = IndexMetadata::build($graph, base_path(), $options->sourceRoot);

            $result = $scaffold
                ? (new ScaffoldGenerator(base_path(), $options->sourceRoot, $options->testsRoot))
                    ->run($graph, $target, $metadata)
                : [
                    'manifests' => (new ManifestWriter(base_path(), $options->testsRoot))->write($graph['manifests'], $metadata),
                    'scaffolds' => ['created' => [], 'existing' => []],
                    'diagnostics' => [],
                    'targets' => [],
                ];

            $incomplete = $result['diagnostics'] !== [];
            $manifestCounts = $result['manifests'];
            $testCounts = $result['scaffolds'];

            return $this->report(
                ! $scaffold ? 'indexed' : ($incomplete ? 'scaffold_incomplete' : 'scaffolded'),
                sprintf(
                    'Indexed %d files, %d edges; manifests: %d written, %d unchanged, %d removed; scaffolds: %d created, %d existing; %d mock warnings.',
                    $graph['summary']['indexed_files'],
                    $graph['summary']['internal_edges'],
                    count($manifestCounts['written']),
                    count($manifestCounts['unchanged']),
                    count($manifestCounts['removed']),
                    count($testCounts['created']),
                    count($testCounts['existing']),
                    count($result['diagnostics']),
                ),
                $incomplete ? 1 : 0,
                $options,
                [
                    'summary' => $graph['summary'],
                    'index_fingerprint' => $graph['fingerprint'],
                    'target' => $target,
                    'manifests' => $manifestCounts,
                    'scaffolds' => $testCounts,
                    'diagnostics' => $result['diagnostics'],
                    'scaffold_targets' => $result['targets'],
                ],
            );
        } catch (InvalidArgumentException $exception) {
            return $this->report('invalid_target_or_path', $exception->getMessage(), 1, $options);
        } catch (RuntimeException $exception) {
            return $this->report('generation_error', $exception->getMessage(), 1, $options);
        }
    }
}
