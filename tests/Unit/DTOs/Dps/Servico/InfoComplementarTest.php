<?php

covers(\Pulsar\NfseNacional\Dps\DTO\Servico\InfoComplementar::class);
use Pulsar\NfseNacional\Dps\DTO\Servico\InfoComplementar;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when xItemPed is empty array', function () {
    expect(fn () => new InfoComplementar(xItemPed: []))
        ->toThrow(InvalidDpsArgument::class, 'ao menos um');
});

it('throws when all fields are null', function () {
    expect(fn () => new InfoComplementar)
        ->toThrow(InvalidDpsArgument::class, 'ao menos um campo preenchido');
});
