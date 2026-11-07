<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

class ScaffoldCommandInteractionTest extends TestCase
{
    private string $outputRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputRoot = sys_get_temp_dir().'/matma-scaffold-cli-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->outputRoot, 0775, true));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputRoot)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->outputRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                $item->isDir() && ! $item->isLink()
                    ? rmdir($item->getPathname())
                    : unlink($item->getPathname());
            }
            rmdir($this->outputRoot);
        }
        parent::tearDown();
    }

    private function source(string $fixture = 'graph-project'): string
    {
        return dirname(__DIR__, 5).'/tests/fixtures/'.$fixture.'/app';
    }

    public function test_scaffold_command_creates_missing_paths_and_regenerates_complete_index(): void
    {
        $args = [
            '--source-root' => $this->source(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'App\Top',
            '--format' => 'json',
        ];

        self::assertSame(0, Artisan::call('matma:scaffold', $args));
        $first = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('scaffolded', $first['status']);
        self::assertSame(0, $first['exit_code']);
        self::assertCount(16, $first['manifests']['written']);
        self::assertCount(5, $first['scaffolds']['created']);
        self::assertFileExists($this->outputRoot.'/unit/mirror/TopTest.php');
        self::assertFileExists($this->outputRoot.'/interaction/mirror/TopInteractionTest.php');
        self::assertFileExists($this->outputRoot.'/mocks/mirror/MockTop.php');
        self::assertFileExists($this->outputRoot.'/mocks/mirror/MockLeft.php');
        self::assertFileExists($this->outputRoot.'/mocks/mirror/MockRight.php');
        self::assertFileDoesNotExist($this->outputRoot.'/unit/mirror/LeftTest.php');

        self::assertSame(0, Artisan::call('matma:scaffold', $args));
        $second = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $second['manifests']['written']);
        self::assertSame([], $second['scaffolds']['created']);
        self::assertCount(5, $second['scaffolds']['existing']);
    }

    public function test_preserves_preexisting_developer_test_on_partial_mirror_tree(): void
    {
        $dir = $this->outputRoot.'/unit/mirror';
        self::assertTrue(mkdir($dir, 0775, true));
        $manual = $dir.'/TopTest.php';
        file_put_contents($manual, "<?php // developer's test\n");

        self::assertSame(0, Artisan::call('matma:scaffold', [
            '--source-root' => $this->source(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'App\Top',
            '--format' => 'json',
        ]));
        $data = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame("<?php // developer's test\n", file_get_contents($manual));
        self::assertContains(str_replace('\\', '/', $manual), $data['scaffolds']['existing']);
        self::assertCount(4, $data['scaffolds']['created']);
    }

    public function test_final_and_readonly_dependencies_produce_actionable_non_mockable_warnings(): void
    {
        self::assertSame(1, Artisan::call('matma:scaffold', [
            '--source-root' => $this->source('scaffold-project'),
            '--tests-root' => $this->outputRoot,
            '--target' => 'App\Consumer',
            '--format' => 'yaml',
        ]));
        $data = Yaml::parse(Artisan::output());

        self::assertSame('scaffold_incomplete', $data['status']);
        self::assertCount(2, $data['diagnostics']);
        self::assertSame(['non_mockable', 'non_mockable'], array_column($data['diagnostics'], 'code'));
        self::assertFileExists($this->outputRoot.'/unit/mirror/ConsumerTest.php');
        self::assertFileExists($this->outputRoot.'/mocks/mirror/MockContract.php');
        self::assertFileDoesNotExist($this->outputRoot.'/mocks/mirror/MockFinalService.php');
        self::assertFileDoesNotExist($this->outputRoot.'/mocks/mirror/MockReadonlyService.php');
    }

    public function test_invalid_source_refuses_to_create_tests_or_manifests(): void
    {
        self::assertSame(1, Artisan::call('matma:scaffold', [
            '--source-root' => dirname(__DIR__, 5).'/tests/fixtures/analysis-project/invalid',
            '--tests-root' => $this->outputRoot,
            '--format' => 'json',
        ]));

        $data = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $data['status']);
        self::assertDirectoryDoesNotExist($this->outputRoot.'/mirror');
        self::assertDirectoryDoesNotExist($this->outputRoot.'/unit');
    }

    public function test_non_mockable_anonymous_class_creates_failing_tests_but_reports_incomplete(): void
    {
        self::assertSame(1, Artisan::call('matma:scaffold', [
            '--source-root' => $this->source(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'App\Missing',
            '--format' => 'json',
        ]));
        $missing = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('invalid_target_or_path', $missing['status']);

        self::assertSame(1, Artisan::call('matma:scaffold', [
            '--source-root' => $this->source(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'AnonymousFactory.php',
            '--format' => 'json',
        ]));
        $data = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('scaffold_incomplete', $data['status']);
        self::assertSame('non_mockable', $data['diagnostics'][0]['code']);
        self::assertFileExists($this->outputRoot.'/unit/mirror/AnonymousFactoryTest.php');
    }
}
