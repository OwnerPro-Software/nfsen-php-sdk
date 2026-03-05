<?php

covers(\Pulsar\NfseNacional\Dps\DTO\Prest\Prest::class);

use Pulsar\NfseNacional\Dps\DTO\Prest\Prest;
use Pulsar\NfseNacional\Dps\DTO\Shared\End;
use Pulsar\NfseNacional\Dps\Enums\Shared\CNaoNIF;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('Prest::fromArray creates instance from array', function () {
    $dto = Prest::fromArray([
        'CNPJ' => '12345678000195',
        'regTrib' => [
            'opSimpNac' => '1',
            'regEspTrib' => '0',
        ],
        'xNome' => 'Empresa Teste',
    ]);

    expect($dto)->toBeInstanceOf(Prest::class)
        ->and($dto->CNPJ)->toBe('12345678000195')
        ->and($dto->xNome)->toBe('Empresa Teste');
});

it('Prest::fromArray preserves CPF and non-exclusive optional fields', function () {
    $dto = Prest::fromArray([
        'regTrib' => ['opSimpNac' => '1', 'regEspTrib' => '0'],
        'CPF' => '12345678901',
        'CAEPF' => 'CAEPF123',
        'IM' => 'IM456',
        'xNome' => 'Empresa',
        'end' => ['xLgr' => 'Rua', 'nro' => '1', 'xBairro' => 'B', 'endNac' => ['cMun' => '3501608', 'CEP' => '01310100']],
        'fone' => '11999999999',
        'email' => 'a@b.com',
    ]);

    expect($dto->CPF)->toBe('12345678901')
        ->and($dto->CAEPF)->toBe('CAEPF123')
        ->and($dto->IM)->toBe('IM456')
        ->and($dto->end)->toBeInstanceOf(End::class)
        ->and($dto->fone)->toBe('11999999999')
        ->and($dto->email)->toBe('a@b.com');
});

it('Prest::fromArray preserves NIF', function () {
    $dto = Prest::fromArray([
        'regTrib' => ['opSimpNac' => '1', 'regEspTrib' => '0'],
        'NIF' => 'NIF123',
    ]);

    expect($dto->NIF)->toBe('NIF123');
});

it('Prest::fromArray preserves cNaoNIF', function () {
    $dto = Prest::fromArray([
        'regTrib' => ['opSimpNac' => '1', 'regEspTrib' => '0'],
        'cNaoNIF' => '1',
    ]);

    expect($dto->cNaoNIF)->toBe(CNaoNIF::Dispensado);
});

it('Prest rejects when no identifier provided', function () {
    Prest::fromArray([
        'regTrib' => ['opSimpNac' => '1', 'regEspTrib' => '0'],
    ]);
})->throws(InvalidDpsArgument::class);
