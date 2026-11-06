<?php

namespace App\Anonymous;

function buildFactory(): object
{
    return new class {
        public function create(): object
        {
            return new class {};
        }
    };
}
