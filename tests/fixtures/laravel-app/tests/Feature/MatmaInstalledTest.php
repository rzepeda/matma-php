<?php

declare(strict_types=1);

namespace MatmaFixture\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class MatmaInstalledTest extends TestCase
{
    private const ARTISAN_COMMANDS = [
        'matma:index', 'matma:scaffold', 'matma:impact', 'matma:validate',
    ];

    public function test_all_four_commands_are_available_via_artisan(): void
    {
        $result = $this->artisanProcess('list', '--raw');
        self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());
        foreach (self::ARTISAN_COMMANDS as $command) {
            self::assertStringContainsString($command, $result->getOutput());
        }
    }

    public function test_consumer_can_generate_a_real_yaml_manifest_via_artisan(): void
    {
        $temporaryRoot = sys_get_temp_dir().'/matma-consumer-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($temporaryRoot, 0775, true));

        try {
            $result = $this->artisanProcess(
                'matma:index',
                '--format=json',
                '--target=App\Services\SmokeService',
                '--tests-root='.$temporaryRoot,
            );

            self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());
            $payload = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('indexed', $payload['status']);
            self::assertSame(2, $payload['summary']['indexed_files']);
            $file = $temporaryRoot.'/mirror/Services/SmokeService.auto.yaml';
            self::assertFileExists($file);
            $parsed = Yaml::parse((string) file_get_contents($file));
            self::assertSame('App\Services\SmokeService', $parsed['class_name']);
            self::assertSame('app/Services/SmokeService.php', $parsed['source_file']);
        } finally {
            $entries = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($temporaryRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($entries as $entry) {
                $entry->isDir() && ! $entry->isLink()
                    ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($temporaryRoot);
        }
    }

    public function test_scaffold_installs_failing_test_templates_and_php_artisan_test_can_run_them(): void
    {
        $temporaryRoot = sys_get_temp_dir().'/matma-scaffold-consumer-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($temporaryRoot, 0775, true));

        try {
            $result = $this->artisanProcess(
                'matma:scaffold',
                '--format=json',
                '--target=App\Services\SmokeService',
                '--tests-root='.$temporaryRoot,
            );

            self::assertSame(0, $result->getExitCode(), $result->getOutput().$result->getErrorOutput());
            $payload = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('scaffolded', $payload['status']);
            self::assertCount(3, $payload['scaffolds']['created']);

            $test = $temporaryRoot.'/unit/mirror/Services/SmokeServiceTest.php';
            self::assertFileExists($test);
            self::assertFileExists($temporaryRoot.'/interaction/mirror/Services/SmokeServiceInteractionTest.php');
            self::assertFileExists($temporaryRoot.'/mocks/mirror/Services/MockSmokeService.php');
            self::assertFileExists($temporaryRoot.'/mirror/Services/SmokeService.auto.yaml');

            $run = $this->artisanProcess('test', $test);
            self::assertNotSame(0, $run->getExitCode());
            self::assertStringContainsString(
                'MATMA: implement unit assertions',
                $run->getOutput().$run->getErrorOutput(),
            );
        } finally {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($temporaryRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($temporaryRoot);
        }
    }

    public function test_impact_resolves_consumer_psr4_class_from_persisted_manifest(): void
    {
        $root = sys_get_temp_dir().'/matma-impact-consumer-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root, 0775, true));

        try {
            $indexed = $this->artisanProcess(
                'matma:index',
                '--tests-root='.$root,
                '--format=json',
            );
            self::assertSame(0, $indexed->getExitCode(), $indexed->getOutput().$indexed->getErrorOutput());
            self::assertFileExists($root.'/mirror/.index.auto.yaml');

            $impact = $this->artisanProcess(
                'matma:impact',
                '--target=App\Services\SmokeService',
                '--tests-root='.$root,
                '--level=1',
                '--format=json',
            );

            self::assertSame(0, $impact->getExitCode(), $impact->getOutput().$impact->getErrorOutput());
            $selection = json_decode($impact->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('selected', $selection['status']);
            self::assertSame('App\Services\SmokeService', $selection['target']['class_name']);
            self::assertSame(2, $selection['summary']['affected_sources']);
            self::assertCount(2, $selection['tests']['unit']);
            self::assertCount(2, $selection['tests']['interaction']);
            self::assertSame([], $selection['registered_workflows']['business']);
            self::assertSame([], $selection['registered_workflows']['regression']);
        } finally {
            $entries = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($entries as $entry) {
                $entry->isDir() && ! $entry->isLink()
                    ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($root);
        }
    }

    public function test_end_to_end_index_scaffold_validate_impact_preserves_developer_tests(): void
    {
        $root = sys_get_temp_dir().'/matma-walkthrough-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root, 0775, true));

        try {
            $indexed = $this->artisanProcess('matma:index', '--tests-root='.$root, '--format=json');
            self::assertSame(0, $indexed->getExitCode(), $indexed->getOutput().$indexed->getErrorOutput());
            $index = json_decode($indexed->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('indexed', $index['status']);
            self::assertSame(2, $index['summary']['indexed_files']);
            self::assertSame(1, $index['summary']['internal_edges']);

            $scaffold = $this->artisanProcess(
                'matma:scaffold',
                '--target=App\Services\SmokeConsumer',
                '--tests-root='.$root,
                '--format=json',
            );
            self::assertSame(0, $scaffold->getExitCode(), $scaffold->getOutput().$scaffold->getErrorOutput());
            $generated = json_decode($scaffold->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('scaffolded', $generated['status']);
            self::assertCount(4, $generated['scaffolds']['created']);
            self::assertSame([], $generated['manifests']['written']);

            $validated = $this->artisanProcess(
                'matma:validate',
                '--target=App\Services\SmokeConsumer',
                '--check',
                '--format=json',
            );
            self::assertSame(0, $validated->getExitCode(), $validated->getOutput().$validated->getErrorOutput());
            $assessment = json_decode($validated->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('ok', $assessment['status']);

            $impact = $this->artisanProcess(
                'matma:impact',
                '--target=App\Services\SmokeService',
                '--tests-root='.$root,
                '--level=1',
                '--format=json',
            );
            self::assertSame(0, $impact->getExitCode(), $impact->getOutput().$impact->getErrorOutput());
            $selected = json_decode($impact->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('selected', $selected['status']);
            self::assertSame([
                'app/Services/SmokeService.php',
                'app/Services/SmokeConsumer.php',
            ], array_column($selected['affected_sources'], 'source_file'));
            self::assertSame([0, 1], array_column($selected['affected_sources'], 'depth'));
            self::assertCount(2, $selected['tests']['unit']);
            self::assertCount(2, $selected['tests']['interaction']);
            self::assertCount(2, $selected['missing_tests']);

            $testFile = $root.'/unit/mirror/Services/SmokeConsumerTest.php';
            self::assertFileExists($testFile);
            $failing = $this->artisanProcess('test', $testFile);
            self::assertNotSame(0, $failing->getExitCode());
            self::assertStringContainsString(
                'MATMA: implement unit assertions',
                $failing->getOutput().$failing->getErrorOutput(),
            );

            $developerContent = "<?php // developer-authored replacement\n";
            file_put_contents($testFile, $developerContent);
            $repeated = $this->artisanProcess(
                'matma:scaffold',
                '--target=App\Services\SmokeConsumer',
                '--tests-root='.$root,
                '--format=json',
            );
            self::assertSame(0, $repeated->getExitCode(), $repeated->getOutput().$repeated->getErrorOutput());
            $repeat = json_decode($repeated->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame([], $repeat['scaffolds']['created']);
            self::assertSame($developerContent, file_get_contents($testFile));
        } finally {
            $entries = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($entries as $entry) {
                $entry->isDir() && ! $entry->isLink()
                    ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($root);
        }
    }

    public function test_bad_options_fail_with_an_actionable_status(): void
    {
        $result = $this->artisanProcess('matma:validate', '--level=-1', '--format=json');
        self::assertSame(64, $result->getExitCode(), $result->getErrorOutput());
        $payload = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('invalid_options', $payload['status']);
    }

    private function artisanProcess(string ...$args): Process
    {
        $projectRoot = dirname(__DIR__, 2);
        $process = new Process([PHP_BINARY, 'artisan', ...$args], $projectRoot);
        $process->run();

        return $process;
    }
}
