<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Events;

final readonly class NfseSubstituted
{
    public function __construct(
        public string $chave,
        public string $chaveSubstituta,
    ) {}
}
