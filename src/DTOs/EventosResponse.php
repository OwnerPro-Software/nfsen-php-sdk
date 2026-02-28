<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs;

readonly class EventosResponse
{
    /** @param array<int, array<string, mixed>> $eventos */
    public function __construct(
        public bool $sucesso,
        public array $eventos,
        public ?string $erro,
    ) {}
}
