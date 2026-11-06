<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Impact;

use Matma\Php\Analysis\ProjectInspector;
use Matma\Php\Impact\ImpactException;
use Matma\Php\Impact\ImpactResolver;
use Matma\Php\Impact\ManifestLocator;
use Matma\Php\Indexing\DependencyGraph;
use Matma\Php\Indexing\IndexMetadata;
use Matma\Php\Indexing\ManifestWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ImpactResolverTest extends TestCase
{
    private string $project;
    private ManifestLocator $locator;
    private ImpactResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = sys_get_temp_dir().'/matma-impact-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->project.'/app', 0775, true));

        $fixture = dirname(__DIR__, 4).'/tests/fixtures/graph-project';
        copy($fixture.'/composer.json', $this->project.'/composer.json');
        foreach (glob($fixture.'/app/*.php') as $source) {
            copy($source, $this->project.'/app/'.basename($source));
        }

        $inspection = (new ProjectInspector($this->project))->inspect('app', 'tests');
        $graph = (new DependencyGraph())->build($inspection);
        $metadata = IndexMetadata::build($graph, $this->project, 'app');
        (new ManifestWriter($this->project, 'tests'))->write($graph['manifests'], $metadata);

        $this->locator = new ManifestLocator($this->project, 'app', 'tests');
        $this->resolver = new ImpactResolver($this->project);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->project)) {
            $entries = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($entries as $entry) {
                $entry->isDir() && ! $entry->isLink()
                    ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($this->project);
        }
        parent::tearDown();
    }

    private function impact(string $target, int $level): array
    {
        return $this->resolver->resolve($this->locator, $target, $level);
    }

    private function getGenerated(string $path): array
    {
        $text = (string) file_get_contents($this->project.'/tests/mirror/'.$path);
        self::assertStringStartsWith(ManifestWriter::HEADER, $text);
        return Yaml::parse(substr($text, strlen(ManifestWriter::HEADER)));
    }

    private function putGenerated(string $path, array $yaml): void
    {
        file_put_contents(
            $this->project.'/tests/mirror/'.$path,
            ManifestWriter::HEADER.Yaml::dump($yaml, 6, 2),
        );
    }

    public function test_level_zero_selects_only_target_unit_test(): void
    {
        $impact = $this->impact('App\Base', 0);

        self::assertSame('App\Base', $impact['target']['class_name']);
        self::assertSame('app/Base.php', $impact['target']['source_file']);
        self::assertSame(['tests/unit/mirror/BaseTest.php'], $impact['tests']['unit']);
        self::assertSame([], $impact['tests']['interaction']);
        self::assertSame([0], array_column($impact['affected_sources'], 'depth'));
        self::assertSame(1, $impact['summary']['missing_tests']);
        self::assertSame([], $impact['registered_workflows']['business']);
        self::assertSame([], $impact['registered_workflows']['regression']);
    }

    public function test_level_one_includes_direct_dependents_and_interactions(): void
    {
        $impact = $this->impact('Base.php', 1);
        self::assertSame(
            ['app/Base.php', 'app/Left.php', 'app/Right.php'],
            array_column($impact['affected_sources'], 'source_file'),
        );
        self::assertSame([0, 1, 1], array_column($impact['affected_sources'], 'depth'));
        self::assertSame(
            ['tests/unit/mirror/BaseTest.php', 'tests/unit/mirror/LeftTest.php', 'tests/unit/mirror/RightTest.php'],
            $impact['tests']['unit'],
        );
        self::assertSame(
            ['tests/interaction/mirror/BaseInteractionTest.php', 'tests/interaction/mirror/LeftInteractionTest.php', 'tests/interaction/mirror/RightInteractionTest.php'],
            $impact['tests']['interaction'],
        );
    }

    public function test_level_two_and_three_have_inclusive_shortest_path_levels(): void
    {
        $two = $this->impact('app/Base.php', 2);
        self::assertSame(
            ['app/Base.php', 'app/Left.php', 'app/Right.php', 'app/Top.php'],
            array_column($two['affected_sources'], 'source_file'),
        );
        self::assertSame([0, 1, 1, 2], array_column($two['affected_sources'], 'depth'));

        $three = $this->impact('App\Base', 3);
        self::assertSame('app/Leaf.php', $three['affected_sources'][4]['source_file']);
        self::assertSame(3, $three['affected_sources'][4]['depth']);
        self::assertCount(5, $three['tests']['unit']);
        self::assertCount(5, $three['tests']['interaction']);
        $beyond = $this->impact('App\Base', 200);
        self::assertSame($three['affected_sources'], $beyond['affected_sources']);
        self::assertSame($three['tests'], $beyond['tests']);
    }

    public function test_cycles_are_deduplicated_and_origin_is_not_revisited(): void
    {
        $impact = $this->impact('App\CycleA', 4);
        self::assertSame(['app/CycleA.php', 'app/CycleB.php'], array_column($impact['affected_sources'], 'source_file'));
        self::assertSame([0, 1], array_column($impact['affected_sources'], 'depth'));
        self::assertCount(2, $impact['tests']['unit']);
    }

    public function test_trait_and_enum_have_no_independent_unit_test_but_propagate_impact(): void
    {
        $trait = $this->impact('App\HelperTrait', 1);
        self::assertSame(['tests/unit/mirror/UsesTraitTest.php'], $trait['tests']['unit']);
        self::assertSame(['tests/interaction/mirror/UsesTraitInteractionTest.php'], $trait['tests']['interaction']);

        $enum = $this->impact('App\Status', 0);
        self::assertSame([], $enum['tests']['unit']);
        self::assertSame([], $enum['tests']['interaction']);
    }

    public function test_anonymous_file_is_selected_by_containing_filename(): void
    {
        $impact = $this->impact('AnonymousFactory.php', 0);
        self::assertNull($impact['target']['class_name']);
        self::assertSame(['tests/unit/mirror/AnonymousFactoryTest.php'], $impact['tests']['unit']);
    }

    public function test_registered_workflows_are_separate_and_never_added_to_mirrored_test_lists(): void
    {
        file_put_contents($this->project.'/tests/matma-verification.yaml', Yaml::dump([
            'schema_version' => 1,
            'business' => [
                ['id' => 'top-journey', 'sources' => ['app/Top.php'], 'tests' => ['tests/business/TopWorkflowTest.php']],
                ['id' => 'unrelated', 'sources' => ['app/Isolated.php'], 'tests' => ['tests/business/IsolatedWorkflowTest.php']],
            ],
            'regression' => [
                ['id' => 'left-e2e', 'sources' => ['App\Left'], 'tests' => ['tests/regression/LeftRegressionTest.php']],
            ],
        ], 4, 2));

        $impact = $this->impact('Base.php', 2);
        self::assertSame(['top-journey'], array_column($impact['registered_workflows']['business'], 'id'));
        self::assertSame(['left-e2e'], array_column($impact['registered_workflows']['regression'], 'id'));
        self::assertSame(1, $impact['summary']['business_workflows']);
        self::assertSame(1, $impact['summary']['regression_workflows']);
        self::assertNotContains('tests/business/TopWorkflowTest.php', $impact['tests']['unit']);
        self::assertNotContains('tests/regression/LeftRegressionTest.php', $impact['tests']['interaction']);
    }

    public function test_invalid_workflow_registry_fails_instead_of_selecting_unsafe_path(): void
    {
        file_put_contents($this->project.'/tests/matma-verification.yaml', Yaml::dump([
            'schema_version' => 1,
            'business' => [
                ['id' => 'bad', 'sources' => ['App\Base'], 'tests' => ['../../UnsafeTest.php']],
            ],
        ], 4, 2));

        $this->expectException(ImpactException::class);
        $this->expectExceptionMessage('tests/business');
        $this->impact('Base.php', 0);
    }

    public function test_missing_target_manifest_fails_with_actionable_error(): void
    {
        unlink($this->project.'/tests/mirror/Base.auto.yaml');
        $this->expectException(ImpactException::class);
        $this->expectExceptionMessage('php artisan matma:index');
        $this->impact('Base.php', 0);
    }

    public function test_missing_generated_inventory_is_not_silently_accepted(): void
    {
        unlink($this->project.'/tests/mirror/.index.auto.yaml');
        try {
            $this->impact('Base.php', 0);
            self::fail('Missing inventory was accepted.');
        } catch (ImpactException $e) {
            self::assertSame('missing_index', $e->status);
        }
    }

    public function test_modified_source_file_is_reported_stale_without_parser(): void
    {
        file_put_contents($this->project.'/app/Base.php', "<?php invalid @@");
        try {
            $this->impact('Base.php', 0);
            self::fail('Stale source was accepted.');
        } catch (ImpactException $e) {
            self::assertSame('stale_index', $e->status);
        }
    }

    public function test_new_file_in_a_watched_directory_invalidates_index(): void
    {
        file_put_contents($this->project.'/app/NewSource.php', "<?php class NewSource {}");
        touch($this->project.'/app', time() + 5);

        try {
            $this->impact('Base.php', 0);
            self::fail('New source was not detected.');
        } catch (ImpactException $e) {
            self::assertSame('stale_index', $e->status);
        }
    }

    public function test_mismatched_fingerprint_or_schema_is_not_trusted(): void
    {
        $manifest = $this->getGenerated('Base.auto.yaml');
        $manifest['index_fingerprint'] = str_repeat('a', 64);
        $this->putGenerated('Base.auto.yaml', $manifest);

        try {
            $this->impact('Base.php', 0);
            self::fail('Invalid fingerprint was accepted.');
        } catch (ImpactException $e) {
            self::assertSame('stale_index', $e->status);
        }

        $manifest['schema_version'] = 2;
        $this->putGenerated('Base.auto.yaml', $manifest);
        try {
            $this->impact('Base.php', 0);
            self::fail('Unsupported manifest schema was accepted.');
        } catch (ImpactException $e) {
            self::assertSame('invalid_manifest', $e->status);
        }
    }

    public function test_manifest_query_does_not_invoke_php_parser(): void
    {
        // Replace source with invalid PHP, but explicitly update recorded
        // content hashes and fingerprint, simulating a trusted fresh index.
        // An AST reparse would throw/diagnose; the manifest-only query succeeds.
        file_put_contents($this->project.'/app/Base.php', "<?php impossible syntax @@");
        $inventory = $this->getGenerated('.index.auto.yaml');
        foreach ($inventory['source_inputs'] as &$input) {
            if ($input[0] === 'app/Base.php') {
                $input[1] = hash_file('sha256', $this->project.'/app/Base.php');
            }
        }
        unset($input);
        $newFingerprint = hash('sha256', json_encode([
            'schema' => 1,
            'inputs' => $inventory['source_inputs'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $inventory['index_fingerprint'] = $newFingerprint;
        $this->putGenerated('.index.auto.yaml', $inventory);

        $manifest = $this->getGenerated('Base.auto.yaml');
        $manifest['index_fingerprint'] = $newFingerprint;
        $this->putGenerated('Base.auto.yaml', $manifest);

        $result = $this->impact('Base.php', 2);
        self::assertCount(4, $result['affected_sources']);
        self::assertSame($newFingerprint, $result['index_fingerprint']);
    }
}
