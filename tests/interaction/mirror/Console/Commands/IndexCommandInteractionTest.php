<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

class IndexCommandInteractionTest extends TestCase
{
    private string $outputRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputRoot = sys_get_temp_dir().'/matma-index-cli-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->outputRoot, 0775, true));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputRoot)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->outputRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($items as $item) {
                $item->isDir() && ! $item->isLink()
                    ? rmdir($item->getPathname())
                    : unlink($item->getPathname());
            }
            rmdir($this->outputRoot);
        }
        parent::tearDown();
    }

    private function fixture(string $directory = 'graph-project/app'): string
    {
        return dirname(__DIR__, 5).'/tests/fixtures/'.$directory;
    }

    public function test_real_index_command_writes_all_manifests_and_resolves_target(): void
    {
        $code = Artisan::call('matma:index', [
            '--source-root' => $this->fixture(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'App\Base',
            '--format' => 'json',
        ]);

        self::assertSame(0, $code);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('indexed', $payload['status']);
        self::assertSame(15, $payload['summary']['indexed_files']);
        self::assertStringEndsWith('/app/Base.php', $payload['target']);
        self::assertCount(15, $payload['manifests']['written']);

        $file = $this->outputRoot.'/mirror/Base.auto.yaml';
        self::assertFileExists($file);
        $manifest = Yaml::parse((string) file_get_contents($file));
        self::assertCount(2, $manifest['dependents']['level_1']);
        self::assertStringEndsWith('/app/Left.php', $manifest['dependents']['level_1'][0]['source_file']);
        self::assertStringEndsWith('/app/Right.php', $manifest['dependents']['level_1'][1]['source_file']);
    }

    public function test_second_run_does_not_rewrite_unchanged_manifests(): void
    {
        $args = [
            '--source-root' => $this->fixture(),
            '--tests-root' => $this->outputRoot,
            '--format' => 'json',
        ];
        self::assertSame(0, Artisan::call('matma:index', $args));
        self::assertSame(0, Artisan::call('matma:index', $args));
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $payload['manifests']['written']);
        self::assertCount(15, $payload['manifests']['unchanged']);
    }

    public function test_invalid_project_is_rejected_without_writing(): void
    {
        $code = Artisan::call('matma:index', [
            '--source-root' => $this->fixture('analysis-project/invalid'),
            '--tests-root' => $this->outputRoot,
            '--format' => 'json',
        ]);

        self::assertSame(1, $code);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $payload['status']);
        self::assertGreaterThan(0, $payload['summary']['errors']);
        self::assertDirectoryDoesNotExist($this->outputRoot.'/mirror');
    }

    public function test_unknown_target_does_not_write_incomplete_index(): void
    {
        $code = Artisan::call('matma:index', [
            '--source-root' => $this->fixture(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'App\Missing',
            '--format' => 'json',
        ]);

        self::assertSame(1, $code);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('invalid_target_or_path', $payload['status']);
        self::assertDirectoryDoesNotExist($this->outputRoot.'/mirror');
    }

    public function test_index_scaffold_flag_generates_only_selected_failing_tests_and_mocks(): void
    {
        $code = Artisan::call('matma:index', [
            '--source-root' => $this->fixture(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'App\Base',
            '--scaffold' => true,
            '--format' => 'yaml',
        ]);

        self::assertSame(0, $code);
        $payload = Yaml::parse(Artisan::output());
        self::assertSame('scaffolded', $payload['status']);
        self::assertCount(15, $payload['manifests']['written']);
        self::assertCount(3, $payload['scaffolds']['created']);
        self::assertFileExists($this->outputRoot.'/mirror/Base.auto.yaml');
        self::assertFileExists($this->outputRoot.'/unit/mirror/BaseTest.php');
        self::assertFileExists($this->outputRoot.'/interaction/mirror/BaseInteractionTest.php');
        self::assertFileExists($this->outputRoot.'/mocks/mirror/MockBase.php');
        self::assertFileDoesNotExist($this->outputRoot.'/unit/mirror/TopTest.php');
        self::assertStringContainsString(
            "self::fail(",
            (string) file_get_contents($this->outputRoot.'/unit/mirror/BaseTest.php'),
        );
    }
}
