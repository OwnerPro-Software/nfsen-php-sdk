<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Support\GzipCompressor;

/**
 * Um documento de um lote de distribuição.
 *
 * Nenhum campo de `DistribuicaoNSU` é obrigatório no contrato do ADN, e o governo
 * pode passar a emitir tipos que esta versão do SDK ainda não conhece. Por isso um
 * item que não pôde ser interpretado por completo **não** interrompe o lote: os
 * campos afetados vêm `null` e {@see self::$parseError} descreve o que faltou.
 * O `nsu` é preservado em qualquer cenário, para que o chamador consiga refazer a
 * busca daquele documento em específico.
 *
 * @api
 */
final readonly class DocumentoFiscal
{
    public function __construct(
        public ?int $nsu,
        public ?string $chaveAcesso,
        public ?TipoDocumentoFiscal $tipoDocumento,
        public ?TipoEventoDistribuicao $tipoEvento,
        public ?string $arquivoXml,
        public ?string $dataHoraGeracao,
        /** Por que o documento não pôde ser interpretado por completo; `null` quando íntegro. */
        public ?string $parseError = null,
    ) {}

    /**
     * @param array{
     *     NSU?: int|null,
     *     ChaveAcesso?: string|null,
     *     TipoDocumento?: string|null,
     *     TipoEvento?: string|null,
     *     ArquivoXml?: string|null,
     *     DataHoraGeracao?: string|null,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $problemas = [];

        $tipoDocumentoRaw = $data['TipoDocumento'] ?? null;
        $tipoDocumento = $tipoDocumentoRaw !== null ? TipoDocumentoFiscal::tryFrom($tipoDocumentoRaw) : null;

        if ($tipoDocumentoRaw === null) {
            $problemas[] = 'Campo TipoDocumento ausente na resposta do ADN.';
        } elseif ($tipoDocumento === null) {
            $problemas[] = sprintf('TipoDocumento desconhecido: "%s".', $tipoDocumentoRaw);
        }

        $tipoEventoRaw = $data['TipoEvento'] ?? null;
        $tipoEvento = $tipoEventoRaw !== null ? TipoEventoDistribuicao::tryFrom($tipoEventoRaw) : null;

        if ($tipoEventoRaw !== null && $tipoEvento === null) {
            $problemas[] = sprintf('TipoEvento desconhecido: "%s".', $tipoEventoRaw);
        }

        try {
            $arquivoXml = GzipCompressor::decompressB64($data['ArquivoXml'] ?? null);
        } catch (NfseException $nfseException) {
            $arquivoXml = null;
            $problemas[] = $nfseException->getMessage();
        }

        return new self(
            nsu: $data['NSU'] ?? null,
            chaveAcesso: $data['ChaveAcesso'] ?? null,
            tipoDocumento: $tipoDocumento,
            tipoEvento: $tipoEvento,
            arquivoXml: $arquivoXml,
            dataHoraGeracao: $data['DataHoraGeracao'] ?? null,
            parseError: $problemas === [] ? null : implode(' ', $problemas),
        );
    }
}
