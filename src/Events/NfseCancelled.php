<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Events;

final readonly class NfseCancelled
{
    public function __construct(
        public string $chave,
    ) {}
}
