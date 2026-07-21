<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Exceptions;

use Throwable;

/**
 * A requisição comprovadamente NUNCA foi entregue ao servidor: a falha
 * ocorreu na resolução DNS, na conexão TCP ou no handshake TLS — antes de
 * qualquer byte HTTP ser enviado. A SEFIN não recebeu nem processou nada;
 * o reenvio direto é seguro e nenhuma reconciliação é necessária.
 *
 * Só é lançada quando o cliente foi construído com `detectNotDelivered: true`
 * e há evidência inequívoca (errno do cURL: 6, 7, 35, 58 ou 60). Qualquer
 * ambiguidade — incluindo timeouts (cURL 28) — classifica como
 * {@see IndeterminateResultException}: os custos são assimétricos, e um falso
 * "não entregue" convidaria a um retry cego com risco de dupla emissão.
 *
 * @api
 */
final class RequestNotDeliveredException extends CommunicationException
{
    /**
     * @param  'connect'|'dns'|'tls'  $phase  fase em que a falha ocorreu
     */
    public function __construct(
        public readonly string $phase,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('A requisição não foi entregue ao servidor (falha de %s) — a operação não foi processada e o reenvio direto é seguro.', $phase)
            .($previous instanceof Throwable ? ' '.$previous->getMessage() : ''),
            0,
            $previous,
        );
    }
}
