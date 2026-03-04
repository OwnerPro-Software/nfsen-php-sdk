<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Exceptions;

final class HttpException extends NfseException
{
    private string $responseBody = ''; // @pest-mutate-ignore EmptyStringToNotEmpty — property initializer uncovered by PCOV, overwritten in fromResponse()

    public static function fromResponse(int $statusCode, string $body): self
    {
        $exception = new self('HTTP error: '.$statusCode, $statusCode);
        $exception->responseBody = substr($body, 0, 500);

        return $exception;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
