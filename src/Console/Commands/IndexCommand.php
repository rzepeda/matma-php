<?php

declare(strict_types=1);

namespace Matma\Php\Console\Commands;

use InvalidArgumentException;
use RuntimeException;
use Matma\Php\Analysis\ProjectInspector;
use Matma\Php\Analysis\SourceInventory;
use Matma\Php\Console\MatmaCommand;
use Matma\Php\Console\MatmaOptions;
use Matma\Php\Indexing\DependencyGraph;
use Matma\Php\Indexing\ManifestWriter;

class IndexCommand extends MatmaCommand
{
    protected const MATMA_NAME = 'matma:index';
    protected const MATMA_DESCRIPTION = 'Build deterministic YAML reverse-dependency manifests from PHP ASTs.';

    public function handle(): int
    {
        try {
            $options = MatmaOptions::fromArray($this->options());
        } catch (InvalidArgumentException $exception) {
            return $this->report('invalid_options', $exception->getMessage(), self::EXIT_INVALID_OPTIONS, null);
        }

        if ($options->scaffold) {
            return $this->report(
                'not_implemented',
                'Indexing with --scaffold requires the Phase 4 generator; no manifests were written.',
                self::EXIT_NOT_IMPLEMENTED,
                $options,
            );
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
            // Preserve namespace backslashes for FQCN targets. Convert
            // filesystem paths only when the input is an absolute project path.
            $target = $lookup === null ? null : $builder->resolveTarget($graph, $lookup);
            $written = (new ManifestWriter(base_path(), $options->testsRoot))->write($graph['manifests']);

            return $this->report(
                'indexed',
                sprintf(
                    'Indexed %d files, %d internal edges, %d external references; %d written, %d unchanged, %d stale removed.',
                    $graph['summary']['indexed_files'],
                    $graph['summary']['internal_edges'],
                    $graph['summary']['external_references'],
                    count($written['written']),
                    count($written['unchanged']),
                    count($written['removed']),
                ),
                0,
                $options,
                [
                    'summary' => $graph['summary'],
                    'index_fingerprint' => $graph['fingerprint'],
                    'target' => $target,
                    'manifests' => $written,
                ],
            );
        } catch (InvalidArgumentException $exception) {
            return $this->report('invalid_target_or_path', $exception->getMessage(), 1, $options);
        } catch (RuntimeException $exception) {
            return $this->report('index_error', $exception->getMessage(), 1, $options);
        }
    }
}
