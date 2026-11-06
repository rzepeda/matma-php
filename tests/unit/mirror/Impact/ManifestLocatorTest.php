<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Impact;

use Matma\Php\Impact\ImpactException;
use Matma\Php\Impact\ManifestLocator;
use PHPUnit\Framework\TestCase;

class ManifestLocatorTest extends TestCase
{
    private function fixture(): string
    {
        return dirname(__DIR__, 4).'/tests/fixtures/graph-project';
    }

    public function test_class_name_and_file_path_resolve_to_exact_same_manifest_without_search(): void
    {
        $locator = new ManifestLocator($this->fixture(), 'app', 'tests');
        $class = $locator->locate('App\Base');
        $file = $locator->locate('app/Base.php');
        $relative = $locator->locate('Base.php');

        self::assertSame($class, $file);
        self::assertSame($class, $relative);
        self::assertSame('app/Base.php', $class['source_file']);
        self::assertSame($this->fixture().'/tests/mirror/Base.auto.yaml', $class['manifest_path']);
        self::assertSame($this->fixture().'/tests/mirror/.index.auto.yaml', $locator->inventoryPath());
    }

    public function test_absolute_source_path_is_supported(): void
    {
        $locator = new ManifestLocator($this->fixture(), 'app', 'tests');
        $result = $locator->locate($this->fixture().'/app/Top.php');
        self::assertSame('app/Top.php', $result['source_file']);
    }

    public function test_tests_root_is_independent_of_source_root(): void
    {
        $locator = new ManifestLocator($this->fixture(), 'app', 'custom/test-location');
        $result = $locator->locate('App\Left');

        self::assertSame(
            $this->fixture().'/custom/test-location/mirror/Left.auto.yaml',
            $result['manifest_path'],
        );
    }

    public function test_unsafe_parent_traversal_is_rejected_without_reading_any_files(): void
    {
        $locator = new ManifestLocator($this->fixture(), 'app', 'tests');
        try {
            $locator->locate('../Secret.php');
            self::fail('Parent traversal was accepted.');
        } catch (ImpactException $exception) {
            self::assertSame('invalid_target', $exception->status);
        }
    }

    public function test_unmapped_class_has_actionable_filename_fallback(): void
    {
        $locator = new ManifestLocator($this->fixture(), 'app', 'tests');
        try {
            $locator->locate('Outside\DifferentService');
            self::fail('Unmapped FQCN was accepted.');
        } catch (ImpactException $exception) {
            self::assertSame('invalid_target', $exception->status);
            self::assertStringContainsString('source-file.php', $exception->getMessage());
        }
    }
}
