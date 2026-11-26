<?php

use OwnerPro\Nfsen\Enums\Leiaute;

covers(Leiaute::class);

it('resolves the layout from configuration', function (string $value, Leiaute $expected) {
    expect(Leiaute::fromConfig($value))->toBe($expected);
})->with([
    ['v1.01', Leiaute::V101],
    ['nt009', Leiaute::Nt009],
    ['NT009', Leiaute::Nt009],
    [' nt009 ', Leiaute::Nt009],
    ['V1.01', Leiaute::V101],
]);

it('rejects an unknown layout', function () {
    expect(fn () => Leiaute::fromConfig('v2'))->toThrow(
        InvalidArgumentException::class,
        "Leiaute NFSe inválido: 'v2'. Valores aceitos: 'v1.01', 'nt009'.",
    );
});

it('points each layout to its schema directory', function () {
    expect(Leiaute::V101->schemasPath('/schemes'))->toBe('/schemes');
    expect(Leiaute::Nt009->schemasPath('/schemes'))->toBe('/schemes/nt009');
});
