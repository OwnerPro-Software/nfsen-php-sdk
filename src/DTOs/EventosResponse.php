<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs;

final readonly class EventosResponse
{
    /** @param list<MensagemProcessamento> $erros */
    public function __construct(
        public bool $sucesso,
        public ?string $xml = null,
        public array $erros = [],
        public ?int $tipoAmbiente = null,
        public ?string $versaoAplicativo = null,
        public ?string $dataHoraProcessamento = null,
    ) {}
}
