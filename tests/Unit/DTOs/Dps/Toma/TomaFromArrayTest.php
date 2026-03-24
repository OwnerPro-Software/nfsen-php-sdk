<?php

covers(\OwnerPro\Nfsen\Dps\DTO\Toma\Toma::class);

use OwnerPro\Nfsen\Dps\DTO\Shared\End;
use OwnerPro\Nfsen\Dps\DTO\Toma\Toma;
use OwnerPro\Nfsen\Dps\Enums\Shared\CNaoNIF;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

it('Toma::fromArray creates instance from array', function () {
    $dto = Toma::fromArray([
        'xNome' => 'Tomador Teste',
        'CNPJ' => '98765432000100',
    ]);

    expect($dto)->toBeInstanceOf(Toma::class)
        ->and($dto->xNome)->toBe('Tomador Teste');
});

it('Toma::fromArray preserves CPF and non-exclusive optional fields', function () {
    $dto = Toma::fromArray([
        'xNome' => 'Tomador',
        'CPF' => '12345678901',
        'CAEPF' => 'CAEPF789',
        'IM' => 'IM001',
        'end' => ['xLgr' => 'Rua', 'nro' => '1', 'xBairro' => 'B', 'endNac' => ['cMun' => '3501608', 'CEP' => '01310100']],
        'fone' => '11888888888',
        'email' => 'toma@test.com',
    ]);

    expect($dto->CPF)->toBe('12345678901')
        ->and($dto->CAEPF)->toBe('CAEPF789')
        ->and($dto->IM)->toBe('IM001')
        ->and($dto->end)->toBeInstanceOf(End::class)
        ->and($dto->fone)->toBe('11888888888')
        ->and($dto->email)->toBe('toma@test.com');
});

it('Toma::fromArray preserves NIF', function () {
    $dto = Toma::fromArray([
        'xNome' => 'Tomador',
        'NIF' => 'NIF456',
    ]);

    expect($dto->NIF)->toBe('NIF456');
});

it('Toma::fromArray preserves cNaoNIF', function () {
    $dto = Toma::fromArray([
        'xNome' => 'Tomador',
        'cNaoNIF' => '0',
    ]);

    expect($dto->cNaoNIF)->toBe(CNaoNIF::NaoInformado);
});

it('Toma rejects when no identifier provided', function () {
    Toma::fromArray(['xNome' => 'T']);
})->throws(InvalidDpsArgument::class);

it('Toma::fromArray propagates path to End', function () {
    expect(fn () => Toma::fromArray([
        'xNome' => 'T',
        'CNPJ' => '98765432000100',
        'end' => ['xLgr' => 'Rua', 'nro' => '1', 'xBairro' => 'B'],
    ], path: 'infDPS/interm'))->toThrow(InvalidDpsArgument::class, '[infDPS/interm/end]');
});
