<?php

declare(strict_types=1);

namespace Matma\Php\Scaffolding;

use InvalidArgumentException;
use Matma\Php\Analysis\SourceInventory;
use Matma\Php\Indexing\ManifestWriter;
use RuntimeException;

/**
 * Plan first, preflight file safety, refresh the full index and create only
 * missing scaffolds. No production files are touched.
 */
final class ScaffoldGenerator
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $sourceRoot,
        private readonly string $testsRoot,
    ) {
    }

    /**
     * @param array<string, mixed> $graph A validated DependencyGraph::build() result
     * @return array{files: array<string, string>, diagnostics: list<array<string, mixed>>, targets: list<string>}
     */
    public function plan(array $graph, ?string $target = null): array
    {
        if ($target !== null && ! isset($graph['nodes'][$target])) {
            throw new InvalidArgumentException('Scaffold target was not indexed: '.$target);
        }

        $targets = $target === null ? array_keys($graph['nodes']) : [$target];
        $targets = array_values(array_filter($targets, static fn (string $file): bool =>
            in_array($graph['nodes'][$file]['kind'], ['class', 'abstract_class', 'interface', 'anonymous'], true)));

        if ($target !== null && $targets === []) {
            throw new InvalidArgumentException('Trait and enum symbols are dependency-only, not standalone test targets.');
        }

        $files = [];
        $diagnostics = [];
        $mockSources = [];

        foreach ($targets as $source) {
            $node = $graph['nodes'][$source];
            $mirrors = $node['mirrors'];

            foreach (['unit_test' => 'unit', 'interaction_test' => 'interaction'] as $field => $tier) {
                $path = $mirrors[$field];
                if ($path === null) {
                    continue;
                }

                $stem = substr(basename($path), 0, -4);
                $class = $this->safeIdentifier($stem);
                $namespace = $this->namespaceFor($path);
                $name = $node['class_name'] ?? $node['symbol_id'];

                $files[$path] = $this->testStub($namespace, $class, $node['source_file'], $name, $tier);
            }

            $mockSources[$source] = true;
            foreach ($graph['manifests'][$source]['dependencies'] as $dependency) {
                if (isset($graph['nodes'][$dependency['source_file']])) {
                    $mockSources[$dependency['source_file']] = true;
                }
            }
        }

        ksort($mockSources, SORT_STRING);
        foreach (array_keys($mockSources) as $source) {
            $node = $graph['nodes'][$source];
            $kind = $node['kind'];
            if (in_array($kind, ['trait', 'enum'], true)) {
                // These are dependency-only composition symbols, never PHPUnit doubles.
                continue;
            }

            $declaration = null;
            foreach ($node['declarations'] as $candidate) {
                if ($candidate['class_name'] === $node['class_name']
                    && $candidate['kind'] === $kind) {
                    $declaration = $candidate;
                    break;
                }
            }

            $reason = null;
            if ($node['class_name'] === null) {
                $reason = 'Anonymous classes cannot be identified as independently mockable named symbols.';
            } elseif (($declaration['is_final'] ?? false) || ($declaration['is_readonly'] ?? false)) {
                $reason = 'Final or readonly PHP classes cannot be safely mocked by standard PHPUnit test doubles.';
            }

            if ($reason !== null) {
                $diagnostics[] = [
                    'severity' => 'warning',
                    'code' => 'non_mockable',
                    'source_file' => $source,
                    'class_name' => $node['class_name'],
                    'message' => $reason.' Production files were not modified.',
                ];
                continue;
            }

            $path = $node['mirrors']['mock_file'];
            if ($path === null) {
                continue;
            }

            $class = $this->safeIdentifier($node['mirrors']['mock_class']);
            $files[$path] = $this->mockFactory(
                $this->namespaceFor($path),
                $class,
                $node['class_name'],
            );
        }

        ksort($files, SORT_STRING);
        return ['files' => $files, 'diagnostics' => $diagnostics, 'targets' => $targets];
    }

    /**
     * @param array<string, mixed> $graph
     * @return array{scaffolds: array, manifests: array, diagnostics: array, targets: array}
     */
    public function run(array $graph, ?string $target = null, ?array $metadata = null): array
    {
        $plan = $this->plan($graph, $target);
        $writer = new ScaffoldWriter($this->projectRoot, $this->testsRoot, $this->sourceRoot);
        $writer->preflight($plan['files']);

        // All manifests are rebuilt, even in --target mode, so every class
        // has up-to-date reverse-dependency pointers.
        $manifests = (new ManifestWriter($this->projectRoot, $this->testsRoot))
            ->write($graph['manifests'], $metadata);

        $scaffolds = $writer->write($plan['files']);

        return [
            'scaffolds' => $scaffolds,
            'manifests' => $manifests,
            'diagnostics' => $plan['diagnostics'],
            'targets' => $plan['targets'],
        ];
    }

    private function testStub(string $namespace, string $class, string $source, string $name, string $tier): string
    {
        $message = var_export(
            sprintf('MATMA: implement %s assertions for %s (%s).', $tier, $name, $source),
            true,
        );

        return "<?php\n\n"
            ."declare(strict_types=1);\n\n"
            ."namespace {$namespace};\n\n"
            ."use PHPUnit\\Framework\\TestCase;\n\n"
            ."// MATMA scaffold: replace the failing assertion with meaningful verification.\n"
            ."final class {$class} extends TestCase\n{\n"
            ."    public function test_implementation_is_required(): void\n    {\n"
            ."        self::fail({$message});\n"
            ."    }\n}\n";
    }

    private function mockFactory(string $namespace, string $class, string $targetName): string
    {
        $target = '\\'.ltrim($targetName, '\\');
        $message = var_export('MATMA mock factory must return a PHPUnit double implementing '.$targetName, true);

        // TestCase::createMock() must be invoked inside the test's own
        // visibility scope. The caller passes a closure rather than being
        // offered a fake concrete subclass with real production behavior.
        return "<?php\n\n"
            ."declare(strict_types=1);\n\n"
            ."namespace {$namespace};\n\n"
            ."use PHPUnit\\Framework\\MockObject\\MockObject;\n\n"
            ."/**\n * Centralized mock factory for {$target}.\n"
            ." * Usage in a PHPUnit test:\n"
            ." *   \$double = {$class}::create(fn (string \$type) => \$this->createMock(\$type));\n"
            ." * @phpstan-type Target {$target}\n"
            ." */\n"
            ."final class {$class}\n{\n"
            ."    public static function create(callable \$factory): MockObject\n    {\n"
            ."        \$double = \$factory({$target}::class);\n"
            ."        if (! \$double instanceof MockObject || ! \$double instanceof {$target}) {\n"
            ."            throw new \\InvalidArgumentException({$message});\n"
            ."        }\n"
            ."        return \$double;\n"
            ."    }\n}\n";
    }

    private function namespaceFor(string $path): string
    {
        $file = SourceInventory::normalize(SourceInventory::absolutePath($this->projectRoot, $path));
        $composer = rtrim(SourceInventory::normalize($this->projectRoot), '/').'/composer.json';
        $json = is_file($composer) ? json_decode((string) file_get_contents($composer), true) : null;
        $rules = is_array($json) ? ($json['autoload-dev']['psr-4'] ?? []) : [];
        $matches = [];

        if (is_array($rules)) {
            foreach ($rules as $prefix => $directories) {
                foreach ((array) $directories as $directory) {
                    if (! is_string($directory) || ! is_string($prefix)) {
                        continue;
                    }
                    $base = rtrim(SourceInventory::normalize(
                        SourceInventory::absolutePath($this->projectRoot, $directory),
                    ), '/').'/';
                    if (str_starts_with($file, $base)) {
                        $matches[] = ['base' => $base, 'prefix' => $prefix];
                    }
                }
            }
        }

        usort($matches, static fn (array $a, array $b): int =>
            strlen($b['base']) <=> strlen($a['base'])
                ?: strcmp($a['prefix'], $b['prefix']));

        if ($matches !== []) {
            $relative = substr(dirname($file), strlen($matches[0]['base']));
            $pieces = $relative === '.' || $relative === '' ? '' : str_replace('/', '\\', $relative);

            return rtrim($matches[0]['prefix'], '\\').($pieces === '' ? '' : '\\'.$pieces);
        }

        $root = rtrim(SourceInventory::normalize(
            SourceInventory::absolutePath($this->projectRoot, $this->testsRoot),
        ), '/').'/';
        if (! str_starts_with($file, $root)) {
            throw new RuntimeException('Generated file lies outside configured tests root: '.$path);
        }
        $relative = substr(dirname($file), strlen($root));
        return 'MatmaGenerated'.($relative === '' ? '' : '\\'.str_replace('/', '\\', $relative));
    }

    private function safeIdentifier(string $name): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?? '';
        if ($value === '' || ctype_digit($value[0])) {
            $value = '_'.$value;
        }

        return $value;
    }
}
