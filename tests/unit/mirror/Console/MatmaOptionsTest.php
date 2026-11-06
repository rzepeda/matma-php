<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Console;

use InvalidArgumentException;
use Matma\Php\Console\MatmaOptions;
use PHPUnit\Framework\TestCase;

class MatmaOptionsTest extends TestCase
{
    public function test_default_options_match_the_php_blueprint(): void
    {
        $options = MatmaOptions::fromArray([]);

        self::assertSame('app/', $options->sourceRoot);
        self::assertSame('tests/', $options->testsRoot);
        self::assertSame(0, $options->level);
        self::assertNull($options->target);
        self::assertFalse($options->scaffold);
        self::assertFalse($options->check);
        self::assertSame('text', $options->format);
    }

    public function test_custom_options_preserve_all_fields(): void
    {
        $options = MatmaOptions::fromArray([
            'source-root' => 'app/Domain',
            'tests-root' => 'custom/tests',
            'scaffold' => true,
            'level' => '2',
            'target' => 'App\\Domain\\Parser',
            'check' => true,
            'format' => 'json',
        ]);

        self::assertSame([
            'source_root' => 'app/Domain',
            'tests_root' => 'custom/tests',
            'scaffold' => true,
            'level' => 2,
            'target' => 'App\\Domain\\Parser',
            'check' => true,
            'format' => 'json',
        ], $options->toArray());
    }

    public function test_negative_level_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--level');
        MatmaOptions::fromArray(['level' => '-1']);
    }

    public function test_non_integer_level_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MatmaOptions::fromArray(['level' => 'two']);
    }

    public function test_unsupported_output_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--format');
        MatmaOptions::fromArray(['format' => 'xml']);
    }

    public function test_empty_source_root_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--source-root');
        MatmaOptions::fromArray(['source-root' => ' ']);
    }

    public function test_scaffold_flag_must_be_boolean(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MatmaOptions::fromArray(['scaffold' => 'true']);
    }
}
