<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Exceptions;

/**
 * Base das falhas de comunicação: nenhuma resposta completa e legível foi
 * obtida do servidor.
 *
 * Capture esta base para tratar toda falha de comunicação como indeterminada
 * (sempre seguro — no pior caso reconcilia sem necessidade). Capture as
 * subclasses para distinguir:
 *
 * - {@see RequestNotDeliveredException} — a requisição comprovadamente NÃO
 *   chegou ao servidor; retry direto é seguro.
 * - {@see IndeterminateResultException} — a requisição PODE ter sido
 *   processada; reconcilie antes de qualquer retry.
 *
 * @api
 */
abstract class CommunicationException extends NfseException {}
