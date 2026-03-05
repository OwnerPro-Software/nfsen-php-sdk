<?php

covers(\Pulsar\NfseNacional\Dps\DTO\Tomador\Tomador::class);

use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Dps\DTO\Tomador\Tomador;
use Pulsar\NfseNacional\Dps\Enums\Shared\CodNaoNIF;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('Tomador::fromArray creates instance from array', function () {
    $dto = Tomador::fromArray([
        'xNome' => 'Tomador Teste',
        'CNPJ' => '98765432000100',
    ]);

    expect($dto)->toBeInstanceOf(Tomador::class)
        ->and($dto->xNome)->toBe('Tomador Teste');
});

it('Tomador::fromArray preserves CPF and non-exclusive optional fields', function () {
    $dto = Tomador::fromArray([
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
        ->and($dto->end)->toBeInstanceOf(Endereco::class)
        ->and($dto->fone)->toBe('11888888888')
        ->and($dto->email)->toBe('toma@test.com');
});

it('Tomador::fromArray preserves NIF', function () {
    $dto = Tomador::fromArray([
        'xNome' => 'Tomador',
        'NIF' => 'NIF456',
    ]);

    expect($dto->NIF)->toBe('NIF456');
});

it('Tomador::fromArray preserves cNaoNIF', function () {
    $dto = Tomador::fromArray([
        'xNome' => 'Tomador',
        'cNaoNIF' => '0',
    ]);

    expect($dto->cNaoNIF)->toBe(CodNaoNIF::NaoInformado);
});

it('Tomador rejects when no identifier provided', function () {
    Tomador::fromArray(['xNome' => 'T']);
})->throws(InvalidDpsArgument::class);

it('Tomador::fromArray propagates path to Endereco', function () {
    expect(fn () => Tomador::fromArray([
        'xNome' => 'T',
        'CNPJ' => '98765432000100',
        'end' => ['xLgr' => 'Rua', 'nro' => '1', 'xBairro' => 'B'],
    ], path: 'infDPS/interm'))->toThrow(InvalidDpsArgument::class, '[infDPS/interm/end]');
});
