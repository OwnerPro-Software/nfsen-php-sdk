<?php

covers(
    \Pulsar\NfseNacional\Dps\DTO\Shared\RegTrib::class,
    \Pulsar\NfseNacional\Dps\DTO\Shared\EnderecoNacional::class,
    \Pulsar\NfseNacional\Dps\DTO\Shared\EnderecoExterior::class,
    \Pulsar\NfseNacional\Dps\DTO\Shared\Endereco::class,
);

use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Dps\DTO\Shared\EnderecoExterior;
use Pulsar\NfseNacional\Dps\DTO\Shared\EnderecoNacional;
use Pulsar\NfseNacional\Dps\DTO\Shared\RegTrib;

it('RegTrib::fromArray creates instance from array', function () {
    $dto = RegTrib::fromArray([
        'opSimpNac' => '1',
        'regEspTrib' => '0',
    ]);

    expect($dto)->toBeInstanceOf(RegTrib::class);
});

it('RegTrib::fromArray throws ValueError for invalid opSimpNac', function () {
    RegTrib::fromArray([
        'opSimpNac' => 'INVALID',
        'regEspTrib' => '0',
    ]);
})->throws(ValueError::class);

it('EnderecoNacional::fromArray creates instance from array', function () {
    $dto = EnderecoNacional::fromArray([
        'cMun' => '3501608',
        'CEP' => '01310100',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoNacional::class)
        ->and($dto->cMun)->toBe('3501608');
});

it('EnderecoExterior::fromArray creates instance from array', function () {
    $dto = EnderecoExterior::fromArray([
        'cPais' => '01058',
        'cEndPost' => '10001',
        'xCidade' => 'New York',
        'xEstProvReg' => 'NY',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoExterior::class)
        ->and($dto->cPais)->toBe('01058');
});

it('Endereco::fromArray creates instance with endNac', function () {
    $dto = Endereco::fromArray([
        'xLgr' => 'Rua Teste',
        'nro' => '100',
        'xBairro' => 'Centro',
        'endNac' => [
            'cMun' => '3501608',
            'CEP' => '01310100',
        ],
    ]);

    expect($dto)->toBeInstanceOf(Endereco::class)
        ->and($dto->endNac)->toBeInstanceOf(EnderecoNacional::class);
});

it('Endereco::fromArray preserves xCpl', function () {
    $dto = Endereco::fromArray([
        'xLgr' => 'Rua Teste',
        'nro' => '100',
        'xBairro' => 'Centro',
        'endNac' => ['cMun' => '3501608', 'CEP' => '01310100'],
        'xCpl' => 'Apto 42',
    ]);

    expect($dto->xCpl)->toBe('Apto 42');
});
