<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Events;

final readonly class NfseRejected
{
    public function __construct(
        public string $operacao,
        public string $codigoErro,
    ) {}
}
