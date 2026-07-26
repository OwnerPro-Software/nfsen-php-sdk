<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

/**
 * @api
 */
final readonly class EventsResponse
{
    /**
     * Código presente em `erros[0]->codigo` quando `consultar()->eventos()`
     * recebe HTTP 404 da SEFIN: o evento comprovadamente não existe (distinto
     * de erro transitório). Na reconciliação de cancelamento indeterminado é a
     * prova de que o cancelamento não registrou — seguro reenviar.
     */
    public const string EVENT_NOT_FOUND = 'EVENT_NOT_FOUND';

    /**
     * @param  list<ProcessingMessage>  $erros
     * @param  array<string, mixed>|null  $raw  corpo JSON decodificado, como a API o
     *                                          devolveu — o que a normalização não
     *                                          reconhecer continua legível aqui
     */
    public function __construct(
        public bool $sucesso,
        public ?string $xml = null,
        public array $erros = [],
        public ?int $tipoAmbiente = null,
        public ?string $versaoAplicativo = null,
        public ?string $dataHoraProcessamento = null,
        public ?array $raw = null,
    ) {}
}
