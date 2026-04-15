<?php

declare(strict_types=1);

use OwnerPro\Nfsen\NfsenClient;

covers(NfsenClient::class);

it('retorna true quando array tem enabled === true', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => true]))->toBeTrue();
});

it('retorna false quando block é null', function () {
    expect(NfsenClient::isDanfseEnabled(null))->toBeFalse();
});

it('retorna false quando block não é array', function () {
    expect(NfsenClient::isDanfseEnabled('string'))->toBeFalse();
});

it('retorna false quando enabled ausente', function () {
    expect(NfsenClient::isDanfseEnabled([]))->toBeFalse();
});

it('retorna false quando enabled é false', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => false]))->toBeFalse();
});

it('retorna false quando enabled é 1 (strict check enforces bool contract)', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => 1]))->toBeFalse();
});

it('retorna false quando enabled é string "true" (strict check)', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => 'true']))->toBeFalse();
});

it('retorna false quando enabled é 0 (strict check)', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => 0]))->toBeFalse();
});

it('retorna false quando enabled é array vazio (strict check)', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => []]))->toBeFalse();
});
