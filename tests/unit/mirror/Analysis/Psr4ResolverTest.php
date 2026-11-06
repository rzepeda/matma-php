<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Analysis;

use Matma\Php\Analysis\Psr4Resolver;
use PHPUnit\Framework\TestCase;

class Psr4ResolverTest extends TestCase
{
    public function test_composer_namespace_is_resolved_from_matching_directory(): void
    {
        $root = dirname(__DIR__, 4).'/tests/fixtures/analysis-project';
        $resolver = new Psr4Resolver($root);

        self::assertSame(
            'App\Services\DocumentParser',
            $resolver->expectedName($root.'/app/Services/DocumentParser.php'),
        );
        self::assertSame(
            'Bad\WrongNamespace',
            $resolver->expectedName($root.'/invalid/WrongNamespace.php'),
        );
    }

    public function test_file_outside_all_psr4_mappings_is_not_guessed(): void
    {
        $root = dirname(__DIR__, 4).'/tests/fixtures/analysis-project';
        self::assertNull((new Psr4Resolver($root))->expectedName($root.'/other/File.php'));
    }
}
