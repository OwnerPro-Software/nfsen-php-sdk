<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Events;

final readonly class NfseFailed
{
    public function __construct(
        public string $operacao,
        public string $message,
    ) {}
}
