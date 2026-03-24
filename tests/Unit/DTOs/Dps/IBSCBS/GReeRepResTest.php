<?php

covers(\OwnerPro\Nfsen\Dps\DTO\IBSCBS\GReeRepRes::class);
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\GReeRepRes;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

it('throws when documentos is empty array', function () {
    expect(fn () => new GReeRepRes(documentos: []))
        ->toThrow(InvalidDpsArgument::class, 'ao menos um');
});
