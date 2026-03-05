<?php

covers(\Pulsar\NfseNacional\Dps\DTO\Prestador\Prestador::class);

use Pulsar\NfseNacional\Dps\DTO\Prestador\Prestador;
use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Dps\Enums\Shared\CNaoNIF;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('Prestador::fromArray creates instance from array', function () {
    $dto = Prestador::fromArray([
        'CNPJ' => '12345678000195',
        'regTrib' => [
            'opSimpNac' => '1',
            'regEspTrib' => '0',
        ],
        'xNome' => 'Empresa Teste',
    ]);

    expect($dto)->toBeInstanceOf(Prestador::class)
        ->and($dto->CNPJ)->toBe('12345678000195')
        ->and($dto->xNome)->toBe('Empresa Teste');
});

it('Prestador::fromArray preserves CPF and non-exclusive optional fields', function () {
    $dto = Prestador::fromArray([
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
        ->and($dto->end)->toBeInstanceOf(Endereco::class)
        ->and($dto->fone)->toBe('11999999999')
        ->and($dto->email)->toBe('a@b.com');
});

it('Prestador::fromArray preserves NIF', function () {
    $dto = Prestador::fromArray([
        'regTrib' => ['opSimpNac' => '1', 'regEspTrib' => '0'],
        'NIF' => 'NIF123',
    ]);

    expect($dto->NIF)->toBe('NIF123');
});

it('Prestador::fromArray preserves cNaoNIF', function () {
    $dto = Prestador::fromArray([
        'regTrib' => ['opSimpNac' => '1', 'regEspTrib' => '0'],
        'cNaoNIF' => '1',
    ]);

    expect($dto->cNaoNIF)->toBe(CNaoNIF::Dispensado);
});

it('Prestador rejects when no identifier provided', function () {
    Prestador::fromArray([
        'regTrib' => ['opSimpNac' => '1', 'regEspTrib' => '0'],
    ]);
})->throws(InvalidDpsArgument::class);
