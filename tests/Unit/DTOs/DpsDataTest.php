<?php

use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\DTOs\Dps\InfDPS\InfDPS;
use Pulsar\NfseNacional\DTOs\Dps\Prestador\Prestador;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Servico;
use Pulsar\NfseNacional\DTOs\Dps\Valores\Valores;
use Pulsar\NfseNacional\Builders\Xml\DpsBuilder;

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
        ->and($data->prest)->toBeInstanceOf(Prestador::class)
        ->and($data->serv)->toBeInstanceOf(Servico::class)
        ->and($data->valores)->toBeInstanceOf(Valores::class)
        ->and($data->subst)->toBeNull()
        ->and($data->toma)->toBeNull()
        ->and($data->interm)->toBeNull()
        ->and($data->IBSCBS)->toBeNull();
});
