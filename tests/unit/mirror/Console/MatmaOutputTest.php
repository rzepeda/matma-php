<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Console;

use InvalidArgumentException;
use Matma\Php\Console\MatmaOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class MatmaOutputTest extends TestCase
{
    private const PAYLOAD = [
        'command' => 'matma:index',
        'status' => 'not_implemented',
        'message' => 'Command shell only.',
        'exit_code' => 2,
    ];

    public function test_json_output_is_valid_machine_readable_data(): void
    {
        $decoded = json_decode(MatmaOutput::render('json', self::PAYLOAD), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(self::PAYLOAD, $decoded);
    }

    public function test_yaml_output_preserves_the_payload(): void
    {
        self::assertSame(self::PAYLOAD, Yaml::parse(MatmaOutput::render('yaml', self::PAYLOAD)));
    }

    public function test_text_output_is_actionable(): void
    {
        self::assertSame(
            'matma:index [not_implemented] Command shell only.',
            MatmaOutput::render('text', self::PAYLOAD),
        );
    }

    public function test_unsupported_output_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MatmaOutput::render('xml', self::PAYLOAD);
    }
}
