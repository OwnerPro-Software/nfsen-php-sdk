<?php

use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoExteriorObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoSimples;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when both CEP and endExt are set', function () {
    expect(fn () => new EnderecoSimples(
        xLgr: 'Rua Teste',
        nro: '100',
        xBairro: 'Centro',
        CEP: '01001000',
        endExt: new EnderecoExteriorObra(cEndPost: '10001', xCidade: 'NY', xEstProvReg: 'NY'),
    ))->toThrow(InvalidDpsArgument::class, 'exatamente um');
});

it('throws when neither CEP nor endExt is set', function () {
    expect(fn () => new EnderecoSimples(
        xLgr: 'Rua Teste',
        nro: '100',
        xBairro: 'Centro',
    ))->toThrow(InvalidDpsArgument::class, 'exatamente um');
});
