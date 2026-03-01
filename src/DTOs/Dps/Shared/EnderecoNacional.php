<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Shared;

final readonly class EnderecoNacional
{
    public function __construct(
        public string $cMun,
        public string $CEP,
    ) {}
}
