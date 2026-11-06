<?php

namespace App\Services;

use App\Contracts\ParserContract as Contract;
use App\Domain\Tools\{Logger as Log, Formatter};
use App\Domain\BaseService;
use App\Domain\HasState;

class DocumentParser extends BaseService implements Contract
{
    use HasState;

    public function __construct(private Log $logger)
    {
    }

    public function parse(Formatter $formatter = new Formatter()): void
    {
        $value = new class implements Contract {
            public function parse(): void
            {
            }
        };

        $formatter::class;
    }
}
