<?php

namespace App;

class Consumer
{
    public function __construct(
        private Contract $contract,
        private FinalService $finalService,
        private ReadonlyService $readonlyService,
    ) {
    }
}
