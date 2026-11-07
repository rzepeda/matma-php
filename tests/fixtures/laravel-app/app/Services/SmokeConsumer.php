<?php

declare(strict_types=1);

namespace App\Services;

class SmokeConsumer
{
    public function __construct(private readonly SmokeService $service)
    {
    }

    public function run(): bool
    {
        return $this->service->ping();
    }
}
