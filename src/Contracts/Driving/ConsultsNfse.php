<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Enums\SituacaoCancelamento;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\EventoConsultado;
use OwnerPro\Nfsen\Responses\EventsResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * @api
 */
interface ConsultsNfse
{
    public function nfse(string $chave): NfseResponse;

    /**
     * Quando a SEFIN responde 404 (DPS inexistente), retorna falha com
     * `erros[0]->codigo === NfseResponse::DPS_NOT_FOUND` — sinal inequívoco,
     * distinto de erros transitórios. Falha de comunicação lança
     * `CommunicationException` (`IndeterminateResultException` ou
     * `RequestNotDeliveredException`).
     */
    public function dps(string $id): NfseResponse;

    public function danfse(string $chave): DanfseResponse;

    /**
     * Quando a SEFIN responde 404 (evento inexistente), retorna falha com
     * `erros[0]->codigo === EventsResponse::EVENT_NOT_FOUND` — sinal inequívoco
     * de ausência, distinto de erros transitórios (que permanecem
     * `sucesso: false` sem esse código, portanto inconclusivos).
     *
     * No sucesso, `eventos` traz um {@see EventoConsultado} por item devolvido e
     * `xml` repete o XML do primeiro deles. Duas formas de resposta são aceitas: a
     * lista `eventos[]` com o XML em `arquivoXml`, que é o que o SefinNacional 1.6.0
     * devolve, e o `eventoXmlGZipB64` de topo, que é o que o swagger declara e o que
     * prefeituras de implementação própria podem devolver.
     *
     * Um 2xx sem nenhuma das duas formas lança `IndeterminateResultException` —
     * nunca vira sucesso silencioso. Já um evento cujo XML não pôde ser lido mantém
     * `sucesso: true` com `xml: null` e o motivo em `eventos[n]->parseError`: o
     * servidor respondeu, e dizer o que faltou vale mais do que descartar a lista.
     */
    public function eventos(string $chave, TipoEvento|int $tipoEvento = TipoEvento::Cancelamento, int $nSequencial = 1): EventsResponse;

    /**
     * Em que ponto está o cancelamento por análise fiscal, deduzido dos eventos
     * vinculados à chave (uma chamada ao ADN). Nota sem pedido algum devolve
     * `SituacaoCancelamento::SemPedido`; consulta rejeitada pelo ADN lança
     * `NfseException`, porque rejeição não é situação da nota.
     */
    public function situacaoCancelamento(string $chave): SituacaoCancelamento;

    /**
     * Retorna `true` para HTTP 200 e `false` APENAS para HTTP 404.
     * Qualquer outro status (401, 403, 429, redirect, 5xx…) lança
     * `HttpException`; falha de comunicação lança `CommunicationException` —
     * em `IndeterminateResultException` a existência da DPS é indeterminada
     * e NÃO é seguro re-emitir.
     */
    public function verificarDps(string $id): bool;
}
