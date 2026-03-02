<?php

use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoReeRepRes;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when documentos is empty array', function () {
    expect(fn () => new InfoReeRepRes(documentos: []))
        ->toThrow(InvalidDpsArgument::class, 'ao menos um');
});
