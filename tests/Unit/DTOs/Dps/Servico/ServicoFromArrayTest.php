<?php

covers(
    \Pulsar\NfseNacional\Dps\DTO\Servico\CodigoServico::class,
    \Pulsar\NfseNacional\Dps\DTO\Servico\Servico::class,
    \Pulsar\NfseNacional\Dps\DTO\Servico\ComercioExterior::class,
    \Pulsar\NfseNacional\Dps\DTO\Servico\Obra::class,
    \Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoObra::class,
    \Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoExteriorObra::class,
    \Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoSimples::class,
    \Pulsar\NfseNacional\Dps\DTO\Servico\AtividadeEvento::class,
    \Pulsar\NfseNacional\Dps\DTO\Servico\InfoComplementar::class,
);

use Pulsar\NfseNacional\Dps\DTO\Servico\AtividadeEvento;
use Pulsar\NfseNacional\Dps\DTO\Servico\CodigoServico;
use Pulsar\NfseNacional\Dps\DTO\Servico\ComercioExterior;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoExteriorObra;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoObra;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoSimples;
use Pulsar\NfseNacional\Dps\DTO\Servico\InfoComplementar;
use Pulsar\NfseNacional\Dps\DTO\Servico\Obra;
use Pulsar\NfseNacional\Dps\DTO\Servico\Servico;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('CodigoServico::fromArray creates instance from array', function () {
    $dto = CodigoServico::fromArray([
        'cTribNac' => '010101',
        'xDescServ' => 'Serviço de Teste',
        'cNBS' => '123456789',
    ]);

    expect($dto)->toBeInstanceOf(CodigoServico::class);
});

it('Servico::fromArray creates instance from array', function () {
    $dto = Servico::fromArray([
        'cServ' => [
            'cTribNac' => '010101',
            'xDescServ' => 'Serviço de Teste',
            'cNBS' => '123456789',
        ],
        'cLocPrestacao' => '3501608',
    ]);

    expect($dto)->toBeInstanceOf(Servico::class)
        ->and($dto->cLocPrestacao)->toBe('3501608');
});

it('Servico::fromArray preserves cPaisPrestacao', function () {
    $dto = Servico::fromArray([
        'cServ' => ['cTribNac' => '010101', 'xDescServ' => 'Teste', 'cNBS' => '123'],
        'cPaisPrestacao' => '01058',
    ]);

    expect($dto->cPaisPrestacao)->toBe('01058');
});

it('Servico rejects when neither cLocPrestacao nor cPaisPrestacao', function () {
    Servico::fromArray([
        'cServ' => ['cTribNac' => '010101', 'xDescServ' => 'T', 'cNBS' => '1'],
    ]);
})->throws(InvalidDpsArgument::class);

it('ComercioExterior::fromArray creates instance from array', function () {
    $dto = ComercioExterior::fromArray([
        'mdPrestacao' => '0',
        'vincPrest' => '0',
        'tpMoeda' => 'USD',
        'vServMoeda' => '100.00',
        'mecAFComexP' => '00',
        'mecAFComexT' => '00',
        'movTempBens' => '0',
        'mdic' => '0',
    ]);

    expect($dto)->toBeInstanceOf(ComercioExterior::class);
});

it('ComercioExterior::fromArray preserves optional fields', function () {
    $dto = ComercioExterior::fromArray([
        'mdPrestacao' => '0',
        'vincPrest' => '0',
        'tpMoeda' => 'USD',
        'vServMoeda' => '100.00',
        'mecAFComexP' => '00',
        'mecAFComexT' => '00',
        'movTempBens' => '0',
        'mdic' => '0',
        'nDI' => 'DI123',
        'nRE' => 'RE456',
    ]);

    expect($dto->nDI)->toBe('DI123')
        ->and($dto->nRE)->toBe('RE456');
});

it('Obra::fromArray creates instance from array', function () {
    $dto = Obra::fromArray([
        'cObra' => '12345678901234',
    ]);

    expect($dto)->toBeInstanceOf(Obra::class)
        ->and($dto->cObra)->toBe('12345678901234');
});

it('Obra::fromArray preserves inscImobFisc with cObra', function () {
    $dto = Obra::fromArray([
        'inscImobFisc' => 'INSC123',
        'cObra' => '12345678901234',
    ]);

    expect($dto->inscImobFisc)->toBe('INSC123')
        ->and($dto->cObra)->toBe('12345678901234');
});

it('Obra::fromArray preserves cCIB', function () {
    $dto = Obra::fromArray(['cCIB' => 'CIB789']);
    expect($dto->cCIB)->toBe('CIB789');
});

it('Obra::fromArray preserves end', function () {
    $dto = Obra::fromArray([
        'end' => ['xLgr' => 'Rua Obra', 'nro' => '200', 'xBairro' => 'Bairro', 'CEP' => '01310100'],
    ]);

    expect($dto->end)->toBeInstanceOf(EnderecoObra::class);
});

it('EnderecoObra::fromArray creates instance from array', function () {
    $dto = EnderecoObra::fromArray([
        'xLgr' => 'Rua Obra',
        'nro' => '200',
        'xBairro' => 'Bairro Obra',
        'CEP' => '01310100',
        'xCpl' => 'Sala 10',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoObra::class)
        ->and($dto->CEP)->toBe('01310100')
        ->and($dto->xCpl)->toBe('Sala 10');
});

it('EnderecoExteriorObra::fromArray creates instance from array', function () {
    $dto = EnderecoExteriorObra::fromArray([
        'cEndPost' => '10001',
        'xCidade' => 'New York',
        'xEstProvReg' => 'NY',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoExteriorObra::class);
});

it('EnderecoSimples::fromArray creates instance from array', function () {
    $dto = EnderecoSimples::fromArray([
        'xLgr' => 'Rua Simples',
        'nro' => '300',
        'xBairro' => 'Bairro',
        'CEP' => '01310100',
        'xCpl' => 'Bloco B',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoSimples::class)
        ->and($dto->CEP)->toBe('01310100')
        ->and($dto->xCpl)->toBe('Bloco B');
});

it('AtividadeEvento::fromArray creates instance from array', function () {
    $dto = AtividadeEvento::fromArray([
        'xNome' => 'Evento Teste',
        'dtIni' => '2026-01-01',
        'dtFim' => '2026-01-02',
        'idAtvEvt' => '12345',
    ]);

    expect($dto)->toBeInstanceOf(AtividadeEvento::class);
});

it('InfoComplementar::fromArray creates instance from array', function () {
    $dto = InfoComplementar::fromArray([
        'idDocTec' => 'DOC123',
    ]);

    expect($dto)->toBeInstanceOf(InfoComplementar::class);
});

it('InfoComplementar::fromArray preserves non-empty xItemPed', function () {
    $dto = InfoComplementar::fromArray([
        'xItemPed' => ['Item 1', 'Item 2'],
    ]);

    expect($dto->xItemPed)->toBe(['Item 1', 'Item 2']);
});

it('InfoComplementar rejects empty xItemPed', function () {
    InfoComplementar::fromArray([
        'idDocTec' => 'DOC',
        'xItemPed' => [],
    ]);
})->throws(InvalidDpsArgument::class);
