<?php

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\Subst;
use OwnerPro\Nfsen\Dps\DTO\Serv\AtvEvento;
use OwnerPro\Nfsen\Dps\DTO\Serv\CServ;
use OwnerPro\Nfsen\Dps\DTO\Serv\EndExt;
use OwnerPro\Nfsen\Dps\DTO\Serv\EndSimples;
use OwnerPro\Nfsen\Dps\DTO\Serv\InfoCompl;
use OwnerPro\Nfsen\Dps\DTO\Serv\Serv;
use OwnerPro\Nfsen\Dps\DTO\Toma\Toma;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Support\XmlDocumentLoader;
use OwnerPro\Nfsen\Support\XsdValidator;
use OwnerPro\Nfsen\Xml\DpsBuilder;

covers(DpsBuilder::class);

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

    $builder = new DpsBuilder(new XsdValidator(__DIR__.'/../../../storage/schemes', xmlDocumentLoader: $loader));

    expect(fn () => $builder->buildAndValidate($data))
        ->toThrow(NfseException::class, 'falha ao carregar documento');
})->with('dpsData');

it('validates DPS with atvEvento (idAtvEvt) against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: new Serv(
            cServ: new CServ(cTribNac: '010101', xDescServ: 'Serviço', cNBS: '123456789'),
            cLocPrestacao: '3501608',
            atvEvento: new AtvEvento(
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
        serv: new Serv(
            cServ: new CServ(cTribNac: '010101', xDescServ: 'Serviço', cNBS: '123456789'),
            cLocPrestacao: '3501608',
            atvEvento: new AtvEvento(
                xNome: 'Show',
                dtIni: '2026-02-01',
                dtFim: '2026-02-02',
                end: new EndSimples(
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
        serv: new Serv(
            cServ: new CServ(cTribNac: '010101', xDescServ: 'Serviço', cNBS: '123456789'),
            cLocPrestacao: '3501608',
            atvEvento: new AtvEvento(
                xNome: 'Conferencia Internacional',
                dtIni: '2026-03-01',
                dtFim: '2026-03-05',
                end: new EndSimples(
                    xLgr: 'Broadway',
                    nro: '500',
                    xBairro: 'Midtown',
                    endExt: new EndExt(
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
        serv: new Serv(
            cServ: new CServ(cTribNac: '010101', xDescServ: 'Serviço', cNBS: '123456789'),
            cLocPrestacao: '3501608',
            infoCompl: new InfoCompl(
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

function makeDpsForXsd(
    ?string $prestadorCnpj = null,
    ?Toma $toma = null,
    ?Toma $interm = null,
    ?Subst $subst = null,
    ?string $serie = null,
    ?string $xDescServ = null,
): DpsData {
    return new DpsData(
        infDPS: makeInfDps(serie: $serie),
        subst: $subst,
        prest: makePrestadorCnpj(CNPJ: $prestadorCnpj),
        toma: $toma,
        interm: $interm,
        serv: new Serv(
            cServ: new CServ(cTribNac: '010101', xDescServ: $xDescServ ?? 'Serviço', cNBS: '123456789'),
            cLocPrestacao: '3501608',
        ),
        valores: makeValoresMinimo(),
    );
}

it('validates a DPS whose prestador carries an alphanumeric CNPJ', function () {
    $xml = (new DpsBuilder(makeXsdValidator()))->buildAndValidate(makeDpsForXsd(prestadorCnpj: makeAlphanumericCnpj()));

    expect($xml)
        ->toContain('Id="DPS3501608212ABC34501DE3500001000000000000001"')
        ->toContain('<prest><CNPJ>12ABC34501DE35</CNPJ>');
});

it('validates a DPS whose tomador and intermediário carry an alphanumeric CNPJ', function () {
    $xml = (new DpsBuilder(makeXsdValidator()))->buildAndValidate(makeDpsForXsd(
        toma: new Toma(CNPJ: makeAlphanumericCnpj(), xNome: 'Tomador Ltda'),
        interm: new Toma(CNPJ: makeAlphanumericCnpj(), xNome: 'Intermediário Ltda'),
    ));

    expect($xml)
        ->toContain('<toma><CNPJ>12ABC34501DE35</CNPJ>')
        ->toContain('<interm><CNPJ>12ABC34501DE35</CNPJ>');
});

it('refuses a lowercase alphanumeric CNPJ of the tomador', function () {
    $builder = new DpsBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate(makeDpsForXsd(toma: new Toma(CNPJ: '12abc34501de35', xNome: 'Tomador Ltda'))))
        ->toThrow(NfseException::class, 'XML inválido');
});

it('validates a substituição whose chSubstda carries an alphanumeric CNPJ', function () {
    // O TSChaveNFSe oficial só admite letras nas posições 7 a 20; a inscrição ocupa de 10 a 23.
    $xml = (new DpsBuilder(makeXsdValidator()))->buildAndValidate(makeDpsForXsd(subst: new Subst(
        chSubstda: makeAlphanumericChaveAcesso(),
        cMotivo: CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
    )));

    expect($xml)->toContain('<chSubstda>'.makeAlphanumericChaveAcesso().'</chSubstda>');
});

it('refuses a chSubstda with a letter outside the inscrição federal', function (string $chave) {
    $builder = new DpsBuilder(makeXsdValidator());
    $subst = new Subst(chSubstda: $chave, cMotivo: CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional);

    expect(fn () => $builder->buildAndValidate(makeDpsForXsd(subst: $subst)))
        ->toThrow(NfseException::class, 'XML inválido');
})->with([
    'letra na posição 9' => ['33033021A'.'12345678000195'.'000000000000126010000010000'],
    'letra na posição 24' => ['330330212'.'12345678000195'.'A00000000000126010000010000'],
]);

it('accepts the DPS series the national schema allows', function (string $serie) {
    $xml = (new DpsBuilder(makeXsdValidator()))->buildAndValidate(makeDpsForXsd(serie: $serie));

    expect($xml)->toContain('<serie>'.$serie.'</serie>');
})->with(['1', '9999', '00001', '89999']);

it('refuses a DPS series outside the range of TSSerieDPS', function (string $serie) {
    $builder = new DpsBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate(makeDpsForXsd(serie: $serie)))
        ->toThrow(NfseException::class, "Element '{http://www.sped.fazenda.gov.br/nfse}serie'");
})->with(['90000', '99999']);

it('refuses a service description made only of whitespace', function () {
    $builder = new DpsBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate(makeDpsForXsd(xDescServ: '   ')))
        ->toThrow(NfseException::class, "Element '{http://www.sped.fazenda.gov.br/nfse}xDescServ'");
});
