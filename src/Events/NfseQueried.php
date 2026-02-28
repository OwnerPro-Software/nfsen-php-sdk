<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Events;

final readonly class NfseQueried
{
    public function __construct(
        public string $operacao,
    ) {}
}
