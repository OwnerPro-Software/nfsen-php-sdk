<?php

use OwnerPro\Nfsen\Danfse\Municipios;

covers(Municipios::class);

it('looks up São Paulo by IBGE code', function () {
    expect(Municipios::lookup(3550308))->toBe('São Paulo - SP');
});

it('looks up Niterói by IBGE code', function () {
    expect(Municipios::lookup(3303302))->toBe('Niterói - RJ');
});

it('accepts string IBGE code', function () {
    expect(Municipios::lookup('4304606'))->toContain(' - RS');
});

it('returns dash for unknown code', function () {
    expect(Municipios::lookup(0))->toBe('-');
    expect(Municipios::lookup('9999999'))->toBe('-');
});

it('caches JSON after first call', function () {
    $first = Municipios::lookup(3550308);
    $second = Municipios::lookup(3550308);
    expect($second)->toBe($first);
});
