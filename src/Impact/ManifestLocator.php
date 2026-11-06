<?php

declare(strict_types=1);

namespace Matma\Php\Impact;

use Matma\Php\Analysis\SourceInventory;

/**
 * Determine an exact manifest path from a PHP file or Composer PSR-4 class.
 * No AST parsing, globbing, class loading, or filesystem tree discovery.
 */
final class ManifestLocator
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $sourceRoot,
        private readonly string $testsRoot,
    ) {
    }

    /**
     * @return array{source_file: string, source_path: string, manifest_path: string, manifest: string}
     */
    public function locate(string $target): array
    {
        $target = trim($target);
        if ($target === '') {
            throw new ImpactException('invalid_target', 'Specify --target as a PHP source file or Composer PSR-4 class.');
        }

        $root = SourceInventory::normalize(
            SourceInventory::absolutePath($this->projectRoot, $this->sourceRoot),
        );
        $root = rtrim($root, '/');
        $tests = rtrim(SourceInventory::normalize(
            SourceInventory::absolutePath($this->projectRoot, $this->testsRoot),
        ), '/');

        if ($tests === '' || $root === '' || str_contains($root, '/../') || str_contains($tests, '/../')) {
            throw new ImpactException('invalid_target', 'Configured root contains an unsafe parent traversal.');
        }

        if (str_ends_with(strtolower($target), '.php')) {
            $normalized = SourceInventory::normalize($target);
            if (preg_match('~(^|/)\.\.(/|$)~', $normalized)) {
                throw new ImpactException('invalid_target', 'Parent path traversal is not allowed.');
            }
            if ($this->isAbsolute($normalized)) {
                $file = $normalized;
            } else {
                $projectPath = SourceInventory::absolutePath($this->projectRoot, $normalized);
                $file = str_starts_with($projectPath, $root.'/')
                    ? $projectPath
                    : $root.'/'.$normalized;
            }
        } else {
            $file = $this->resolveClass($target, $root);
        }

        $file = SourceInventory::normalize($file);
        if (! str_starts_with($file, $root.'/') || ! str_ends_with($file, '.php')) {
            throw new ImpactException(
                'invalid_target',
                'Target must resolve to a PHP file inside the configured --source-root.',
            );
        }

        $relative = substr($file, strlen($root) + 1);
        $stem = substr($relative, 0, -4);
        $manifest = $tests.'/mirror/'.$stem.'.auto.yaml';

        return [
            'source_file' => SourceInventory::projectRelative($this->projectRoot, $file),
            'source_path' => $file,
            'manifest_path' => $manifest,
            'manifest' => SourceInventory::projectRelative($this->projectRoot, $manifest),
        ];
    }

    public function inventoryPath(): string
    {
        return rtrim(SourceInventory::normalize(
            SourceInventory::absolutePath($this->projectRoot, $this->testsRoot),
        ), '/').'/mirror/.index.auto.yaml';
    }

    public function verificationRegistryPath(): string
    {
        return rtrim(SourceInventory::normalize(
            SourceInventory::absolutePath($this->projectRoot, $this->testsRoot),
        ), '/').'/matma-verification.yaml';
    }

    public function sourceRootPath(): string
    {
        return rtrim(SourceInventory::normalize(
            SourceInventory::absolutePath($this->projectRoot, $this->sourceRoot),
        ), '/');
    }

    private function resolveClass(string $class, string $sourceRoot): string
    {
        $name = ltrim($class, '\\');
        if (! preg_match('/^[A-Za-z_][A-Za-z_0-9]*(?:\\\\[A-Za-z_][A-Za-z_0-9]*)*$/', $name)) {
            throw new ImpactException('invalid_target', 'Invalid class name. Use a source filename or a PSR-4 FQCN.');
        }

        $composerPath = rtrim(SourceInventory::normalize($this->projectRoot), '/').'/composer.json';
        $composer = is_file($composerPath)
            ? json_decode((string) file_get_contents($composerPath), true)
            : null;
        if (! is_array($composer)) {
            throw new ImpactException('invalid_target', 'Composer PSR-4 mapping unavailable. Use --target=<source-file.php>.');
        }

        $candidates = [];
        $maxPrefix = -1;
        foreach (['autoload', 'autoload-dev'] as $section) {
            $map = $composer[$section]['psr-4'] ?? [];
            if (! is_array($map)) {
                continue;
            }
            foreach ($map as $prefix => $dirs) {
                if (! is_string($prefix) || ! str_starts_with($name, $prefix)) {
                    continue;
                }
                $length = strlen($prefix);
                if ($length < $maxPrefix) {
                    continue;
                }
                if ($length > $maxPrefix) {
                    $candidates = [];
                    $maxPrefix = $length;
                }
                $subpath = str_replace('\\', '/', substr($name, $length)).'.php';
                foreach ((array) $dirs as $dir) {
                    if (! is_string($dir)) {
                        continue;
                    }
                    $candidate = SourceInventory::normalize(
                        SourceInventory::absolutePath($this->projectRoot, $dir),
                    );
                    $candidate = rtrim($candidate, '/').'/'.$subpath;
                    if (str_starts_with($candidate, $sourceRoot.'/')) {
                        $candidates[$candidate] = true;
                    }
                }
            }
        }

        $paths = array_keys($candidates);
        if (count($paths) !== 1) {
            throw new ImpactException(
                'invalid_target',
                $paths === []
                    ? 'Class cannot be mapped under --source-root without searching. Use --target=<relative-file.php>.'
                    : 'Class maps to multiple PSR-4 source files. Use --target=<relative-file.php>.',
            );
        }

        return $paths[0];
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('~^[A-Za-z]:/~', $path) === 1;
    }
}
