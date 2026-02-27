<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Events;

class NfseRequested
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $operacao,
        public readonly array  $metadata = [],
    ) {}
}
