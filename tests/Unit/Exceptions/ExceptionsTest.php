<?php

use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\NfseException;

covers(NfseException::class, CertificateExpiredException::class, HttpException::class);

it('NfseException is a RuntimeException', function () {
    $e = new NfseException('msg');
    expect($e)->toBeInstanceOf(RuntimeException::class);
    expect($e->getMessage())->toBe('msg');
});

it('CertificateExpiredException extends NfseException', function () {
    $e = new CertificateExpiredException('cert expired');
    expect($e)->toBeInstanceOf(NfseException::class);
});

it('HttpException carries status code', function () {
    $e = new HttpException('timeout', 408);
    expect($e)->toBeInstanceOf(NfseException::class);
    expect($e->getCode())->toBe(408);
});

it('HttpException::fromResponse creates with status code and message', function () {
    $e = HttpException::fromResponse(500, 'Internal Server Error');

    expect($e->getMessage())->toBe('HTTP error: 500');
    expect($e->getCode())->toBe(500);
    expect($e->getResponseBody())->toBe('Internal Server Error');
});

it('HttpException::fromResponse keeps the body whole, however long', function () {
    // Truncar em 500 bytes quebrava NfseConsulter::parseHttpError(), que faz
    // json_decode() deste valor: um envelope de erro maior que o corte virava JSON
    // inválido e as mensagens da SEFIN sumiam. A mensagem da exceção não inclui o
    // corpo, então guardá-lo inteiro não infla log nenhum.
    $longBody = (string) json_encode(['erros' => [
        ['codigo' => 'E001', 'descricao' => str_repeat('x', 600)],
    ]]);

    $e = HttpException::fromResponse(422, $longBody);

    expect($e->getResponseBody())->toBe($longBody)
        ->and(json_decode($e->getResponseBody(), true))->toBeArray();
});

it('HttpException has empty responseBody by default', function () {
    $e = new HttpException('error');

    expect($e->getResponseBody())->toBe('');
});
