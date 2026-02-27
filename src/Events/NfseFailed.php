<?php

namespace Pulsar\NfseNacional\Events;

class NfseFailed
{
    public function __construct(
        public readonly string $operacao,
        public readonly string $message,
    ) {}
}
