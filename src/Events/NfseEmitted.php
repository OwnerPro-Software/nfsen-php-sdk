<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Events;

class NfseEmitted
{
    public function __construct(
        public readonly string $chave,
    ) {}
}
