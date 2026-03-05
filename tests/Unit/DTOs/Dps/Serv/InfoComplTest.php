<?php

covers(\Pulsar\NfseNacional\Dps\DTO\Serv\InfoCompl::class);
use Pulsar\NfseNacional\Dps\DTO\Serv\InfoCompl;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when xItemPed is empty array', function () {
    expect(fn () => new InfoCompl(xItemPed: []))
        ->toThrow(InvalidDpsArgument::class, 'ao menos um');
});

it('throws when all fields are null', function () {
    expect(fn () => new InfoCompl)
        ->toThrow(InvalidDpsArgument::class, 'ao menos um campo preenchido');
});
