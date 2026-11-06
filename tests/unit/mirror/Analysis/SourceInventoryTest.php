<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Analysis;

use InvalidArgumentException;
use Matma\Php\Analysis\SourceInventory;
use PHPUnit\Framework\TestCase;

class SourceInventoryTest extends TestCase
{
    private function fixtureRoot(): string
    {
        return dirname(__DIR__, 4).'/tests/fixtures/analysis-project';
    }

    public function test_inventory_is_sorted_excludes_vendor_and_keeps_relative_names(): void
    {
        $files = (new SourceInventory($this->fixtureRoot()))->collect('app');
        $paths = array_column($files, 'relative_path');

        self::assertCount(8, $paths);
        self::assertContains('Services/DocumentParser.php', $paths);
        self::assertNotContains('vendor/SkipMe.php', $paths);
        self::assertSame($paths, array_values((static function (array $p): array {
            sort($p, SORT_STRING);
            return $p;
        })($paths)));
    }

    public function test_explicit_absolute_source_root_is_supported(): void
    {
        $root = $this->fixtureRoot();
        $files = (new SourceInventory($root))->collect($root.'/app');

        self::assertCount(8, $files);
        self::assertSame('app/Anonymous/Factory.php', $files[0]['source_file']);
    }

    public function test_nonexistent_source_root_is_not_silently_accepted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SourceInventory($this->fixtureRoot()))->collect('missing');
    }

    public function test_paths_are_slash_normalized(): void
    {
        self::assertSame('app/Domain/Entity.php', SourceInventory::normalize('app\\Domain\\Entity.php'));
    }
}
