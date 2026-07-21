<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Exceptions;

final class HttpException extends NfseException
{
    private string $responseBody = ''; // @pest-mutate-ignore EmptyStringToNotEmpty — property initializer uncovered by PCOV, overwritten in fromResponse()

    public static function fromResponse(int $statusCode, string $body): self
    {
        $exception = new self('HTTP error: '.$statusCode, $statusCode);
        $exception->responseBody = $body;

        return $exception;
    }

    /**
     * Corpo íntegro da resposta de erro — guardado sem corte, de propósito.
     *
     * Até a 3.0.0 era truncado em 500 bytes, o que quebrava quem precisa desserializá-lo:
     * `NfseConsulter::parseHttpError()` faz `json_decode()` deste valor, e um envelope
     * de erro da SEFIN maior que o corte virava JSON inválido — as mensagens
     * estruturadas eram substituídas por um genérico "HTTP error: N". A mensagem da
     * exceção nunca incluiu o corpo, então guardá-lo inteiro não infla log algum.
     */
    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
