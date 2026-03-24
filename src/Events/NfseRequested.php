<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Events;

final readonly class NfseRequested
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $operacao,
        public array $metadata = [],
    ) {}
}
