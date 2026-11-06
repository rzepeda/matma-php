<?php

declare(strict_types=1);

namespace Matma\Php\Analysis;

use InvalidArgumentException;

final class MirrorMapper
{
    /**
     * Compute mirrors by string transformation, not a filesystem search.
     *
     * @return array{unit_test: string, interaction_test: string, mock_file: string, mock_class: string, manifest: string}
     */
    public function map(string $relativeSourcePath, string $testsRoot): array
    {
        $relativeSourcePath = SourceInventory::normalize($relativeSourcePath);
        if (str_starts_with($relativeSourcePath, '/')
            || preg_match('~(^|/)\.\.(/|$)~', $relativeSourcePath) === 1
            || ! str_ends_with(strtolower($relativeSourcePath), '.php')) {
            throw new InvalidArgumentException('Mirror source path must be a relative PHP file inside the source root.');
        }

        $directory = dirname($relativeSourcePath);
        $directory = $directory === '.' ? '' : $directory.'/';
        $stem = substr(basename($relativeSourcePath), 0, -4);
        $root = rtrim(SourceInventory::normalize($testsRoot), '/');

        if ($root === '') {
            throw new InvalidArgumentException('Tests root must not be empty.');
        }

        return [
            'unit_test' => $root.'/unit/mirror/'.$directory.$stem.'Test.php',
            'interaction_test' => $root.'/interaction/mirror/'.$directory.$stem.'InteractionTest.php',
            'mock_file' => $root.'/mocks/mirror/'.$directory.'Mock'.$stem.'.php',
            'mock_class' => 'Mock'.$stem,
            'manifest' => $root.'/mirror/'.$directory.$stem.'.auto.yaml',
        ];
    }
}
