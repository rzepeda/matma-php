<?php

declare(strict_types=1);

namespace Matma\Php\Analysis;

use InvalidArgumentException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SourceInventory
{
    private const EXCLUDED_DIRECTORIES = [
        '.git', '.tools', '.cache', 'vendor', 'node_modules', 'tests',
        'storage', 'bootstrap', 'cache',
    ];

    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @return array<int, array{path: string, relative_path: string, source_file: string}>
     */
    public function collect(string $sourceRoot): array
    {
        $sourcePath = self::absolutePath($this->projectRoot, $sourceRoot);
        $root = realpath($sourcePath);

        if ($root === false || ! is_dir($root) || ! is_readable($root)) {
            throw new InvalidArgumentException('Source root does not exist or is not readable: '.$sourceRoot);
        }

        $root = self::normalize($root);
        $directory = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $file): bool {
                if ($file->isLink()) {
                    return false;
                }

                if ($file->isDir()) {
                    return ! in_array($file->getFilename(), self::EXCLUDED_DIRECTORIES, true)
                        && $file->isReadable();
                }

                return $file->isFile()
                    && $file->isReadable()
                    && strtolower($file->getExtension()) === 'php';
            },
        );

        $files = [];

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            /** @var SplFileInfo $file */
            $path = self::normalize($file->getPathname());
            $relative = substr($path, strlen($root) + 1);
            $files[] = [
                'path' => $path,
                'relative_path' => $relative,
                'source_file' => self::projectRelative($this->projectRoot, $path),
            ];
        }

        usort($files, static fn (array $a, array $b): int =>
            strcmp($a['relative_path'], $b['relative_path']));

        return $files;
    }

    public static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public static function absolutePath(string $base, string $path): string
    {
        $path = self::normalize($path);
        if (str_starts_with($path, '/') || preg_match('~^[A-Za-z]:/~', $path) === 1) {
            return $path;
        }

        return rtrim(self::normalize($base), '/').'/'.$path;
    }

    public static function projectRelative(string $projectRoot, string $path): string
    {
        // On Windows the same directory may appear as a short (8.3) path
        // or a canonical long path. Match both, with a separator boundary.
        $roots = [
            rtrim(self::normalize($projectRoot), '/'),
            rtrim(self::normalize((string) (realpath($projectRoot) ?: $projectRoot)), '/'),
        ];
        $paths = [self::normalize($path)];
        $resolved = realpath($path);
        if ($resolved !== false) {
            $paths[] = self::normalize($resolved);
        }

        foreach ($paths as $candidate) {
            foreach (array_unique($roots) as $root) {
                $prefix = $root.'/';
                $matches = PHP_OS_FAMILY === 'Windows'
                    ? strncasecmp($candidate, $prefix, strlen($prefix)) === 0
                    : str_starts_with($candidate, $prefix);
                if ($matches) {
                    return substr($candidate, strlen($prefix));
                }
            }
        }

        return self::normalize($path);
    }
}
