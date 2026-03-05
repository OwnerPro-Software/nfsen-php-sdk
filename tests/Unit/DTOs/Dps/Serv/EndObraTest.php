<?php

covers(\Pulsar\NfseNacional\Dps\DTO\Serv\EndObra::class);
use Pulsar\NfseNacional\Dps\DTO\Serv\EndExt;
use Pulsar\NfseNacional\Dps\DTO\Serv\EndObra;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when both CEP and endExt are set', function () {
    expect(fn () => new EndObra(
        xLgr: 'Rua Teste',
        nro: '100',
        xBairro: 'Centro',
        CEP: '01001000',
        endExt: new EndExt(cEndPost: '10001', xCidade: 'NY', xEstProvReg: 'NY'),
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when neither CEP nor endExt is set', function () {
    expect(fn () => new EndObra(
        xLgr: 'Rua Teste',
        nro: '100',
        xBairro: 'Centro',
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});
