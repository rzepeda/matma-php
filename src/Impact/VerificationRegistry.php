<?php

declare(strict_types=1);

namespace Matma\Php\Impact;

use Matma\Php\Analysis\SourceInventory;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Explicit user-authored workflow registrations; never automatically run them.
 * This registry is independent from per-class mirrored YAML manifests.
 */
final class VerificationRegistry
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $testsRoot,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $affected
     * @return array{business: list<array<string, mixed>>, regression: list<array<string, mixed>>}
     */
    public function matching(string $path, array $affected): array
    {
        $result = ['business' => [], 'regression' => []];
        if (! is_file($path)) {
            return $result;
        }
        if (is_link($path)) {
            throw new ImpactException('invalid_registry', 'Verification registry must not be a symlink.');
        }

        try {
            $registry = Yaml::parse((string) file_get_contents($path));
        } catch (ParseException $exception) {
            throw new ImpactException('invalid_registry', 'Verification registry YAML is invalid: '.$exception->getMessage());
        }
        if (! is_array($registry) || ($registry['schema_version'] ?? null) !== 1) {
            throw new ImpactException('invalid_registry', 'Verification registry must contain schema_version: 1.');
        }

        $affectedFiles = array_fill_keys(array_column($affected, 'source_file'), true);
        $affectedClasses = [];
        foreach ($affected as $node) {
            if (is_string($node['class_name'] ?? null)) {
                $affectedClasses[strtolower($node['class_name'])] = true;
            }
        }

        $testsBase = rtrim(SourceInventory::normalize(
            SourceInventory::absolutePath($this->projectRoot, $this->testsRoot),
        ), '/');

        foreach (['business', 'regression'] as $tier) {
            $items = $registry[$tier] ?? [];
            if (! is_array($items) || ! array_is_list($items)) {
                throw new ImpactException('invalid_registry', 'Registry '.$tier.' must be a list.');
            }

            $ids = [];
            foreach ($items as $entry) {
                if (! is_array($entry)
                    || ! is_string($entry['id'] ?? null)
                    || trim($entry['id']) === ''
                    || isset($ids[$entry['id']])
                    || ! is_array($entry['sources'] ?? null)
                    || ! is_array($entry['tests'] ?? null)
                    || ! array_is_list($entry['sources'])
                    || ! array_is_list($entry['tests'])) {
                    throw new ImpactException('invalid_registry', 'Malformed or duplicate '.$tier.' workflow registration.');
                }
                $ids[$entry['id']] = true;

                $matches = [];
                foreach ($entry['sources'] as $source) {
                    if (! is_string($source) || trim($source) === '') {
                        throw new ImpactException('invalid_registry', 'Registered sources must be nonempty strings.');
                    }
                    if (isset($affectedFiles[SourceInventory::normalize($source)])
                        || isset($affectedClasses[strtolower(ltrim($source, '\\'))])) {
                        $matches[$source] = true;
                    }
                }

                $tests = [];
                foreach ($entry['tests'] as $test) {
                    if (! is_string($test) || trim($test) === '') {
                        throw new ImpactException('invalid_registry', 'Registered test paths must be nonempty strings.');
                    }
                    $absolute = SourceInventory::normalize(
                        SourceInventory::absolutePath($this->projectRoot, $test),
                    );
                    if (preg_match('~(^|/)\.\.(/|$)~', $absolute) === 1
                        || ! str_starts_with($absolute, $testsBase.'/'.$tier.'/')
                        || ! str_ends_with($absolute, 'Test.php')) {
                        throw new ImpactException(
                            'invalid_registry',
                            'Registered '.$tier.' tests must be within tests/'.$tier.' and end in Test.php.',
                        );
                    }
                    $tests[$test] = true;
                }

                if ($matches !== [] && $tests !== []) {
                    $matchedSources = array_keys($matches);
                    $testPaths = array_keys($tests);
                    sort($matchedSources, SORT_STRING);
                    sort($testPaths, SORT_STRING);
                    $result[$tier][] = [
                        'id' => $entry['id'],
                        'matched_sources' => $matchedSources,
                        'tests' => $testPaths,
                    ];
                }
            }
            usort($result[$tier], static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        }

        return $result;
    }
}
