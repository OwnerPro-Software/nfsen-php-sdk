<?php

namespace Pulsar\NfseNacional\DTOs;

readonly class DanfseResponse
{
    public function __construct(
        public bool    $sucesso,
        public ?string $url,
        public ?string $erro,
    ) {}
}
