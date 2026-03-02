<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs;

final readonly class NfseResponse
{
    public function __construct(
        public bool $sucesso,
        public ?string $chave,
        public ?string $xml,
        public ?string $erro,
    ) {}
}
