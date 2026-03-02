<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs;

final readonly class DanfseResponse
{
    /** @param list<MensagemProcessamento> $erros */
    public function __construct(
        public bool $sucesso,
        public ?string $url = null,
        public array $erros = [],
    ) {}
}
