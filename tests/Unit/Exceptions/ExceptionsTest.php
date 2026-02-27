<?php

use Pulsar\NfseNacional\Exceptions\CertificateExpiredException;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Exceptions\NfseException;

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
