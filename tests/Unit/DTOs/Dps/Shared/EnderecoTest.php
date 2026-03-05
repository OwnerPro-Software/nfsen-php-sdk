<?php

covers(
    \Pulsar\NfseNacional\Dps\DTO\Shared\End::class,
    \Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice::class,
);
use Pulsar\NfseNacional\Dps\DTO\Shared\End;
use Pulsar\NfseNacional\Dps\DTO\Shared\EndExt;
use Pulsar\NfseNacional\Dps\DTO\Shared\EndNac;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when both endNac and endExt are set', function () {
    expect(fn () => new End(
        xLgr: 'Rua Teste',
        nro: '100',
        xBairro: 'Centro',
        endNac: new EndNac(cMun: '3501608', CEP: '01001000'),
        endExt: new EndExt(cPais: '01058', cEndPost: '10001', xCidade: 'NY', xEstProvReg: 'NY'),
    ))->toThrow(InvalidDpsArgument::class, '[end] Somente 1 dos seguintes campos deve ser informado: endereço nacional (endNac), endereço exterior (endExt). Informados: endereço nacional (endNac), endereço exterior (endExt).');
});

it('throws when neither endNac nor endExt is set', function () {
    expect(fn () => new End(
        xLgr: 'Rua Teste',
        nro: '100',
        xBairro: 'Centro',
    ))->toThrow(InvalidDpsArgument::class, '[end] Somente 1 dos seguintes campos deve ser informado: endereço nacional (endNac), endereço exterior (endExt). Nenhum foi informado.');
});
