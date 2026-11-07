<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Unit\Console;

use Matma\Php\Console\MatmaOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class MatmaOutputSnapshotTest extends TestCase
{
    public function test_all_four_command_text_outputs_match_committed_snapshot(): void
    {
        $payloads = [
            [
                'command' => 'matma:index',
                'status' => 'indexed',
                'message' => 'Indexed graph.',
            ],
            [
                'command' => 'matma:scaffold',
                'status' => 'scaffolded',
                'message' => 'Missing mirrors created.',
            ],
            [
                'command' => 'matma:validate',
                'status' => 'ok',
                'message' => 'Structural audit passed.',
            ],
            [
                'command' => 'matma:impact',
                'status' => 'selected',
                'message' => 'Selected impacted tests; no tests executed.',
                'affected_sources' => [
                    ['depth' => 0, 'source_file' => 'app/Services/Base.php', 'class_name' => 'App\Services\Base'],
                    ['depth' => 1, 'source_file' => 'app/Services/Consumer.php', 'class_name' => 'App\Services\Consumer'],
                ],
                'tests' => [
                    'unit' => [
                        'tests/unit/mirror/Services/BaseTest.php',
                        'tests/unit/mirror/Services/ConsumerTest.php',
                    ],
                    'interaction' => [
                        'tests/interaction/mirror/Services/BaseInteractionTest.php',
                    ],
                ],
                'missing_tests' => ['tests/unit/mirror/Services/ConsumerTest.php'],
                'registered_workflows' => [
                    'business' => [
                        ['id' => 'document-flow', 'tests' => ['tests/business/DocumentWorkflowTest.php']],
                    ],
                    'regression' => [],
                ],
            ],
        ];

        $actual = implode("\n---\n", array_map(
            static fn (array $payload): string => MatmaOutput::render('text', $payload),
            $payloads,
        ));
        $snapshot = dirname(__DIR__, 4).'/tests/fixtures/snapshots/four_commands.txt';
        self::assertSame(
            rtrim(str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($snapshot)), "\n"),
            str_replace(["\r\n", "\r"], "\n", $actual),
        );
    }

    public function test_machine_readable_json_and_yaml_preserve_tier_isolation(): void
    {
        $payload = [
            'command' => 'matma:impact',
            'status' => 'selected',
            'exit_code' => 0,
            'level' => 1,
            'tests' => [
                'unit' => ['tests/unit/mirror/BaseTest.php'],
                'interaction' => ['tests/interaction/mirror/BaseInteractionTest.php'],
            ],
            'registered_workflows' => [
                'business' => [['id' => 'workflow', 'tests' => ['tests/business/WorkflowTest.php']]],
                'regression' => [],
            ],
        ];

        $json = json_decode(MatmaOutput::render('json', $payload), true, flags: JSON_THROW_ON_ERROR);
        $yaml = Yaml::parse(MatmaOutput::render('yaml', $payload));
        self::assertSame($payload, $json);
        self::assertSame($payload, $yaml);
        self::assertNotContains('tests/business/WorkflowTest.php', $json['tests']['unit']);
        self::assertNotContains('tests/business/WorkflowTest.php', $json['tests']['interaction']);
    }
}
