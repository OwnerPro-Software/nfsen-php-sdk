<?php

covers(
    \Pulsar\NfseNacional\Dps\DTO\Shared\RegTrib::class,
    \Pulsar\NfseNacional\Dps\DTO\Shared\EndNac::class,
    \Pulsar\NfseNacional\Dps\DTO\Shared\EndExt::class,
    \Pulsar\NfseNacional\Dps\DTO\Shared\End::class,
);

use Pulsar\NfseNacional\Dps\DTO\Shared\End;
use Pulsar\NfseNacional\Dps\DTO\Shared\EndExt;
use Pulsar\NfseNacional\Dps\DTO\Shared\EndNac;
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

it('EndNac::fromArray creates instance from array', function () {
    $dto = EndNac::fromArray([
        'cMun' => '3501608',
        'CEP' => '01310100',
    ]);

    expect($dto)->toBeInstanceOf(EndNac::class)
        ->and($dto->cMun)->toBe('3501608');
});

it('EndExt::fromArray creates instance from array', function () {
    $dto = EndExt::fromArray([
        'cPais' => '01058',
        'cEndPost' => '10001',
        'xCidade' => 'New York',
        'xEstProvReg' => 'NY',
    ]);

    expect($dto)->toBeInstanceOf(EndExt::class)
        ->and($dto->cPais)->toBe('01058');
});

it('End::fromArray creates instance with endNac', function () {
    $dto = End::fromArray([
        'xLgr' => 'Rua Teste',
        'nro' => '100',
        'xBairro' => 'Centro',
        'endNac' => [
            'cMun' => '3501608',
            'CEP' => '01310100',
        ],
    ]);

    expect($dto)->toBeInstanceOf(End::class)
        ->and($dto->endNac)->toBeInstanceOf(EndNac::class);
});

it('End::fromArray preserves xCpl', function () {
    $dto = End::fromArray([
        'xLgr' => 'Rua Teste',
        'nro' => '100',
        'xBairro' => 'Centro',
        'endNac' => ['cMun' => '3501608', 'CEP' => '01310100'],
        'xCpl' => 'Apto 42',
    ]);

    expect($dto->xCpl)->toBe('Apto 42');
});
