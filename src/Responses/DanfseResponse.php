<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Responses;

final readonly class DanfseResponse
{
    /** @param list<MensagemProcessamento> $erros */
    public function __construct(
        public bool $sucesso,
        public ?string $url = null,
        public array $erros = [],
        public ?int $tipoAmbiente = null,
        public ?string $versaoAplicativo = null,
        public ?string $dataHoraProcessamento = null,
    ) {}
}
