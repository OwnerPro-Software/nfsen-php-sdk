<?php

use OwnerPro\Nfsen\Dps\DTO\Serv\AtvEvento;
use OwnerPro\Nfsen\Dps\DTO\Serv\ComExt;
use OwnerPro\Nfsen\Dps\DTO\Serv\CServ;
use OwnerPro\Nfsen\Dps\DTO\Serv\EndExt;
use OwnerPro\Nfsen\Dps\DTO\Serv\EndObra;
use OwnerPro\Nfsen\Dps\DTO\Serv\EndSimples;
use OwnerPro\Nfsen\Dps\DTO\Serv\InfoCompl;
use OwnerPro\Nfsen\Dps\DTO\Serv\Obra;
use OwnerPro\Nfsen\Dps\DTO\Serv\Serv;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

covers(CServ::class, Serv::class, ComExt::class, Obra::class, EndObra::class, EndExt::class, EndSimples::class, AtvEvento::class, InfoCompl::class);

it('CServ::fromArray creates instance from array', function () {
    $dto = CServ::fromArray([
        'cTribNac' => '010101',
        'xDescServ' => 'Serviço de Teste',
        'cNBS' => '123456789',
    ]);

    expect($dto)->toBeInstanceOf(CServ::class);
});

it('Serv::fromArray creates instance from array', function () {
    $dto = Serv::fromArray([
        'cServ' => [
            'cTribNac' => '010101',
            'xDescServ' => 'Serviço de Teste',
            'cNBS' => '123456789',
        ],
        'cLocPrestacao' => '3501608',
    ]);

    expect($dto)->toBeInstanceOf(Serv::class)
        ->and($dto->cLocPrestacao)->toBe('3501608');
});

it('Serv::fromArray preserves cPaisPrestacao', function () {
    $dto = Serv::fromArray([
        'cServ' => ['cTribNac' => '010101', 'xDescServ' => 'Teste', 'cNBS' => '123'],
        'cPaisPrestacao' => '01058',
    ]);

    expect($dto->cPaisPrestacao)->toBe('01058');
});

it('Serv rejects when neither cLocPrestacao nor cPaisPrestacao', function () {
    Serv::fromArray([
        'cServ' => ['cTribNac' => '010101', 'xDescServ' => 'T', 'cNBS' => '1'],
    ]);
})->throws(InvalidDpsArgument::class);

it('ComExt::fromArray creates instance from array', function () {
    $dto = ComExt::fromArray([
        'mdPrestacao' => '0',
        'vincPrest' => '0',
        'tpMoeda' => 'USD',
        'vServMoeda' => '100.00',
        'mecAFComexP' => '00',
        'mecAFComexT' => '00',
        'movTempBens' => '0',
        'mdic' => '0',
    ]);

    expect($dto)->toBeInstanceOf(ComExt::class);
});

it('ComExt::fromArray preserves optional fields', function () {
    $dto = ComExt::fromArray([
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

    expect($dto->end)->toBeInstanceOf(EndObra::class);
});

it('EndObra::fromArray creates instance from array', function () {
    $dto = EndObra::fromArray([
        'xLgr' => 'Rua Obra',
        'nro' => '200',
        'xBairro' => 'Bairro Obra',
        'CEP' => '01310100',
        'xCpl' => 'Sala 10',
    ]);

    expect($dto)->toBeInstanceOf(EndObra::class)
        ->and($dto->CEP)->toBe('01310100')
        ->and($dto->xCpl)->toBe('Sala 10');
});

it('EndExt::fromArray creates instance from array', function () {
    $dto = EndExt::fromArray([
        'cEndPost' => '10001',
        'xCidade' => 'New York',
        'xEstProvReg' => 'NY',
    ]);

    expect($dto)->toBeInstanceOf(EndExt::class);
});

it('EndSimples::fromArray creates instance from array', function () {
    $dto = EndSimples::fromArray([
        'xLgr' => 'Rua Simples',
        'nro' => '300',
        'xBairro' => 'Bairro',
        'CEP' => '01310100',
        'xCpl' => 'Bloco B',
    ]);

    expect($dto)->toBeInstanceOf(EndSimples::class)
        ->and($dto->CEP)->toBe('01310100')
        ->and($dto->xCpl)->toBe('Bloco B');
});

it('AtvEvento::fromArray creates instance from array', function () {
    $dto = AtvEvento::fromArray([
        'xNome' => 'Evento Teste',
        'dtIni' => '2026-01-01',
        'dtFim' => '2026-01-02',
        'idAtvEvt' => '12345',
    ]);

    expect($dto)->toBeInstanceOf(AtvEvento::class);
});

it('InfoCompl::fromArray creates instance from array', function () {
    $dto = InfoCompl::fromArray([
        'idDocTec' => 'DOC123',
    ]);

    expect($dto)->toBeInstanceOf(InfoCompl::class);
});

it('InfoCompl::fromArray preserves non-empty xItemPed', function () {
    $dto = InfoCompl::fromArray([
        'xItemPed' => ['Item 1', 'Item 2'],
    ]);

    expect($dto->xItemPed)->toBe(['Item 1', 'Item 2']);
});

it('InfoCompl rejects empty xItemPed', function () {
    InfoCompl::fromArray([
        'idDocTec' => 'DOC',
        'xItemPed' => [],
    ]);
})->throws(InvalidDpsArgument::class);
