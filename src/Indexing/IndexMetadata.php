<?php

declare(strict_types=1);

namespace Matma\Php\Indexing;

use Matma\Php\Analysis\SourceInventory;
use RuntimeException;

/**
 * A bounded freshness index. Impact queries read this generated YAML and
 * stat/hash known paths, never parse ASTs or recursively discover files.
 */
final class IndexMetadata
{
    public const NAME = '.index.auto.yaml';

    /** @param array<string, mixed> $graph
     * @return array<string, mixed>
     */
    public static function build(array $graph, string $projectRoot, string $sourceRoot): array
    {
        $root = realpath(SourceInventory::absolutePath($projectRoot, $sourceRoot));
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('Cannot resolve indexed source root.');
        }
        $root = SourceInventory::normalize($root);
        $folders = [$root => true];
        foreach ($graph['inputs'] as $input) {
            $absolute = SourceInventory::absolutePath($projectRoot, $input[0]);
            for ($dir = dirname($absolute);
                str_starts_with($dir, $root.'/');
                $dir = dirname($dir)) {
                $folders[SourceInventory::normalize($dir)] = true;
            }
        }

        $directories = [];
        $names = array_keys($folders);
        sort($names, SORT_STRING);
        foreach ($names as $name) {
            clearstatcache(true, $name);
            $mtime = @filemtime($name);
            if ($mtime === false) {
                throw new RuntimeException('Cannot stat indexed directory: '.$name);
            }
            $directories[] = ['path' => $name, 'mtime' => $mtime];
        }

        return [
            'schema_version' => 1,
            'index_fingerprint' => $graph['fingerprint'],
            'source_root' => $root,
            'source_inputs' => $graph['inputs'],
            'watched_directories' => $directories,
        ];
    }
}
