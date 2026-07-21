<?php

use Illuminate\Http\Client\ConnectionException;
use OwnerPro\Nfsen\Exceptions\CommunicationException;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Exceptions\RequestNotDeliveredException;

covers(RequestNotDeliveredException::class);

it('is a CommunicationException and an NfseException', function () {
    $exception = new RequestNotDeliveredException('dns');

    expect($exception)->toBeInstanceOf(CommunicationException::class)
        ->and($exception)->toBeInstanceOf(NfseException::class);
});

it('exposes phase and builds message stating direct retry is safe', function () {
    $exception = new RequestNotDeliveredException('tls');

    expect($exception->phase)->toBe('tls')
        ->and($exception->getCode())->toBe(0)
        ->and($exception->getPrevious())->toBeNull()
        ->and($exception->getMessage())->toBe('A requisição não foi entregue ao servidor (falha de tls) — a operação não foi processada e o reenvio direto é seguro.');
});

it('appends the previous message and preserves the previous exception', function () {
    $previous = new ConnectionException('cURL error 7: Failed to connect to sefin.nfse.gov.br port 443');

    $exception = new RequestNotDeliveredException('connect', $previous);

    expect($exception->getPrevious())->toBe($previous)
        ->and($exception->getMessage())->toBe(
            'A requisição não foi entregue ao servidor (falha de connect) — a operação não foi processada e o reenvio direto é seguro.'
            .' cURL error 7: Failed to connect to sefin.nfse.gov.br port 443'
        );
});
