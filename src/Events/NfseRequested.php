<?php

namespace Pulsar\NfseNacional\Events;

class NfseRequested
{
    public function __construct(
        public readonly string $operacao,
        public readonly array  $metadata = [],
    ) {}
}
