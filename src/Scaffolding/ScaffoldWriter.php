<?php

declare(strict_types=1);

namespace Matma\Php\Scaffolding;

use InvalidArgumentException;
use Matma\Php\Analysis\SourceInventory;
use RuntimeException;

/**
 * Create missing PHP files exclusively. Never overwrite user-owned or even
 * previously generated test files. Manifests have a separate owner-aware writer.
 */
final class ScaffoldWriter
{
    private readonly string $root;

    public function __construct(
        private readonly string $projectRoot,
        string $testsRoot,
        string $sourceRoot,
    ) {
        $root = SourceInventory::normalize(SourceInventory::absolutePath($projectRoot, $testsRoot));
        $source = SourceInventory::normalize(SourceInventory::absolutePath($projectRoot, $sourceRoot));
        if ($root === '' || $root === '/' || preg_match('~(^|/)\.\.(/|$)~', $root) === 1
            || $root === $source || str_starts_with($source, rtrim($root, '/').'/')
            || str_starts_with($root, rtrim($source, '/').'/')) {
            throw new InvalidArgumentException('Unsafe tests root: test artifacts must not overlap production source.');
        }
        $this->root = rtrim($root, '/');
    }

    /** @param array<string, string> $files */
    public function preflight(array $files): void
    {
        foreach ($files as $path => $contents) {
            $absolute = $this->absolute($path);
            if (! str_starts_with($absolute, $this->root.'/')
                || preg_match('~(^|/)\.\.(/|$)~', $absolute) === 1
                || ! str_ends_with($absolute, '.php')) {
                throw new InvalidArgumentException('Unsafe scaffold file path: '.$path);
            }
            $relative = substr($absolute, strlen($this->root) + 1);
            if (! preg_match('~^(unit|interaction|mocks)/mirror/.+\.php$~', $relative)) {
                throw new InvalidArgumentException('Unexpected scaffold tier: '.$path);
            }

            $this->rejectSymlinks($absolute);
            if ((file_exists($absolute) || is_link($absolute)) && ! is_file($absolute)) {
                throw new RuntimeException('Scaffold destination is not a regular file: '.$absolute);
            }
        }
    }

    /**
     * @param array<string, string> $files
     * @return array{created: list<string>, existing: list<string>}
     */
    public function write(array $files): array
    {
        $this->preflight($files);
        ksort($files, SORT_STRING);
        $result = ['created' => [], 'existing' => []];

        foreach ($files as $path => $contents) {
            $absolute = $this->absolute($path);
            if (is_file($absolute)) {
                $result['existing'][] = $this->display($absolute);
                continue;
            }

            $directory = dirname($absolute);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException('Unable to create test directory: '.$directory);
            }
            $this->rejectSymlinks($absolute);

            // 'x' is an exclusive create; a race with another writer never
            // truncates an existing hand-written file.
            $handle = @fopen($absolute, 'x');
            if ($handle === false) {
                if (is_file($absolute) && ! is_link($absolute)) {
                    $result['existing'][] = $this->display($absolute);
                    continue;
                }
                throw new RuntimeException('Unable to create test file exclusively: '.$absolute);
            }
            try {
                $length = strlen($contents);
                $written = 0;
                while ($written < $length) {
                    $count = fwrite($handle, substr($contents, $written));
                    if ($count === false || $count === 0) {
                        throw new RuntimeException('Unable to finish writing scaffold: '.$absolute);
                    }
                    $written += $count;
                }
            } catch (RuntimeException $exception) {
                fclose($handle);
                unlink($absolute);
                throw $exception;
            }
            fclose($handle);
            $result['created'][] = $this->display($absolute);
        }

        return $result;
    }

    private function absolute(string $path): string
    {
        return SourceInventory::normalize(SourceInventory::absolutePath($this->projectRoot, $path));
    }

    private function display(string $absolute): string
    {
        return SourceInventory::projectRelative($this->projectRoot, $absolute);
    }

    private function rejectSymlinks(string $path): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Scaffold symlink destination is forbidden: '.$path);
        }
        for ($directory = dirname($path); $directory !== dirname($directory); $directory = dirname($directory)) {
            if (is_link($directory)) {
                throw new RuntimeException('Scaffold symlink ancestor is forbidden: '.$directory);
            }
        }
    }
}
