<?php

namespace App;

readonly class ReadonlyService
{
    public function __construct(public string $name)
    {
    }
}
