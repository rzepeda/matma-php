<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Matma\Php\Analysis\ProjectInspector;
use Matma\Php\Impact\ImpactResolver;
use Matma\Php\Impact\ManifestLocator;
use Matma\Php\Indexing\DependencyGraph;
use Matma\Php\Indexing\IndexMetadata;
use Matma\Php\Indexing\ManifestWriter;

/**
 * Reproducible smoke benchmark of AST index+write vs fresh manifest lookup.
 * Results depend on hardware, filesystem and cache state; no speed ratio is
 * treated as a release gate. Never benchmark production files in-place.
 */
$options = ['classes' => 100, 'iterations' => 7];
foreach (array_slice($argv, 1) as $arg) {
    if (! preg_match('/^--(classes|iterations)=([0-9]+)$/', $arg, $matches)) {
        fwrite(STDERR, "Usage: php benchmarks/benchmark.php --classes=100 --iterations=7\n");
        exit(64);
    }
    $options[$matches[1]] = (int) $matches[2];
}
if ($options['classes'] < 2 || $options['classes'] > 1000
    || $options['iterations'] < 1 || $options['iterations'] > 50) {
    fwrite(STDERR, "classes must be 2-1000 and iterations 1-50.\n");
    exit(64);
}

$root = sys_get_temp_dir().'/matma-benchmark-'.bin2hex(random_bytes(8));
$sourceDir = $root.'/app/Bench';
if (! mkdir($sourceDir, 0775, true) && ! is_dir($sourceDir)) {
    throw new RuntimeException('Cannot create isolated benchmark fixture.');
}
try {
    file_put_contents($root.'/composer.json', json_encode([
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
    ], JSON_THROW_ON_ERROR));

    for ($i = 0; $i < $options['classes']; ++$i) {
        $symbol = sprintf('Node%04d', $i);
        $declaration = $i === 0
            ? ''
            : sprintf('public function __construct(private Node%04d $previous) {}', $i - 1);
        file_put_contents(
            $sourceDir.'/'.$symbol.'.php',
            "<?php\nnamespace App\\Bench;\nclass {$symbol} { {$declaration} }\n",
        );
    }

    $indexTimes = [];
    $impactTimes = [];
    $writer = new ManifestWriter($root, 'tests');
    $resolver = new ImpactResolver($root);
    $locator = new ManifestLocator($root, 'app', 'tests');
    $summary = null;
    $result = null;

    // Warm each operation separately; do not include fixture generation.
    for ($i = 0; $i < $options['iterations'] + 1; ++$i) {
        $start = hrtime(true);
        $snapshot = (new ProjectInspector($root))->inspect('app', 'tests');
        $graph = (new DependencyGraph())->build($snapshot);
        $metadata = IndexMetadata::build($graph, $root, 'app');
        $writer->write($graph['manifests'], $metadata);
        $elapsed = (hrtime(true) - $start) / 1_000_000;
        $summary = $graph['summary'];
        if ($i > 0) {
            $indexTimes[] = $elapsed;
        }
    }
    for ($i = 0; $i < $options['iterations'] + 1; ++$i) {
        $start = hrtime(true);
        $result = $resolver->resolve($locator, 'App\Bench\Node0000', 3);
        $elapsed = (hrtime(true) - $start) / 1_000_000;
        if ($i > 0) {
            $impactTimes[] = $elapsed;
        }
    }

    sort($indexTimes, SORT_NUMERIC);
    sort($impactTimes, SORT_NUMERIC);
    $median = static function (array $timings): float {
        $count = count($timings);
        $middle = intdiv($count, 2);
        return $count % 2 === 0
            ? ($timings[$middle - 1] + $timings[$middle]) / 2
            : $timings[$middle];
    };
    $indexMedian = $median($indexTimes);
    $impactMedian = $median($impactTimes);

    echo json_encode([
        'status' => 'measured',
        'php_version' => PHP_VERSION,
        'platform' => PHP_OS_FAMILY,
        'source_classes' => $options['classes'],
        'iterations' => $options['iterations'],
        'unit' => 'milliseconds',
        'index_ast_and_write_median_ms' => round($indexMedian, 3),
        'impact_manifest_and_hash_median_ms' => round($impactMedian, 3),
        'observed_ratio_index_to_impact' => $impactMedian > 0
            ? round($indexMedian / $impactMedian, 3)
            : null,
        'indexed_files' => $summary['indexed_files'],
        'selected_sources' => $result['summary']['affected_sources'],
        'impact_status' => $result['target']['source_file'] === 'app/Bench/Node0000.php'
            ? 'selected'
            : 'unexpected',
        'ast_reparsed_during_impact' => false,
        'note' => 'Measurements are local to this runner; ratio is descriptive, not a performance guarantee.',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink()
                ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }
}
