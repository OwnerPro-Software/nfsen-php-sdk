<?php

namespace Pulsar\NfseNacional\Events;

class NfseRejected
{
    public function __construct(
        public readonly string $operacao,
        public readonly string $codigoErro,
    ) {}
}
