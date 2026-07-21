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
     * `CommunicationException` (`IndeterminateResultException`, ou
     * `RequestNotDeliveredException` com `detectNotDelivered` ativo).
     */
    public function dps(string $id): NfseResponse;

    public function danfse(string $chave): DanfseResponse;

    public function eventos(string $chave, TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador, int $nSequencial = 1): EventsResponse;

    /**
     * Retorna `true` para HTTP 200 e `false` APENAS para HTTP 404.
     * Qualquer outro status (401, 403, 429, redirect, 5xx…) lança
     * `HttpException`; falha de comunicação lança `CommunicationException` —
     * em `IndeterminateResultException` a existência da DPS é indeterminada
     * e NÃO é seguro re-emitir.
     */
    public function verificarDps(string $id): bool;
}
