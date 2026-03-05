<?php

covers(\Pulsar\NfseNacional\Dps\DTO\Serv\Obra::class);
use Pulsar\NfseNacional\Dps\DTO\Serv\Obra;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when no choice is set', function () {
    expect(fn () => new Obra(inscImobFisc: '12345'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});
