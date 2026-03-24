<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

final readonly class NfseResponse
{
    /**
     * @param  list<ProcessingMessage>  $alertas
     * @param  list<ProcessingMessage>  $erros
     */
    public function __construct(
        public bool $sucesso,
        public ?string $chave = null,
        public ?string $xml = null,
        public ?string $idDps = null,
        public array $alertas = [],
        public array $erros = [],
        public ?int $tipoAmbiente = null,
        public ?string $versaoAplicativo = null,
        public ?string $dataHoraProcessamento = null,
    ) {}
}
