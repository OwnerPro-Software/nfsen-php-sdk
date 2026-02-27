<?php

namespace Pulsar\NfseNacional\Events;

class NfseQueried
{
    public function __construct(
        public readonly string $operacao,
    ) {}
}
