<?php

namespace Pulsar\NfseNacional\DTOs;

readonly class NfseResponse
{
    public function __construct(
        public bool    $sucesso,
        public ?string $chave,
        public ?string $xml,
        public ?string $erro,
    ) {}
}
