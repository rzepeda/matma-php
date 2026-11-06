<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Scaffolding;

use InvalidArgumentException;
use Matma\Php\Scaffolding\ScaffoldWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ScaffoldWriterTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = sys_get_temp_dir().'/matma-writer-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->project.'/app', 0775, true));
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->project);
        parent::tearDown();
    }

    public function test_exclusive_creation_preserves_user_edits_on_repeated_runs(): void
    {
        $writer = new ScaffoldWriter($this->project, 'tests', 'app');
        $paths = [
            'tests/unit/mirror/Services/ServiceTest.php' => '<?php // generated',
            'tests/interaction/mirror/Services/ServiceInteractionTest.php' => '<?php // interaction',
            'tests/mocks/mirror/Services/MockService.php' => '<?php // mock',
        ];

        $first = $writer->write($paths);
        self::assertCount(3, $first['created']);
        self::assertSame([], $first['existing']);

        $owned = $this->project.'/tests/unit/mirror/Services/ServiceTest.php';
        file_put_contents($owned, '<?php // hand edited');
        $second = $writer->write($paths);

        self::assertSame([], $second['created']);
        self::assertCount(3, $second['existing']);
        self::assertSame('<?php // hand edited', file_get_contents($owned));
    }

    public function test_preflight_rejects_directory_destination_before_creating_any_file(): void
    {
        self::assertTrue(mkdir($this->project.'/tests/unit/mirror/Services/ServiceTest.php', 0775, true));
        $writer = new ScaffoldWriter($this->project, 'tests', 'app');
        $this->expectException(RuntimeException::class);
        $writer->write([
            'tests/interaction/mirror/AnotherTest.php' => '<?php',
            'tests/unit/mirror/Services/ServiceTest.php' => '<?php',
        ]);
    }

    public function test_paths_cannot_escape_or_enter_non_mirrored_tiers(): void
    {
        $writer = new ScaffoldWriter($this->project, 'tests', 'app');
        foreach ([
            'tests/../../unsafe.php',
            'tests/business/WorkflowTest.php',
            'app/Injected.php',
        ] as $path) {
            try {
                $writer->preflight([$path => '<?php']);
                self::fail('Unsafe destination was accepted: '.$path);
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_tests_root_cannot_overlap_application_source(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScaffoldWriter($this->project, 'app', 'app');
    }

    public function test_symlink_ancestor_and_file_are_rejected(): void
    {
        if (! function_exists('symlink')) {
            self::markTestSkipped('Symlinks unavailable.');
        }

        self::assertTrue(mkdir($this->project.'/tests/unit', 0775, true));
        self::assertTrue(symlink($this->project.'/app', $this->project.'/tests/unit/mirror'));
        $this->expectException(RuntimeException::class);
        (new ScaffoldWriter($this->project, 'tests', 'app'))
            ->preflight(['tests/unit/mirror/HijackedTest.php' => '<?php']);
    }
}
