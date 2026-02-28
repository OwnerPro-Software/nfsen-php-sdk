<?php

use Pulsar\NfseNacional\Enums\NfseAmbiente;

it('has producao value of 1', function () {
    expect(NfseAmbiente::PRODUCAO->value)->toBe(1);
});

it('has homologacao value of 2', function () {
    expect(NfseAmbiente::HOMOLOGACAO->value)->toBe(2);
});

it('can be created from value', function () {
    expect(NfseAmbiente::from(1))->toBe(NfseAmbiente::PRODUCAO);
    expect(NfseAmbiente::from(2))->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('fromConfig accepts integer values', function () {
    expect(NfseAmbiente::fromConfig(1))->toBe(NfseAmbiente::PRODUCAO);
    expect(NfseAmbiente::fromConfig(2))->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('fromConfig accepts string values', function () {
    expect(NfseAmbiente::fromConfig('producao'))->toBe(NfseAmbiente::PRODUCAO);
    expect(NfseAmbiente::fromConfig('homologacao'))->toBe(NfseAmbiente::HOMOLOGACAO);
    expect(NfseAmbiente::fromConfig('production'))->toBe(NfseAmbiente::PRODUCAO);
    expect(NfseAmbiente::fromConfig('homologation'))->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('fromConfig accepts numeric string values', function () {
    expect(NfseAmbiente::fromConfig('1'))->toBe(NfseAmbiente::PRODUCAO);
    expect(NfseAmbiente::fromConfig('2'))->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('fromConfig throws on unknown string value', function () {
    expect(fn () => NfseAmbiente::fromConfig('unknown'))
        ->toThrow(\InvalidArgumentException::class);
});

it('fromConfig throws InvalidArgumentException on invalid numeric value', function () {
    expect(fn () => NfseAmbiente::fromConfig(0))
        ->toThrow(\InvalidArgumentException::class, 'Ambiente NFSe inválido');

    expect(fn () => NfseAmbiente::fromConfig('3'))
        ->toThrow(\InvalidArgumentException::class, 'Ambiente NFSe inválido');
});
