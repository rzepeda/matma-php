<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Interaction\Analysis;

use Matma\Php\Analysis\ProjectInspector;
use Matma\Php\Tests\TestCase;

class ProjectInspectorInteractionTest extends TestCase
{
    public function test_anonymous_classes_share_their_containing_source_file(): void
    {
        $fixtureRoot = dirname(__DIR__, 4).'/tests/fixtures/analysis-project';
        $result = (new ProjectInspector($fixtureRoot))->inspect('app', 'custom-tests', 'Anonymous/Factory.php');

        self::assertSame(1, $result['summary']['source_files']);
        self::assertCount(2, $result['files'][0]['declarations']);
        self::assertSame('custom-tests/unit/mirror/Anonymous/FactoryTest.php', $result['files'][0]['mirrors']['unit_test']);
        self::assertSame('custom-tests/mirror/Anonymous/Factory.auto.yaml', $result['files'][0]['mirrors']['manifest']);
        self::assertSame(0, $result['summary']['errors']);
    }

    public function test_whole_project_symbol_index_can_be_recomputed_deterministically(): void
    {
        $fixtureRoot = dirname(__DIR__, 4).'/tests/fixtures/analysis-project';
        $inspector = new ProjectInspector($fixtureRoot);

        $first = $inspector->inspect('app');
        $second = $inspector->inspect('app');

        self::assertSame($first, $second);
    }
}
