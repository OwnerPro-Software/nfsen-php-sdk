<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

use OwnerPro\Nfsen\Enums\NfseAmbiente;

/**
 * DTO "display-ready" para o template do DANFSE.
 *
 * Todos os campos são strings já formatadas (ou '-' para ausentes).
 * Sub-DTOs agrupam campos por bloco visual do PDF.
 *
 * @api
 */
final readonly class NfseData
{
    public function __construct(
        public string $chaveAcesso,
        public string $numeroNfse,
        public string $competencia,
        public string $emissaoNfse,
        public string $numeroDps,
        public string $serieDps,
        public string $emissaoDps,
        public NfseAmbiente $ambiente,
        public DanfseParte $emitente,
        public DanfseParte $tomador,
        public ?DanfseParte $intermediario,
        public DanfseServico $servico,
        public DanfseTributacaoMunicipal $tribMun,
        public DanfseTributacaoFederal $tribFed,
        public DanfseTotais $totais,
        public DanfseTotaisTributos $totaisTributos,
        public string $informacoesComplementares,
    ) {}
}
