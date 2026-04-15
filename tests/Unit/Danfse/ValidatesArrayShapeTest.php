<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Danfse\Concerns\ValidatesArrayShape;

covers(ValidatesArrayShape::class);

final class ValidatesArrayShapeHarness
{
    use ValidatesArrayShape {
        rejectUnknownKeys as public;
    }
}

it('passa silencioso quando todas as chaves estão na whitelist', function () {
    expect(fn () => ValidatesArrayShapeHarness::rejectUnknownKeys(
        ['a' => 1, 'b' => 2],
        ['a', 'b', 'c'],
        'ctx',
    ))->not->toThrow(InvalidArgumentException::class);
});

it('passa silencioso quando array vazio', function () {
    expect(fn () => ValidatesArrayShapeHarness::rejectUnknownKeys([], ['a', 'b'], 'ctx'))
        ->not->toThrow(InvalidArgumentException::class);
});

it('lança quando há uma chave desconhecida', function () {
    ValidatesArrayShapeHarness::rejectUnknownKeys(
        ['a' => 1, 'foo' => 2],
        ['a', 'b'],
        'danfse',
    );
})->throws(InvalidArgumentException::class, 'danfse: chave(s) desconhecida(s): foo');

it('lança listando todas as chaves desconhecidas', function () {
    ValidatesArrayShapeHarness::rejectUnknownKeys(
        ['a' => 1, 'foo' => 2, 'bar' => 3],
        ['a'],
        'danfse.municipality',
    );
})->throws(InvalidArgumentException::class, 'danfse.municipality: chave(s) desconhecida(s): foo, bar');
