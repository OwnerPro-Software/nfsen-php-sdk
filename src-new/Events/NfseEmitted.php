<?php

namespace Pulsar\NfseNacional\Events;

class NfseEmitted
{
    public function __construct(
        public readonly string $chave,
    ) {}
}
