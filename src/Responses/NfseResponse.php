<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

/**
 * @api
 */
final readonly class NfseResponse
{
    /**
     * Código presente em `erros[0]->codigo` quando `consultar()->dps($id)`
     * recebe HTTP 404 da SEFIN: a DPS comprovadamente não existe (distinto
     * de erro transitório — seguro re-emitir com o mesmo nDPS).
     */
    public const string DPS_NOT_FOUND = 'DPS_NOT_FOUND';

    /**
     * @param  list<ProcessingMessage>  $alertas
     * @param  list<ProcessingMessage>  $erros
     * @param  list<ProcessingMessage>  $pdfErrors
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
        public ?string $pdf = null,
        public array $pdfErrors = [],
    ) {}
}
