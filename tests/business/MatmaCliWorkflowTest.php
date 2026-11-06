<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Business;

use Illuminate\Support\Facades\Artisan;
use Matma\Php\Tests\TestCase;

class MatmaCliWorkflowTest extends TestCase
{
    public function test_index_scaffold_and_validate_are_real_while_impact_remains_unimplemented(): void
    {
        foreach (['matma:impact'] as $name) {
            self::assertSame(2, Artisan::call($name, ['--format' => 'json']));
            $response = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('not_implemented', $response['status']);
        }

        $sourceRoot = dirname(__DIR__, 2).'/src';
        self::assertSame(0, Artisan::call('matma:validate', [
            '--source-root' => $sourceRoot,
            '--check' => true,
            '--format' => 'json',
        ]));
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ok', $payload['status']);
        self::assertGreaterThan(0, $payload['summary']['source_files']);
    }
}
