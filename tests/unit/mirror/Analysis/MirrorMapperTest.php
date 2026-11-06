<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Analysis;

use InvalidArgumentException;
use Matma\Php\Analysis\MirrorMapper;
use PHPUnit\Framework\TestCase;

class MirrorMapperTest extends TestCase
{
    public function test_laravel_pascal_case_is_preserved_in_every_mirror(): void
    {
        self::assertSame([
            'unit_test' => 'tests/unit/mirror/Services/DocumentParserTest.php',
            'interaction_test' => 'tests/interaction/mirror/Services/DocumentParserInteractionTest.php',
            'mock_file' => 'tests/mocks/mirror/Services/MockDocumentParser.php',
            'mock_class' => 'MockDocumentParser',
            'manifest' => 'tests/mirror/Services/DocumentParser.auto.yaml',
        ], (new MirrorMapper())->map('Services/DocumentParser.php', 'tests'));
    }

    public function test_nondefault_tests_root_and_windows_separators_are_supported(): void
    {
        $mirrors = (new MirrorMapper())->map('Domain\\Sub\\Thing.php', 'custom\\test');
        self::assertSame('custom/test/unit/mirror/Domain/Sub/ThingTest.php', $mirrors['unit_test']);
    }

    public function test_source_root_level_classes_have_no_extra_slash(): void
    {
        $mirrors = (new MirrorMapper())->map('Logger.php', 'tests/');
        self::assertSame('tests/mirror/Logger.auto.yaml', $mirrors['manifest']);
    }

    public function test_parent_directory_escape_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new MirrorMapper())->map('../Another.php', 'tests');
    }
}
