<?php

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\IBSCBS;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\InfDPS;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\Subst;
use OwnerPro\Nfsen\Dps\DTO\Prest\Prest;
use OwnerPro\Nfsen\Dps\DTO\Serv\Serv;
use OwnerPro\Nfsen\Dps\DTO\Toma\Toma;
use OwnerPro\Nfsen\Dps\DTO\Valores\Valores;
use OwnerPro\Nfsen\Xml\DpsBuilder;

covers(DpsData::class);

it('exposes all five groups as readonly properties', function () {
    $infDps = makeInfDps();
    $prestador = makePrestadorCnpj();
    $servico = makeServicoMinimo();
    $valores = makeValoresMinimo();

    $data = new DpsData(
        infDPS: $infDps,
        subst: null,
        prest: $prestador,
        toma: null,
        interm: null,
        serv: $servico,
        valores: $valores,
    );

    expect($data)
        ->infDPS->toBe($infDps)
        ->prest->toBe($prestador)
        ->toma->toBeNull()
        ->subst->toBeNull()
        ->interm->toBeNull()
        ->serv->toBe($servico)
        ->valores->toBe($valores);
});

it('produces valid XML when passed to DpsBuilder', function (DpsData $data) {
    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->build($data);

    $doc = new DOMDocument;
    $doc->loadXML($xml);

    expect($doc->getElementsByTagName('DPS')->length)->toBe(1);
    expect($doc->getElementsByTagName('infDPS')->length)->toBe(1);
    expect($doc->getElementsByTagName('tpAmb')->item(0)->textContent)->toBe('2');
    expect($doc->getElementsByTagName('serie')->item(0)->textContent)->toBe('1');
})->with('dpsData');

it('creates DpsData from array via fromArray', function () {
    $data = DpsData::fromArray([
        'infDPS' => [
            'tpAmb' => '2',
            'dhEmi' => '2026-02-27T10:00:00-03:00',
            'verAplic' => '1.0',
            'serie' => '1',
            'nDPS' => '1',
            'dCompet' => '2026-02-27',
            'tpEmit' => '1',
            'cLocEmi' => '3501608',
        ],
        'prest' => [
            'CNPJ' => '12345678000195',
            'regTrib' => [
                'opSimpNac' => '1',
                'regEspTrib' => '0',
            ],
            'xNome' => 'Empresa Teste',
        ],
        'serv' => [
            'cServ' => [
                'cTribNac' => '010101',
                'xDescServ' => 'Serviço de Teste',
                'cNBS' => '123456789',
            ],
            'cLocPrestacao' => '3501608',
        ],
        'valores' => [
            'vServPrest' => ['vServ' => '100.00'],
            'trib' => [
                'tribMun' => [
                    'tribISSQN' => '1',
                    'tpRetISSQN' => '1',
                ],
                'indTotTrib' => '0',
            ],
        ],
    ]);

    expect($data)->toBeInstanceOf(DpsData::class)
        ->and($data->infDPS)->toBeInstanceOf(InfDPS::class)
        ->and($data->prest)->toBeInstanceOf(Prest::class)
        ->and($data->serv)->toBeInstanceOf(Serv::class)
        ->and($data->valores)->toBeInstanceOf(Valores::class)
        ->and($data->subst)->toBeNull()
        ->and($data->toma)->toBeNull()
        ->and($data->interm)->toBeNull()
        ->and($data->IBSCBS)->toBeNull();
});

it('DpsData::fromArray creates instance with toma and subst', function () {
    $dto = DpsData::fromArray([
        'infDPS' => [
            'tpAmb' => '2', 'dhEmi' => '2026-02-27T10:00:00-03:00',
            'verAplic' => '1.0', 'serie' => '1', 'nDPS' => '1',
            'dCompet' => '2026-02-27', 'tpEmit' => '1', 'cLocEmi' => '3501608',
        ],
        'prest' => [
            'CNPJ' => '12345678000195',
            'regTrib' => ['opSimpNac' => '1', 'regEspTrib' => '0'],
        ],
        'serv' => [
            'cServ' => ['cTribNac' => '010101', 'xDescServ' => 'Serviço', 'cNBS' => '123456789'],
            'cLocPrestacao' => '3501608',
        ],
        'valores' => [
            'vServPrest' => ['vServ' => '100.00'],
            'trib' => ['tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'], 'indTotTrib' => '0'],
        ],
        'toma' => ['xNome' => 'Tomador', 'CNPJ' => '98765432000100'],
        'subst' => ['chSubstda' => '12345678901234567890123456789012345678901234567890', 'cMotivo' => '01'],
        'interm' => ['xNome' => 'Intermediario', 'CPF' => '12345678901'],
        'IBSCBS' => [
            'finNFSe' => '0', 'indFinal' => '0', 'cIndOp' => '001', 'indDest' => '0',
            'valores' => ['trib' => ['gIBSCBS' => ['CST' => '00', 'cClassTrib' => '001']]],
        ],
    ]);

    expect($dto)->toBeInstanceOf(DpsData::class)
        ->and($dto->toma)->toBeInstanceOf(Toma::class)
        ->and($dto->subst)->toBeInstanceOf(Subst::class)
        ->and($dto->interm)->toBeInstanceOf(Toma::class)
        ->and($dto->IBSCBS)->toBeInstanceOf(IBSCBS::class);
});
