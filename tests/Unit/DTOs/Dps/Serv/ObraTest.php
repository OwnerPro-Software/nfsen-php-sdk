<?php

use OwnerPro\Nfsen\Dps\DTO\Serv\Obra;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

covers(Obra::class);

it('throws when no choice is set', function () {
    expect(fn () => new Obra(inscImobFisc: '12345'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});
