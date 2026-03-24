<?php

covers(
    \OwnerPro\Nfsen\Exceptions\NfseException::class,
    \OwnerPro\Nfsen\Exceptions\CertificateExpiredException::class,
    \OwnerPro\Nfsen\Exceptions\HttpException::class,
);

use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\NfseException;

it('NfseException is a RuntimeException', function () {
    $e = new NfseException('msg');
    expect($e)->toBeInstanceOf(\RuntimeException::class);
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

it('HttpException::fromResponse truncates body to 500 characters', function () {
    $longBody = str_repeat('x', 600);

    $e = HttpException::fromResponse(422, $longBody);

    expect($e->getResponseBody())->toHaveLength(500);
});

it('HttpException has empty responseBody by default', function () {
    $e = new HttpException('error');

    expect($e->getResponseBody())->toBe('');
});
