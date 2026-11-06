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
            self::assertSame(1, $payload['summary']['indexed_files']);
            $file = $temporaryRoot.'/mirror/Services/SmokeService.auto.yaml';
            self::assertFileExists($file);
            $parsed = Yaml::parse((string) file_get_contents($file));
            self::assertSame('App\Services\SmokeService', $parsed['class_name']);
            self::assertSame('app/Services/SmokeService.php', $parsed['source_file']);
        } finally {
            $folder = $temporaryRoot.'/mirror/Services';
            if (is_file($folder.'/SmokeService.auto.yaml')) {
                unlink($folder.'/SmokeService.auto.yaml');
            }
            if (is_dir($folder)) {
                rmdir($folder);
            }
            if (is_dir($temporaryRoot.'/mirror')) {
                rmdir($temporaryRoot.'/mirror');
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
            self::assertSame(1, $selection['summary']['affected_sources']);
            self::assertCount(1, $selection['tests']['unit']);
            self::assertCount(1, $selection['tests']['interaction']);
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
