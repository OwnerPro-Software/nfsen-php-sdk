<?php

use Pulsar\NfseNacional\Dps\DTO\Servico\Obra;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when no choice is set', function () {
    expect(fn () => new Obra(inscImobFisc: '12345'))
        ->toThrow(InvalidDpsArgument::class, 'exatamente um');
});
