<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Client\ConnectionException;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Exceptions\RequestNotDeliveredException;
use OwnerPro\Nfsen\Support\TransportFailureClassifier;

covers(TransportFailureClassifier::class);

function makeConnectFailure(int|string|null $errno, string $message = 'cURL error: transport failure'): ConnectionException
{
    $context = $errno === null ? [] : ['errno' => $errno];

    return new ConnectionException($message, 0, new ConnectException(
        $message,
        new GuzzleRequest('POST', 'https://sefin.nfse.gov.br/nfse'),
        null,
        $context,
    ));
}

it('classifies everything as indeterminate when detection is disabled', function () {
    $exception = TransportFailureClassifier::classify(makeConnectFailure(6, 'cURL error 6: Could not resolve host'), false);

    expect($exception)->toBeInstanceOf(IndeterminateResultException::class)
        ->and($exception->phase)->toBe('dns');
});

it('classifies provably undelivered errnos as RequestNotDeliveredException', function (int $errno, string $expectedPhase) {
    $exception = TransportFailureClassifier::classify(makeConnectFailure($errno), true);

    expect($exception)->toBeInstanceOf(RequestNotDeliveredException::class)
        ->and($exception->phase)->toBe($expectedPhase)
        ->and($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);
})->with([
    'dns (cURL 6)' => [6, 'dns'],
    'connect (cURL 7)' => [7, 'connect'],
    'tls handshake (cURL 35)' => [35, 'tls'],
    'tls client cert (cURL 58)' => [58, 'tls'],
    'tls peer verification (cURL 60)' => [60, 'tls'],
]);

it('classifies ambiguous or post-send errnos as IndeterminateResultException', function (int $errno, ?string $expectedPhase) {
    $exception = TransportFailureClassifier::classify(makeConnectFailure($errno), true);

    expect($exception)->toBeInstanceOf(IndeterminateResultException::class)
        ->and($exception->phase)->toBe($expectedPhase);
})->with([
    'timeout (cURL 28) always indeterminate' => [28, 'read'],
    'empty reply (cURL 52)' => [52, 'read'],
    'partial body (cURL 18)' => [18, 'transfer'],
    'recv failure (cURL 56)' => [56, 'transfer'],
    'http2 stream (cURL 92)' => [92, 'transfer'],
    'unknown errno (cURL 55)' => [55, null],
]);

it('classifies as indeterminate but keeps the message-sniffed phase when errno is missing', function () {
    // Sem errno não há evidência para "não entregue" (decisão), mas a fase
    // informacional ainda vem do texto da mensagem para não perder diagnóstico.
    $exception = TransportFailureClassifier::classify(makeConnectFailure(null, 'cURL error 6: Could not resolve host'), true);

    expect($exception)->toBeInstanceOf(IndeterminateResultException::class)
        ->and($exception->phase)->toBe('dns');
});

it('classifies as indeterminate when errno is not an integer', function () {
    $exception = TransportFailureClassifier::classify(makeConnectFailure('6'), true);

    expect($exception)->toBeInstanceOf(IndeterminateResultException::class)
        ->and($exception->phase)->toBeNull();
});

it('classifies as indeterminate when no guzzle exception exists in the chain', function () {
    $exception = TransportFailureClassifier::classify(new ConnectionException('falha sem previous'), true);

    expect($exception)->toBeInstanceOf(IndeterminateResultException::class)
        ->and($exception->phase)->toBeNull();
});

it('reads the errno when the failure itself is the guzzle exception', function () {
    // Mensagem sem padrão sniffável: a fase 'transfer' só pode vir do errno.
    $failure = new GuzzleRequestException(
        'Recv failure: Connection reset by peer',
        new GuzzleRequest('GET', 'https://sefin.nfse.gov.br/dps/DPS1'),
        null,
        null,
        ['errno' => 56],
    );

    $exception = TransportFailureClassifier::classify($failure, true);

    expect($exception)->toBeInstanceOf(IndeterminateResultException::class)
        ->and($exception->phase)->toBe('transfer');
});

it('walks through foreign exceptions in the chain to find the guzzle one', function () {
    $inner = new ConnectException(
        'cURL error 7: Failed to connect',
        new GuzzleRequest('POST', 'https://sefin.nfse.gov.br/nfse'),
        null,
        ['errno' => 7],
    );
    $failure = new ConnectionException('wrapper externo', 0, new RuntimeException('camada intermediária', 0, $inner));

    $exception = TransportFailureClassifier::classify($failure, true);

    expect($exception)->toBeInstanceOf(RequestNotDeliveredException::class)
        ->and($exception->phase)->toBe('connect');
});

it('stops at the first guzzle exception even when a deeper one has a different errno', function () {
    $deeper = new ConnectException(
        'inner',
        new GuzzleRequest('POST', 'https://sefin.nfse.gov.br/nfse'),
        null,
        ['errno' => 6],
    );
    $first = new ConnectException(
        'outer',
        new GuzzleRequest('POST', 'https://sefin.nfse.gov.br/nfse'),
        $deeper,
        ['errno' => 7],
    );

    $exception = TransportFailureClassifier::classify(new ConnectionException('wrapper', 0, $first), true);

    expect($exception)->toBeInstanceOf(RequestNotDeliveredException::class)
        ->and($exception->phase)->toBe('connect');
});
