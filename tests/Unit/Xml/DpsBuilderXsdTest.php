<?php

covers(\Pulsar\NfseNacional\Xml\DpsBuilder::class);

use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Dps\DTO\Servico\AtividadeEvento;
use Pulsar\NfseNacional\Dps\DTO\Servico\CodigoServico;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoSimples;
use Pulsar\NfseNacional\Dps\DTO\Servico\InfoComplementar;
use Pulsar\NfseNacional\Dps\DTO\Servico\Servico;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Support\XmlDocumentLoader;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('produces xml that validates against DPS_v1.01.xsd', function (DpsData $data) {
    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    // Se chegou aqui sem exception, o XML é válido
    expect($xml)->toContain('<DPS ');
})->with('dpsData');

it('DPS_v1.01.xsd scheme file exists', function () {
    $path = __DIR__.'/../../../storage/schemes/DPS_v1.01.xsd';
    expect(file_exists($path))->toBeTrue();
    expect(filesize($path))->toBeGreaterThan(0);
});

it('build() does not validate XSD (fast path)', function (DpsData $data) {
    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->build($data);

    // build() retorna XML sem validar — não lança exceção mesmo se inválido
    expect($xml)->toBeString();
})->with('dpsData');

it('buildAndValidate throws NfseException when XML loading fails', function (DpsData $data) {
    $loader = Mockery::mock(XmlDocumentLoader::class);
    $loader->shouldReceive('__invoke')->andReturn(false);

    $builder = new DpsBuilder(new \Pulsar\NfseNacional\Support\XsdValidator(__DIR__.'/../../../storage/schemes', xmlDocumentLoader: $loader));

    expect(fn () => $builder->buildAndValidate($data))
        ->toThrow(NfseException::class, 'falha ao carregar documento');
})->with('dpsData');

it('validates DPS with atvEvento (idAtvEvt) against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: new Servico(
            cServ: new CodigoServico(cTribNac: '010101', xDescServ: 'Serviço', cNBS: '123456789'),
            cLocPrestacao: '3501608',
            atvEvento: new AtividadeEvento(
                xNome: 'Festival',
                dtIni: '2026-01-01',
                dtFim: '2026-01-03',
                idAtvEvt: 'EVT001',
            ),
        ),
        valores: makeValoresMinimo(),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)->toContain('<atvEvento>');
});

it('validates DPS with atvEvento (end with CEP) against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: new Servico(
            cServ: new CodigoServico(cTribNac: '010101', xDescServ: 'Serviço', cNBS: '123456789'),
            cLocPrestacao: '3501608',
            atvEvento: new AtividadeEvento(
                xNome: 'Show',
                dtIni: '2026-02-01',
                dtFim: '2026-02-02',
                end: new EnderecoSimples(
                    xLgr: 'Rua Evento',
                    nro: '200',
                    xBairro: 'Centro',
                    CEP: '01001000',
                ),
            ),
        ),
        valores: makeValoresMinimo(),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)->toContain('<atvEvento>');
});

it('validates DPS with atvEvento (end with endExt) against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: new Servico(
            cServ: new CodigoServico(cTribNac: '010101', xDescServ: 'Serviço', cNBS: '123456789'),
            cLocPrestacao: '3501608',
            atvEvento: new AtividadeEvento(
                xNome: 'Conferencia Internacional',
                dtIni: '2026-03-01',
                dtFim: '2026-03-05',
                end: new EnderecoSimples(
                    xLgr: 'Broadway',
                    nro: '500',
                    xBairro: 'Midtown',
                    endExt: new \Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoExteriorObra(
                        cEndPost: '10036', xCidade: 'New York', xEstProvReg: 'NY',
                    ),
                ),
            ),
        ),
        valores: makeValoresMinimo(),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)
        ->toContain('<atvEvento>')
        ->toContain('<endExt>');
});

it('validates DPS with infoCompl against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: new Servico(
            cServ: new CodigoServico(cTribNac: '010101', xDescServ: 'Serviço', cNBS: '123456789'),
            cLocPrestacao: '3501608',
            infoCompl: new InfoComplementar(
                idDocTec: 'ART-123',
                docRef: 'Documento de referencia',
                xPed: '789',
                xItemPed: ['item1', 'item2'],
                xInfComp: 'Informacao complementar do servico',
            ),
        ),
        valores: makeValoresMinimo(),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)->toContain('<infoCompl>');
});
