<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Indexing;

use InvalidArgumentException;
use Matma\Php\Analysis\ProjectInspector;
use Matma\Php\Indexing\DependencyGraph;
use PHPUnit\Framework\TestCase;

class DependencyGraphTest extends TestCase
{
    private function graph(): array
    {
        $root = dirname(__DIR__, 4).'/tests/fixtures/graph-project';
        return (new DependencyGraph())->build((new ProjectInspector($root))->inspect('app', 'tests'));
    }

    public function test_chain_and_diamond_use_shortest_path_reverse_levels(): void
    {
        $graph = $this->graph();
        $base = $graph['manifests']['app/Base.php'];

        self::assertSame([], $base['dependencies']);
        self::assertSame(['app/Left.php', 'app/Right.php'], array_column($base['dependents']['level_1'], 'source_file'));
        self::assertSame(['app/Top.php'], array_column($base['dependents']['level_2'], 'source_file'));
        self::assertSame(['app/Leaf.php'], array_column($base['dependents']['level_3'], 'source_file'));
        self::assertCount(1, $base['dependents']['level_2']);

        $top = $graph['manifests']['app/Top.php'];
        self::assertSame(['app/Left.php', 'app/Right.php'], array_column($top['dependencies'], 'source_file'));
        self::assertSame('tests/mocks/mirror/MockLeft.php', $top['dependencies'][0]['mock_file']);
        self::assertSame('MockLeft', $top['dependencies'][0]['mock_class']);
    }

    public function test_cycles_do_not_revisit_origin_or_run_forever(): void
    {
        $graph = $this->graph();
        $a = $graph['manifests']['app/CycleA.php'];

        self::assertSame(['app/CycleB.php'], array_column($a['dependencies'], 'source_file'));
        self::assertSame(['app/CycleB.php'], array_column($a['dependents']['level_1'], 'source_file'));
        self::assertArrayNotHasKey('level_2', $a['dependents']);
        self::assertSame(['app/CycleA.php'], array_column($graph['manifests']['app/CycleB.php']['dependents']['level_1'], 'source_file'));
    }

    public function test_isolated_node_has_no_dependencies_or_dependents(): void
    {
        $manifest = $this->graph()['manifests']['app/Isolated.php'];
        self::assertSame([], $manifest['dependencies']);
        self::assertSame([], $manifest['dependents']);
        self::assertSame([], $manifest['external_dependencies']);
    }

    public function test_external_or_unresolved_class_is_explicit_and_has_no_fabricated_source(): void
    {
        $manifest = $this->graph()['manifests']['app/ExternalConsumer.php'];

        self::assertSame([], $manifest['dependencies']);
        self::assertSame([[
            'class_name' => 'Vendor\MissingAdapter',
            'source_file' => null,
            'resolution' => 'external_or_unresolved',
        ]], $manifest['external_dependencies']);
    }

    public function test_traits_and_enums_propagate_impact_without_standalone_tests(): void
    {
        $graph = $this->graph();
        foreach ([
            'app/HelperTrait.php' => 'app/UsesTrait.php',
            'app/Status.php' => 'app/EnumUser.php',
        ] as $dependency => $dependent) {
            $manifest = $graph['manifests'][$dependency];
            self::assertNull($manifest['mirrors']['unit_test']);
            self::assertNull($manifest['mirrors']['mock_file']);
            self::assertSame([$dependent], array_column($manifest['dependents']['level_1'], 'source_file'));
        }
    }

    public function test_anonymous_class_inherits_file_identity_without_fake_class_name(): void
    {
        $graph = $this->graph();
        $manifest = $graph['manifests']['app/AnonymousFactory.php'];

        self::assertNull($manifest['class_name']);
        self::assertSame('file:app/AnonymousFactory.php', $manifest['symbol_id']);
        self::assertSame('anonymous', $manifest['kind']);
        self::assertSame('tests/unit/mirror/AnonymousFactoryTest.php', $manifest['mirrors']['unit_test']);
    }

    public function test_all_source_files_contribute_to_fingerprint_but_helpers_have_no_manifest(): void
    {
        $graph = $this->graph();
        self::assertSame(16, $graph['summary']['source_files']);
        self::assertSame(15, $graph['summary']['indexed_files']);
        self::assertArrayNotHasKey('app/Helpers.php', $graph['manifests']);
        self::assertSame(64, strlen($graph['fingerprint']));
        self::assertSame($graph, $this->graph());
        self::assertSame($graph['fingerprint'], $graph['manifests']['app/Base.php']['index_fingerprint']);
    }

    public function test_dynamic_class_references_are_explicitly_not_assumed_to_be_internal(): void
    {
        $manifest = $this->graph()['manifests']['app/DynamicConsumer.php'];
        self::assertSame([], $manifest['dependencies']);
        self::assertSame('dynamic_new', $manifest['dynamic_references'][0]['kind']);
    }

    public function test_exact_target_class_and_relative_file_resolve_to_same_source(): void
    {
        $builder = new DependencyGraph();
        $graph = $this->graph();

        self::assertSame('app/Base.php', $builder->resolveTarget($graph, 'App\Base'));
        self::assertSame('app/Base.php', $builder->resolveTarget($graph, 'app/Base.php'));
        self::assertSame('app/Base.php', $builder->resolveTarget($graph, 'Base.php'));
    }

    public function test_unknown_target_cannot_silently_succeed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DependencyGraph())->resolveTarget($this->graph(), 'NoSuchClass');
    }

    public function test_invalid_structure_cannot_produce_a_graph(): void
    {
        $root = dirname(__DIR__, 4).'/tests/fixtures/analysis-project';
        $snapshot = (new ProjectInspector($root))->inspect('invalid');

        $this->expectException(InvalidArgumentException::class);
        (new DependencyGraph())->build($snapshot);
    }
}
