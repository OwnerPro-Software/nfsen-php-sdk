<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

/**
 * @api
 */
final readonly class EventsResponse
{
    /** @param list<ProcessingMessage> $erros */
    public function __construct(
        public bool $sucesso,
        public ?string $xml = null,
        public array $erros = [],
        public ?int $tipoAmbiente = null,
        public ?string $versaoAplicativo = null,
        public ?string $dataHoraProcessamento = null,
    ) {}
}
