<?php

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\ConnectionException;
use OwnerPro\Nfsen\Exceptions\CommunicationException;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Exceptions\NfseException;

covers(IndeterminateResultException::class);

it('is a CommunicationException and an NfseException', function () {
    expect(new IndeterminateResultException('falha'))
        ->toBeInstanceOf(CommunicationException::class)
        ->toBeInstanceOf(NfseException::class);
});

it('fromTransportFailureWithPhase uses the explicit phase instead of sniffing the message', function () {
    $previous = new ConnectionException('cURL error 6: Could not resolve host');

    $exception = IndeterminateResultException::fromTransportFailureWithPhase($previous, 'transfer');

    expect($exception->phase)->toBe('transfer')
        ->and($exception->getPrevious())->toBe($previous)
        ->and($exception->getMessage())->toStartWith('Resultado indeterminado:')
        ->and($exception->getMessage())->toContain('cURL error 6');
});

it('defaults phase and previous to null', function () {
    $exception = new IndeterminateResultException('falha');

    expect($exception->phase)->toBeNull()
        ->and($exception->getPrevious())->toBeNull();
});

it('fromTransportFailure preserves previous and prefixes message', function () {
    $previous = new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds with 0 bytes received');

    $exception = IndeterminateResultException::fromTransportFailure($previous);

    expect($exception->getPrevious())->toBe($previous)
        ->and($exception->getCode())->toBe(0)
        ->and($exception->getMessage())->toStartWith('Resultado indeterminado: a comunicação falhou antes de uma resposta completa ser recebida. ')
        ->and($exception->getMessage())->toContain('cURL error 28');
});

it('fromTransportFailure accepts mid-transfer guzzle exceptions', function () {
    $previous = new RequestException(
        'cURL error 56: Recv failure: Connection reset by peer',
        new Request('POST', 'https://sefin.nfse.gov.br/nfse'),
    );

    $exception = IndeterminateResultException::fromTransportFailure($previous);

    expect($exception->getPrevious())->toBe($previous)
        ->and($exception->phase)->toBe('transfer');
});

it('detects the transport failure phase from the cURL message', function (string $message, ?string $expectedPhase) {
    $exception = IndeterminateResultException::fromTransportFailure(new ConnectionException($message));

    expect($exception->phase)->toBe($expectedPhase);
})->with([
    'dns' => ['cURL error 6: Could not resolve host: sefin.nfse.gov.br', 'dns'],
    'connect refused' => ['cURL error 7: Failed to connect to sefin.nfse.gov.br port 443: Connection refused', 'connect'],
    'connect timeout' => ['cURL error 28: Connection timed out after 10001 milliseconds', 'connect'],
    'read timeout' => ['cURL error 28: Operation timed out after 30000 milliseconds with 0 bytes received', 'read'],
    'tls handshake' => ['cURL error 35: SSL connect error', 'tls'],
    'tls client cert' => ['cURL error 58: unable to load client cert', 'tls'],
    'tls server cert' => ['cURL error 60: SSL certificate problem: unable to get local issuer certificate', 'tls'],
    'partial body' => ['cURL error 18: transfer closed with outstanding read data remaining', 'transfer'],
    'recv failure' => ['cURL error 56: Recv failure: Connection reset by peer', 'transfer'],
    'http2 stream' => ['cURL error 92: HTTP/2 stream 0 was not closed cleanly', 'transfer'],
    'unknown' => ['Connection to https://sefin.nfse.gov.br failed', null],
]);

it('fromUnreadableResponse sets body phase and truncates body to 200 chars', function () {
    $body = 'A'.str_repeat('x', 199).'Z'.str_repeat('y', 100);

    $exception = IndeterminateResultException::fromUnreadableResponse(200, $body);

    expect($exception->phase)->toBe('body')
        ->and($exception->getPrevious())->toBeNull()
        ->and($exception->getMessage())->toContain('HTTP 200')
        ->and($exception->getMessage())->toContain('"A'.str_repeat('x', 199).'"')
        ->and($exception->getMessage())->not->toContain('Z');
});
