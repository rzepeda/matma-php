<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Scaffolding;

use InvalidArgumentException;
use Matma\Php\Analysis\ProjectInspector;
use Matma\Php\Indexing\DependencyGraph;
use Matma\Php\Scaffolding\ScaffoldGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class ScaffoldGeneratorTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = sys_get_temp_dir().'/matma-generator-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->project, 0775, true));
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->project);
        parent::tearDown();
    }

    private function graph(string $fixture = 'graph-project'): array
    {
        $root = dirname(__DIR__, 4).'/tests/fixtures/'.$fixture;
        return (new DependencyGraph())->build((new ProjectInspector($root))->inspect('app', 'tests'));
    }

    private function generator(): ScaffoldGenerator
    {
        return new ScaffoldGenerator($this->project, 'app', 'tests');
    }

    public function test_target_scaffolds_tests_and_centralized_dependency_mocks_only(): void
    {
        $plan = $this->generator()->plan($this->graph(), 'app/Top.php');

        self::assertSame([], $plan['diagnostics']);
        self::assertCount(5, $plan['files']);
        self::assertArrayHasKey('tests/unit/mirror/TopTest.php', $plan['files']);
        self::assertArrayHasKey('tests/interaction/mirror/TopInteractionTest.php', $plan['files']);
        foreach (['MockTop.php', 'MockLeft.php', 'MockRight.php'] as $name) {
            self::assertArrayHasKey('tests/mocks/mirror/'.$name, $plan['files']);
        }
        self::assertArrayNotHasKey('tests/unit/mirror/LeftTest.php', $plan['files']);
        self::assertStringContainsString('self::fail(', $plan['files']['tests/unit/mirror/TopTest.php']);
        self::assertStringContainsString('self::fail(', $plan['files']['tests/interaction/mirror/TopInteractionTest.php']);
        self::assertStringNotContainsString('assertTrue(true)', $plan['files']['tests/unit/mirror/TopTest.php']);
        self::assertStringContainsString('createMock(', $plan['files']['tests/mocks/mirror/MockTop.php']);
    }

    public function test_scaffold_runs_full_index_and_is_idempotent(): void
    {
        $graph = $this->graph();
        $generator = $this->generator();
        $first = $generator->run($graph, 'app/Top.php');

        self::assertCount(15, $first['manifests']['written']);
        self::assertCount(5, $first['scaffolds']['created']);
        self::assertSame([], $first['diagnostics']);
        self::assertFileExists($this->project.'/tests/mirror/Base.auto.yaml');
        $manifest = Yaml::parse((string) file_get_contents($this->project.'/tests/mirror/Top.auto.yaml'));
        self::assertSame('App\Top', $manifest['class_name']);

        $path = $this->project.'/tests/unit/mirror/TopTest.php';
        file_put_contents($path, "<?php // authored by developer\n");
        $second = $generator->run($graph, 'app/Top.php');

        self::assertSame([], $second['scaffolds']['created']);
        self::assertCount(5, $second['scaffolds']['existing']);
        self::assertSame([], $second['manifests']['written']);
        self::assertCount(15, $second['manifests']['unchanged']);
        self::assertSame("<?php // authored by developer\n", file_get_contents($path));
    }

    public function test_readonly_and_final_dependencies_report_diagnostics_without_fake_mocks(): void
    {
        $plan = $this->generator()->plan($this->graph('scaffold-project'), 'app/Consumer.php');

        self::assertCount(2, $plan['diagnostics']);
        self::assertSame(['app/FinalService.php', 'app/ReadonlyService.php'], array_column($plan['diagnostics'], 'source_file'));
        self::assertSame(['non_mockable', 'non_mockable'], array_column($plan['diagnostics'], 'code'));
        self::assertArrayHasKey('tests/unit/mirror/ConsumerTest.php', $plan['files']);
        self::assertArrayHasKey('tests/interaction/mirror/ConsumerInteractionTest.php', $plan['files']);
        self::assertArrayHasKey('tests/mocks/mirror/MockConsumer.php', $plan['files']);
        self::assertArrayHasKey('tests/mocks/mirror/MockContract.php', $plan['files']);
        self::assertArrayNotHasKey('tests/mocks/mirror/MockFinalService.php', $plan['files']);
        self::assertArrayNotHasKey('tests/mocks/mirror/MockReadonlyService.php', $plan['files']);
    }

    public function test_anonymous_classes_receive_file_scoped_failing_tests_but_no_fake_mock(): void
    {
        $plan = $this->generator()->plan($this->graph(), 'app/AnonymousFactory.php');

        self::assertCount(1, $plan['diagnostics']);
        self::assertSame('non_mockable', $plan['diagnostics'][0]['code']);
        self::assertArrayHasKey('tests/unit/mirror/AnonymousFactoryTest.php', $plan['files']);
        self::assertArrayHasKey('tests/interaction/mirror/AnonymousFactoryInteractionTest.php', $plan['files']);
        self::assertArrayNotHasKey('tests/mocks/mirror/MockAnonymousFactory.php', $plan['files']);
        self::assertStringContainsString('file:app/AnonymousFactory.php', $plan['files']['tests/unit/mirror/AnonymousFactoryTest.php']);
    }

    public function test_untestable_traits_cannot_be_selected_as_standalone_targets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator()->plan($this->graph(), 'app/HelperTrait.php');
    }

    public function test_generated_mock_factory_uses_real_phpunit_mockobject(): void
    {
        $this->generator()->run($this->graph('scaffold-project'), 'app/Contract.php');
        require_once dirname(__DIR__, 4).'/tests/fixtures/scaffold-project/app/Contract.php';
        require_once $this->project.'/tests/mocks/mirror/MockContract.php';

        $mock = \MatmaGenerated\mocks\mirror\MockContract::create(
            fn (string $type) => $this->createMock($type),
        );
        self::assertInstanceOf(\App\Contract::class, $mock);
        $mock->method('run')->willReturn('stubbed');
        self::assertSame('stubbed', $mock->run());
    }

    public function test_generated_phpunit_test_is_executable_and_fails_until_implemented(): void
    {
        $this->generator()->run($this->graph(), 'app/Top.php');
        $file = $this->project.'/tests/unit/mirror/TopTest.php';
        $phpunit = dirname(__DIR__, 4).'/vendor/bin/phpunit';
        $command = new Process([PHP_BINARY, $phpunit, '--no-configuration', $file]);
        $command->run();

        self::assertNotSame(0, $command->getExitCode());
        self::assertStringContainsString('MATMA: implement unit assertions', $command->getOutput().$command->getErrorOutput());
    }

    public function test_namespace_follows_existing_composer_autoload_dev_mapping(): void
    {
        file_put_contents($this->project.'/composer.json', json_encode([
            'autoload-dev' => ['psr-4' => ['Tests\\' => 'tests/']],
        ], JSON_THROW_ON_ERROR));

        $plan = $this->generator()->plan($this->graph(), 'app/Base.php');
        self::assertStringContainsString(
            'namespace Tests\unit\mirror;',
            $plan['files']['tests/unit/mirror/BaseTest.php'],
        );
        self::assertStringContainsString(
            'namespace Tests\mocks\mirror;',
            $plan['files']['tests/mocks/mirror/MockBase.php'],
        );
    }
}
