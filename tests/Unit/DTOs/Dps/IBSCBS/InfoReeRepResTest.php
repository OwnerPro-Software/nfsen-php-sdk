<?php

covers(\Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoReeRepRes::class);
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoReeRepRes;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when documentos is empty array', function () {
    expect(fn () => new InfoReeRepRes(documentos: []))
        ->toThrow(InvalidDpsArgument::class, 'ao menos um');
});
