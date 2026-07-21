<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

use OwnerPro\Nfsen\Enums\MarcaDagua;
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
        /** Descrição de `infNFSe/cStat` — campo "SITUAÇÃO DA NFS-E" da NT 008. */
        public string $situacao,
        /** Descrição de `infDPS/IBSCBS/finNFSe` — campo "FINALIDADE" da NT 008. */
        public string $finalidade,
        /** Descrição de `infDPS/tpEmit` — campo "EMITENTE DA NFS-e" da NT 008. */
        public string $emitidaPor,
        /** Descrição de `infNFSe/ambGer` — campo "AMBIENTE GERADOR" da NT 008. */
        public string $ambienteGerador,
        /**
         * `infNFSe/xLocEmi` + a UF do emitente — campo "MUNICÍPIO" do quadro de
         * identificação (NT 008, item 2.4.5).
         *
         * String vazia quando a própria NT manda não exibir: item 99 do código de
         * tributação nacional. Distinto de `'-'`, que é campo sem dado (nota 12).
         */
        public string $municipioEmitente,
        public DanfseParticipante $emitente,
        public DanfseParticipante $tomador,
        public ?DanfseParticipante $intermediario,
        /** Bloco "DESTINATÁRIO DA OPERAÇÃO" (`infDPS/IBSCBS/dest`); null quando ausente. */
        public ?DanfseParticipante $destinatario,
        /**
         * `indDest = 0`: o destinatário é o próprio tomador/adquirente.
         *
         * A NT 008 dá a este caso uma frase própria (item 2.4.5, nota 3), diferente
         * da usada quando não há dados de destinatário (nota 2).
         */
        public bool $destinatarioEhTomador,
        public DanfseServico $servico,
        public DanfseTributacaoMunicipal $tribMun,
        public DanfseTributacaoFederal $tribFed,
        public DanfseTributacaoIbsCbs $tribIbsCbs,
        public DanfseTotais $totais,
        public DanfseTotaisTributos $totaisTributos,
        public string $informacoesComplementares,
        /**
         * Marca d'água diagonal "CANCELADA"/"SUBSTITUÍDA" (NT 008, itens 2.5.1 e 2.5.2).
         *
         * `null` para a NFS-e vigente — nenhuma marca é impressa.
         */
        public ?MarcaDagua $marcaDagua = null,
    ) {}
}
