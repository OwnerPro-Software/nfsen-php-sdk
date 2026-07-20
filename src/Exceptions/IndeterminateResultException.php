<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Exceptions;

use Throwable;

/**
 * Resultado indeterminado: o SDK não conseguiu obter uma resposta completa e
 * legível do servidor — a requisição pode ou não ter sido recebida e
 * processada pela SEFIN.
 *
 * Cobre três situações:
 * - Falha antes de qualquer resposta (timeout, DNS, conexão recusada, TLS):
 *   a requisição pode nem ter chegado ao servidor.
 * - Falha no meio da transferência (conexão resetada, corpo truncado): o
 *   servidor recebeu e processou, mas o resultado não pôde ser lido.
 * - Resposta 2xx com corpo ilegível (JSON inválido ou vazio): o servidor
 *   confirmou o processamento, mas o resultado não pôde ser interpretado.
 *
 * Contrato: NUNCA faça retry cego de emissão após capturar esta exceção — a
 * NFS-e pode já ter sido emitida e um retry causaria dupla emissão. Reconcilie
 * primeiro: calcule o ID com `DpsId::generate()` e consulte
 * `consultar()->dps($id)`:
 * - encontrou → a nota FOI emitida; siga o fluxo normal com a chave retornada;
 * - `NfseResponse::DPS_NOT_FOUND` → a emissão NÃO aconteceu; é seguro
 *   re-emitir com o mesmo nDPS.
 *
 * Qualquer outra exceção ou resposta do SDK é uma resposta definitiva do
 * servidor (rejeição, erro de negócio, erro de certificado).
 *
 * @api
 */
final class IndeterminateResultException extends NfseException
{
    /**
     * @param  'body'|'connect'|'dns'|'read'|'tls'|'transfer'|null  $phase  fase da falha, quando detectável
     */
    public function __construct(
        string $message,
        public readonly ?string $phase = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromTransportFailure(Throwable $previous): self
    {
        return new self(
            'Resultado indeterminado: a comunicação falhou antes de uma resposta completa ser recebida. '.$previous->getMessage(),
            self::detectPhase($previous->getMessage()),
            $previous,
        );
    }

    public static function fromUnreadableResponse(int $statusCode, string $body): self
    {
        return new self(
            sprintf(
                'Resultado indeterminado: o servidor respondeu HTTP %d, mas o corpo da resposta não pôde ser interpretado. Corpo: "%s"',
                $statusCode,
                substr($body, 0, 200),
            ),
            'body',
        );
    }

    /**
     * @return 'connect'|'dns'|'read'|'tls'|'transfer'|null
     */
    private static function detectPhase(string $message): ?string
    {
        return match (true) {
            str_contains($message, 'cURL error 6:') => 'dns',
            str_contains($message, 'cURL error 7:') => 'connect',
            str_contains($message, 'cURL error 35:'),
            str_contains($message, 'cURL error 58:'),
            str_contains($message, 'cURL error 60:') => 'tls',
            str_contains($message, 'cURL error 18:'),
            str_contains($message, 'cURL error 56:'),
            str_contains($message, 'cURL error 92:') => 'transfer',
            str_contains($message, 'cURL error 28:') => str_contains($message, 'Connection timed out') ? 'connect' : 'read',
            default => null,
        };
    }
}
