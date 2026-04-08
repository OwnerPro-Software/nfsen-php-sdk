<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;
use OwnerPro\Nfsen\Support\GzipCompressor;

final readonly class DocumentoFiscal
{
    public function __construct(
        public ?int $nsu,
        public ?string $chaveAcesso,
        public TipoDocumentoFiscal $tipoDocumento,
        public ?TipoEventoDistribuicao $tipoEvento,
        public ?string $arquivoXml,
        public ?string $dataHoraGeracao,
    ) {}

    /**
     * @param array{
     *     NSU?: int|null,
     *     ChaveAcesso?: string|null,
     *     TipoDocumento: string,
     *     TipoEvento?: string|null,
     *     ArquivoXml?: string|null,
     *     DataHoraGeracao?: string|null,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nsu: $data['NSU'] ?? null,
            chaveAcesso: $data['ChaveAcesso'] ?? null,
            tipoDocumento: TipoDocumentoFiscal::from($data['TipoDocumento']),
            tipoEvento: isset($data['TipoEvento']) ? TipoEventoDistribuicao::from($data['TipoEvento']) : null,
            arquivoXml: GzipCompressor::decompressB64($data['ArquivoXml'] ?? null),
            dataHoraGeracao: $data['DataHoraGeracao'] ?? null,
        );
    }
}
