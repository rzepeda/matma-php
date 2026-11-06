<?php

declare(strict_types=1);

namespace Matma\Php\Impact;

use RuntimeException;

final class ImpactException extends RuntimeException
{
    public function __construct(
        public readonly string $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}
