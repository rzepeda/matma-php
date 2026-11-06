<?php

declare(strict_types=1);

namespace Matma\Php\Impact;

use Matma\Php\Analysis\SourceInventory;
use Matma\Php\Indexing\ManifestWriter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class ManifestReader
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * Read exactly the computed target manifest and one index metadata file.
     * Hashes and directory mtimes check freshness without a PHP AST pass.
     *
     * @return array{target: array<string, mixed>, manifest: array<string, mixed>, index: array<string, mixed>}
     */
    public function read(ManifestLocator $locator, string $target): array
    {
        $location = $locator->locate($target);
        $manifest = $this->generatedYaml($location['manifest_path'], 'missing_manifest');
        $index = $this->generatedYaml($locator->inventoryPath(), 'missing_index');

        if (($manifest['schema_version'] ?? null) !== 1
            || ($index['schema_version'] ?? null) !== 1
            || ! is_string($manifest['source_file'] ?? null)
            || $manifest['source_file'] !== $location['source_file']
            || ! is_array($manifest['mirrors'] ?? null)
            || ! is_array($manifest['dependents'] ?? null)) {
            throw new ImpactException('invalid_manifest', 'Manifest schema/identity mismatch. Run php artisan matma:index.');
        }

        $fingerprint = $manifest['index_fingerprint'] ?? null;
        if (! is_string($fingerprint)
            || ! preg_match('/^[0-9a-f]{64}$/', $fingerprint)
            || $fingerprint !== ($index['index_fingerprint'] ?? null)) {
            throw new ImpactException('stale_index', 'Manifest fingerprint differs from index inventory. Run php artisan matma:index.');
        }

        $this->checkFreshness($index, $locator->sourceRootPath());
        return ['target' => $location, 'manifest' => $manifest, 'index' => $index];
    }

    private function generatedYaml(string $path, string $missingStatus): array
    {
        $this->checkSymlinks($path);
        if (! is_file($path)) {
            throw new ImpactException(
                $missingStatus,
                'Required generated manifest is missing: '.$path.'. Run php artisan matma:index.',
            );
        }

        $content = @file_get_contents($path);
        if ($content === false || ! str_starts_with($content, ManifestWriter::HEADER)) {
            throw new ImpactException('invalid_manifest', 'Invalid or unowned generated YAML: '.$path);
        }

        try {
            $decoded = Yaml::parse(substr($content, strlen(ManifestWriter::HEADER)));
        } catch (ParseException $exception) {
            throw new ImpactException('invalid_manifest', 'Malformed generated YAML: '.$exception->getMessage());
        }

        if (! is_array($decoded)) {
            throw new ImpactException('invalid_manifest', 'Expected YAML mapping in '.$path);
        }

        return $decoded;
    }

    /** @param array<string, mixed> $index */
    private function checkFreshness(array $index, string $configuredSourceRoot): void
    {
        $actual = realpath($configuredSourceRoot);
        $canonical = $actual === false ? null : SourceInventory::normalize($actual);
        if ($canonical === null || $canonical !== ($index['source_root'] ?? null)) {
            throw new ImpactException('stale_index', 'Source root changed or disappeared. Run php artisan matma:index.');
        }

        $inputs = $index['source_inputs'] ?? null;
        $directories = $index['watched_directories'] ?? null;
        if (! is_array($inputs) || ! is_array($directories)
            || ! is_string($index['index_fingerprint'] ?? null)) {
            throw new ImpactException('invalid_manifest', 'Index inventory metadata is incomplete. Run php artisan matma:index.');
        }

        $recomputed = hash('sha256', json_encode(
            ['schema' => 1, 'inputs' => $inputs],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
        if ($recomputed !== $index['index_fingerprint']) {
            throw new ImpactException('stale_index', 'Index inventory fingerprint does not match its source list. Reindex.');
        }

        foreach ($inputs as $input) {
            if (! is_array($input) || count($input) !== 2
                || ! is_string($input[0]) || ! is_string($input[1])) {
                throw new ImpactException('invalid_manifest', 'Malformed source inventory. Reindex.');
            }
            $path = SourceInventory::normalize(
                SourceInventory::absolutePath($this->projectRoot, $input[0]),
            );
            if (! str_starts_with($path, rtrim($canonical, '/').'/')) {
                throw new ImpactException('invalid_manifest', 'Indexed source path escapes the configured root.');
            }
            if (! is_file($path) || is_link($path) || hash_file('sha256', $path) !== $input[1]) {
                throw new ImpactException(
                    'stale_index',
                    'Source changed or disappeared since indexing: '.$input[0].'. Run php artisan matma:index.',
                );
            }
        }

        foreach ($directories as $entry) {
            if (! is_array($entry) || ! is_string($entry['path'] ?? null)
                || ! is_int($entry['mtime'] ?? null)) {
                throw new ImpactException('invalid_manifest', 'Malformed directory freshness metadata. Reindex.');
            }
            $path = $entry['path'];
            if ($path !== $canonical
                && ! str_starts_with($path, rtrim($canonical, '/').'/')) {
                throw new ImpactException('invalid_manifest', 'Directory freshness path escapes source root.');
            }
            clearstatcache(true, $path);
            if (! is_dir($path) || is_link($path) || @filemtime($path) !== $entry['mtime']) {
                throw new ImpactException(
                    'stale_index',
                    'Source directory entries changed since indexing: '.$path.'. Run php artisan matma:index.',
                );
            }
        }
    }

    private function checkSymlinks(string $path): void
    {
        if (is_link($path)) {
            throw new ImpactException('invalid_manifest', 'Refusing symlink manifest: '.$path);
        }
        for ($dir = dirname($path); $dir !== dirname($dir); $dir = dirname($dir)) {
            if (is_link($dir)) {
                throw new ImpactException('invalid_manifest', 'Refusing symlink manifest ancestor: '.$dir);
            }
        }
    }
}
