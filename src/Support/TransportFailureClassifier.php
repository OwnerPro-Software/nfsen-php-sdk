<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Support;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use OwnerPro\Nfsen\Exceptions\CommunicationException;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Exceptions\RequestNotDeliveredException;
use Throwable;

/**
 * Mapeia uma falha de transporte para o contrato tipado de exceções.
 *
 * Regra de classificação: certeza obrigatória, viés para indeterminado.
 * RequestNotDeliveredException só é produzida com evidência inequívoca de que
 * nenhum byte HTTP chegou ao servidor (errno cURL 6/7/35/58/60). Qualquer
 * ambiguidade — errno ausente, exceção estranha na cadeia — classifica como
 * indeterminado: os custos são assimétricos, e um falso "não entregue"
 * convida a um retry cego de uma emissão que pode ter acontecido.
 *
 * cURL 28 (timeout) é SEMPRE indeterminado: em conexão keep-alive reutilizada
 * o cURL zera os timers de conexão (curl issue #2703), então não há como
 * provar que um timeout ocorreu na fase de connect.
 *
 * A DECISÃO entregue/não-entregue usa apenas o errno do handler context do
 * Guzzle — nunca o texto da mensagem, que varia entre versões do libcurl.
 * Já a `phase` da IndeterminateResultException é informacional: sem errno,
 * cai no sniffing de mensagem legado para não perder diagnóstico.
 */
final readonly class TransportFailureClassifier
{
    public static function classify(Throwable $failure): CommunicationException
    {
        $errno = self::curlErrno($failure);

        return match (true) {
            $errno === 6 => new RequestNotDeliveredException('dns', $failure), // CURLE_COULDNT_RESOLVE_HOST
            $errno === 7 => new RequestNotDeliveredException('connect', $failure), // CURLE_COULDNT_CONNECT
            $errno === 35, // CURLE_SSL_CONNECT_ERROR
            $errno === 58, // CURLE_SSL_CERTPROBLEM
            $errno === 60 => new RequestNotDeliveredException('tls', $failure), // CURLE_PEER_FAILED_VERIFICATION (CURLE_SSL_CACERT no PHP)
            $errno === 28, // CURLE_OPERATION_TIMEDOUT — sempre indeterminado, ver docblock da classe
            $errno === 52 => IndeterminateResultException::fromTransportFailureWithPhase($failure, 'read'), // CURLE_GOT_NOTHING
            $errno === 18, // CURLE_PARTIAL_FILE
            $errno === 56, // CURLE_RECV_ERROR
            $errno === 92 => IndeterminateResultException::fromTransportFailureWithPhase($failure, 'transfer'), // CURLE_HTTP2_STREAM
            default => IndeterminateResultException::fromTransportFailure($failure),
        };
    }

    /**
     * Extrai o errno do cURL do handler context do Guzzle, percorrendo a
     * cadeia de exceções: dependendo da versão do Laravel, a exceção do
     * Guzzle chega crua ou envelopada (com a original em getPrevious()).
     */
    private static function curlErrno(Throwable $failure): ?int
    {
        for ($e = $failure; $e instanceof Throwable; $e = $e->getPrevious()) {
            if ($e instanceof ConnectException || $e instanceof RequestException) {
                $errno = $e->getHandlerContext()['errno'] ?? null;

                return is_int($errno) ? $errno : null;
            }
        }

        return null;
    }
}
