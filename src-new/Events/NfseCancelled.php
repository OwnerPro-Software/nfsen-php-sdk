<?php

namespace Pulsar\NfseNacional\Events;

class NfseCancelled
{
    public function __construct(
        public readonly string $chave,
    ) {}
}
