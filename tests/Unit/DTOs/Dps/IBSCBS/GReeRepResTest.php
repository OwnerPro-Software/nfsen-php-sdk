<?php

covers(\Pulsar\NfseNacional\Dps\DTO\IBSCBS\GReeRepRes::class);
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\GReeRepRes;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when documentos is empty array', function () {
    expect(fn () => new GReeRepRes(documentos: []))
        ->toThrow(InvalidDpsArgument::class, 'ao menos um');
});
