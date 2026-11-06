<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Indexing;

use InvalidArgumentException;
use Matma\Php\Analysis\ProjectInspector;
use Matma\Php\Indexing\DependencyGraph;
use Matma\Php\Indexing\ManifestWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class ManifestWriterTest extends TestCase
{
    private string $outputRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputRoot = sys_get_temp_dir().'/matma-manifests-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->outputRoot, 0775, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->outputRoot);
        parent::tearDown();
    }

    private function manifests(): array
    {
        $fixture = dirname(__DIR__, 4).'/tests/fixtures/graph-project';
        return (new DependencyGraph())->build((new ProjectInspector($fixture))->inspect('app', 'tests'))['manifests'];
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }

    public function test_real_yaml_has_blueprint_fields_and_generator_warning(): void
    {
        $writer = new ManifestWriter($this->outputRoot, 'tests');
        $result = $writer->write($this->manifests());

        self::assertCount(15, $result['written']);
        $path = $this->outputRoot.'/tests/mirror/Top.auto.yaml';
        self::assertFileExists($path);
        $raw = (string) file_get_contents($path);
        self::assertStringStartsWith(ManifestWriter::HEADER, $raw);
        $manifest = Yaml::parse($raw);
        self::assertSame('App\Top', $manifest['class_name']);
        self::assertSame('app/Top.php', $manifest['source_file']);
        self::assertSame(['App\Left', 'App\Right'], array_column($manifest['dependencies'], 'class_name'));
        self::assertSame('tests/unit/mirror/TopTest.php', $manifest['mirrors']['unit_test']);
        self::assertSame('tests/interaction/mirror/TopInteractionTest.php', $manifest['mirrors']['interaction_test']);
        self::assertSame('tests/mocks/mirror/MockTop.php', $manifest['mirrors']['mock_file']);
        self::assertSame('MockTop', $manifest['mirrors']['mock_class']);
    }

    public function test_second_full_rebuild_is_byte_stable_and_does_not_rewrite_identical_file(): void
    {
        $writer = new ManifestWriter($this->outputRoot, 'tests');
        $first = $writer->write($this->manifests());
        self::assertCount(15, $first['written']);
        $before = (string) file_get_contents($this->outputRoot.'/tests/mirror/Base.auto.yaml');

        $second = $writer->write($this->manifests());
        self::assertSame([], $second['written']);
        self::assertSame([], $second['removed']);
        self::assertCount(15, $second['unchanged']);
        self::assertSame($before, file_get_contents($this->outputRoot.'/tests/mirror/Base.auto.yaml'));
    }

    public function test_stale_owned_manifest_is_pruned_but_user_files_are_preserved(): void
    {
        $writer = new ManifestWriter($this->outputRoot, 'tests');
        $manifests = $this->manifests();
        $writer->write($manifests);

        $mirror = $this->outputRoot.'/tests/mirror/';
        file_put_contents($mirror.'Keep.auto.yaml', "# User-authored file\nsafe: true\n");
        file_put_contents($mirror.'old.auto.yaml', ManifestWriter::HEADER."class_name: Old\n");
        unset($manifests['app/Leaf.php']);

        $result = $writer->write($manifests);
        self::assertSame(['tests/mirror/Leaf.auto.yaml', 'tests/mirror/old.auto.yaml'], $result['removed']);
        self::assertFileExists($mirror.'Keep.auto.yaml');
        self::assertFileDoesNotExist($mirror.'Leaf.auto.yaml');
    }

    public function test_unowned_collision_fails_before_writing_any_generated_manifests(): void
    {
        $mirror = $this->outputRoot.'/tests/mirror/';
        self::assertTrue(mkdir($mirror, 0775, true));
        file_put_contents($mirror.'Top.auto.yaml', "class_name: Manual\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-MATMA');
        (new ManifestWriter($this->outputRoot, 'tests'))->write($this->manifests());
    }

    public function test_parent_traversal_tests_root_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ManifestWriter($this->outputRoot, '../outside'))->write($this->manifests());
    }
}
