<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Indexing\ManifestWriter;
use Matma\Php\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

class ImpactCommandInteractionTest extends TestCase
{
    private string $outputRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputRoot = sys_get_temp_dir().'/matma-impact-cli-'.bin2hex(random_bytes(8));
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
                $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->outputRoot);
        }
        parent::tearDown();
    }

    private function sourceRoot(): string
    {
        return dirname(__DIR__, 5).'/tests/fixtures/graph-project/app';
    }

    private function index(): void
    {
        self::assertSame(0, Artisan::call('matma:index', [
            '--source-root' => $this->sourceRoot(),
            '--tests-root' => $this->outputRoot,
            '--format' => 'json',
        ]));
    }

    public function test_impact_selects_real_reverse_dependencies_from_persisted_yaml(): void
    {
        $this->index();
        $exit = Artisan::call('matma:impact', [
            '--source-root' => $this->sourceRoot(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'Base.php',
            '--level' => '2',
            '--format' => 'json',
        ]);

        self::assertSame(0, $exit);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('selected', $payload['status']);
        self::assertSame(2, $payload['level']);
        self::assertSame(4, $payload['summary']['affected_sources']);
        self::assertCount(4, $payload['tests']['unit']);
        self::assertCount(4, $payload['tests']['interaction']);
        self::assertCount(8, $payload['missing_tests']);
        self::assertFileExists($this->outputRoot.'/mirror/.index.auto.yaml');
    }

    public function test_yaml_and_text_render_selected_paths_not_just_a_status(): void
    {
        $this->index();
        $args = [
            '--source-root' => $this->sourceRoot(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'Base.php',
            '--level' => '1',
            '--format' => 'yaml',
        ];
        self::assertSame(0, Artisan::call('matma:impact', $args));
        $yaml = Yaml::parse(Artisan::output());
        self::assertSame('selected', $yaml['status']);
        self::assertCount(3, $yaml['tests']['unit']);
        self::assertCount(3, $yaml['tests']['interaction']);

        $args['--format'] = 'text';
        self::assertSame(0, Artisan::call('matma:impact', $args));
        $text = Artisan::output();
        self::assertStringContainsString('BaseTest.php', $text);
        self::assertStringContainsString('LeftInteractionTest.php', $text);
        self::assertStringContainsString('[selected]', $text);
    }

    public function test_missing_index_and_missing_target_manifest_fail_actionably(): void
    {
        $args = [
            '--source-root' => $this->sourceRoot(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'Base.php',
            '--format' => 'json',
        ];

        self::assertSame(1, Artisan::call('matma:impact', $args));
        $missing = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('missing_manifest', $missing['status']);

        $this->index();
        unlink($this->outputRoot.'/mirror/.index.auto.yaml');
        self::assertSame(1, Artisan::call('matma:impact', $args));
        $missing = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('missing_index', $missing['status']);
        self::assertStringContainsString('matma:index', $missing['message']);
    }

    public function test_inconsistent_fingerprint_fails_instead_of_returning_stale_selection(): void
    {
        $this->index();
        $path = $this->outputRoot.'/mirror/Base.auto.yaml';
        $manifest = Yaml::parse((string) file_get_contents($path));
        $manifest['index_fingerprint'] = str_repeat('0', 64);
        file_put_contents($path, ManifestWriter::HEADER.Yaml::dump($manifest, 6, 2));

        self::assertSame(1, Artisan::call('matma:impact', [
            '--source-root' => $this->sourceRoot(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'Base.php',
            '--format' => 'json',
        ]));
        $response = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('stale_index', $response['status']);
    }

    public function test_registered_business_and_regression_are_suggestions_not_executed_tests(): void
    {
        $this->index();
        file_put_contents($this->outputRoot.'/matma-verification.yaml', Yaml::dump([
            'schema_version' => 1,
            'business' => [
                [
                    'id' => 'business-smoke',
                    'sources' => [$this->sourceRoot().'/Top.php'],
                    'tests' => [$this->outputRoot.'/business/BusinessSmokeTest.php'],
                ],
            ],
            'regression' => [
                [
                    'id' => 'manual-regression',
                    'sources' => [$this->sourceRoot().'/Base.php'],
                    'tests' => [$this->outputRoot.'/regression/ManualRegressionTest.php'],
                ],
            ],
        ], 4, 2));

        self::assertSame(0, Artisan::call('matma:impact', [
            '--source-root' => $this->sourceRoot(),
            '--tests-root' => $this->outputRoot,
            '--target' => 'Base.php',
            '--level' => '2',
            '--format' => 'json',
        ]));
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['business-smoke'], array_column($result['registered_workflows']['business'], 'id'));
        self::assertSame(['manual-regression'], array_column($result['registered_workflows']['regression'], 'id'));
        self::assertNotContains($this->outputRoot.'/business/BusinessSmokeTest.php', $result['tests']['unit']);
    }

    public function test_target_is_required_and_scaffold_flag_is_rejected_without_writes(): void
    {
        self::assertSame(64, Artisan::call('matma:impact', ['--format' => 'json']));
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('invalid_options', $payload['status']);

        self::assertSame(64, Artisan::call('matma:impact', [
            '--target' => 'Base.php',
            '--scaffold' => true,
            '--format' => 'json',
        ]));
        self::assertDirectoryDoesNotExist($this->outputRoot.'/mirror');
    }
}
