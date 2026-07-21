<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Responses\DanfseResponse;
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
     * Um 2xx com JSON válido porém sem `eventoXmlGZipB64` não ocorre em
     * operação normal e lança `IndeterminateResultException` — nunca vira
     * sucesso com `xml: null`.
     */
    public function eventos(string $chave, TipoEvento|int $tipoEvento = TipoEvento::Cancelamento, int $nSequencial = 1): EventsResponse;

    /**
     * Retorna `true` para HTTP 200 e `false` APENAS para HTTP 404.
     * Qualquer outro status (401, 403, 429, redirect, 5xx…) lança
     * `HttpException`; falha de comunicação lança `CommunicationException` —
     * em `IndeterminateResultException` a existência da DPS é indeterminada
     * e NÃO é seguro re-emitir.
     */
    public function verificarDps(string $id): bool;
}
