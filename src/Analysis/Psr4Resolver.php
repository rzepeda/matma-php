<?php

declare(strict_types=1);

namespace Matma\Php\Analysis;

final class Psr4Resolver
{
    /** @var array<int, array{directory: string, prefix: string}> */
    private array $rules = [];

    public function __construct(string $projectRoot)
    {
        $path = rtrim(SourceInventory::normalize($projectRoot), '/').'/composer.json';

        if (! is_file($path) || ! is_readable($path)) {
            return;
        }

        $composer = json_decode((string) file_get_contents($path), true);
        if (! is_array($composer)) {
            return;
        }

        foreach (['autoload', 'autoload-dev'] as $section) {
            $mapping = $composer[$section]['psr-4'] ?? [];

            if (! is_array($mapping)) {
                continue;
            }

            foreach ($mapping as $prefix => $directories) {
                if (! is_string($prefix)) {
                    continue;
                }

                foreach ((array) $directories as $directory) {
                    if (! is_string($directory)) {
                        continue;
                    }

                    $base = rtrim(SourceInventory::normalize(
                        SourceInventory::absolutePath($projectRoot, $directory),
                    ), '/');

                    $this->rules[] = ['directory' => $base, 'prefix' => $prefix];
                }
            }
        }

        usort($this->rules, static fn (array $a, array $b): int =>
            strlen($b['directory']) <=> strlen($a['directory'])
                ?: strcmp($a['directory'], $b['directory'])
                ?: strcmp($a['prefix'], $b['prefix']));
    }

    public function expectedName(string $absoluteFile): ?string
    {
        $file = SourceInventory::normalize($absoluteFile);

        foreach ($this->rules as $rule) {
            $directory = $rule['directory'];

            if (! str_starts_with($file, $directory.'/')) {
                continue;
            }

            $subpath = substr($file, strlen($directory) + 1);
            if (! str_ends_with($subpath, '.php')) {
                continue;
            }

            $relativeName = substr($subpath, 0, -4);

            return $rule['prefix'].str_replace('/', '\\', $relativeName);
        }

        return null;
    }
}
