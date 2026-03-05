<?php

covers(\Pulsar\NfseNacional\Xml\Builders\ServicoBuilder::class);

use Pulsar\NfseNacional\Dps\DTO\Servico\AtividadeEvento;
use Pulsar\NfseNacional\Dps\DTO\Servico\CodigoServico;
use Pulsar\NfseNacional\Dps\DTO\Servico\ComercioExterior;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoExteriorObra;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoObra;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoSimples;
use Pulsar\NfseNacional\Dps\DTO\Servico\InfoComplementar;
use Pulsar\NfseNacional\Dps\DTO\Servico\Obra;
use Pulsar\NfseNacional\Dps\DTO\Servico\Servico;
use Pulsar\NfseNacional\Dps\Enums\Servico\Mdic;
use Pulsar\NfseNacional\Dps\Enums\Servico\MdPrestacao;
use Pulsar\NfseNacional\Dps\Enums\Servico\MecAFComexP;
use Pulsar\NfseNacional\Dps\Enums\Servico\MecAFComexT;
use Pulsar\NfseNacional\Dps\Enums\Servico\MovTempBens;
use Pulsar\NfseNacional\Dps\Enums\Servico\VincPrest;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;
use Pulsar\NfseNacional\Xml\Builders\ServicoBuilder;

function makeServMinimo(): Servico
{
    return new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
    );
}

it('builds serv element with locPrest and cServ', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();

    $element = $builder->build($doc, $serv);
    $xml = $doc->saveXML($element);

    expect($xml)->toContain('<locPrest>');
    expect($xml)->toContain('<cLocPrestacao>3501608</cLocPrestacao>');
    expect($xml)->toContain('<cServ>');
    expect($xml)->toContain('<cTribNac>01.01.01.000</cTribNac>');
    expect($xml)->toContain('<xDescServ>Serviço X</xDescServ>');
    expect($xml)->toContain('<cNBS>123456789</cNBS>');
    expect($xml)->not->toContain('<obra>');
    expect($xml)->not->toContain('<comExt>');
    expect($xml)->not->toContain('<atvEvento>');
    expect($xml)->not->toContain('<infoCompl>');
});

it('omits cNBS when null', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X'),
        cLocPrestacao: '3501608',
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<cTribNac>01.01.01.000</cTribNac>')
        ->toContain('<xDescServ>')
        ->not->toContain('<cNBS>');
});

it('uses cPaisPrestacao when cLocPrestacao is not set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cPaisPrestacao: '01058',
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<cPaisPrestacao>01058</cPaisPrestacao>')
        ->not->toContain('<cLocPrestacao>');
});

it('throws when both cLocPrestacao and cPaisPrestacao are set', function () {
    expect(fn () => new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'X', cNBS: '1'),
        cLocPrestacao: '3501608',
        cPaisPrestacao: '01058',
    ))->toThrow(InvalidDpsArgument::class, '[infDPS/serv/locPrest] Somente 1 dos seguintes campos deve ser informado: código do local de prestação (cLocPrestacao), código do país de prestação (cPaisPrestacao). Informados: código do local de prestação (cLocPrestacao), código do país de prestação (cPaisPrestacao).');
});

it('throws when locPrest has no choice set', function () {
    expect(fn () => new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'X', cNBS: '1'),
    ))->toThrow(InvalidDpsArgument::class, '[infDPS/serv/locPrest] Somente 1 dos seguintes campos deve ser informado: código do local de prestação (cLocPrestacao), código do país de prestação (cPaisPrestacao). Nenhum foi informado.');
});

it('includes optional cServ fields when set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(
            cTribNac: '01.01.01.000',
            xDescServ: 'Serviço X',
            cNBS: '123456789',
            cTribMun: '01.01',
            cIntContrib: 'INT-001',
        ),
        cLocPrestacao: '3501608',
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<cTribMun>01.01</cTribMun>')
        ->toContain('<cNBS>123456789</cNBS>')
        ->toContain('<cIntContrib>INT-001</cIntContrib>');
});

it('builds comExt element with all fields', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        comExt: new ComercioExterior(
            mdPrestacao: MdPrestacao::Transfronteirico,
            vincPrest: VincPrest::SemVinculo,
            tpMoeda: '790',
            vServMoeda: '500.00',
            mecAFComexP: MecAFComexP::PROEXFinanciamento,
            mecAFComexT: MecAFComexT::PromocaoBrasilExterior,
            movTempBens: MovTempBens::Desconhecido,
            mdic: Mdic::NaoEnviar,
            nDI: '123456',
            nRE: '789012',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<comExt>')
        ->toContain('<mdPrestacao>1</mdPrestacao>')
        ->toContain('<vincPrest>0</vincPrest>')
        ->toContain('<tpMoeda>790</tpMoeda>')
        ->toContain('<vServMoeda>500.00</vServMoeda>')
        ->toContain('<mecAFComexP>08</mecAFComexP>')
        ->toContain('<mecAFComexT>13</mecAFComexT>')
        ->toContain('<movTempBens>0</movTempBens>')
        ->toContain('<nDI>123456</nDI>')
        ->toContain('<nRE>789012</nRE>')
        ->toContain('<mdic>0</mdic>');
});

it('builds comExt without optional nDI and nRE', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        comExt: new ComercioExterior(
            mdPrestacao: MdPrestacao::Transfronteirico,
            vincPrest: VincPrest::SemVinculo,
            tpMoeda: '790',
            vServMoeda: '500.00',
            mecAFComexP: MecAFComexP::PROEXFinanciamento,
            mecAFComexT: MecAFComexT::PromocaoBrasilExterior,
            movTempBens: MovTempBens::Desconhecido,
            mdic: Mdic::NaoEnviar,
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<comExt>')
        ->not->toContain('<nDI>')
        ->not->toContain('<nRE>');
});

it('builds obra with cObra choice', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        obra: new Obra(inscImobFisc: '12345', cObra: '67890'),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<obra>')
        ->toContain('<inscImobFisc>12345</inscImobFisc>')
        ->toContain('<cObra>67890</cObra>')
        ->not->toContain('<cCIB>')
        ->not->toContain('<end>');
});

it('builds obra with cCIB choice', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        obra: new Obra(cCIB: '11111'),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<obra>')
        ->toContain('<cCIB>11111</cCIB>')
        ->not->toContain('<cObra>')
        ->not->toContain('<end>');
});

it('builds obra with end choice using CEP', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        obra: new Obra(
            end: new EnderecoObra(
                xLgr: 'Rua Teste', nro: '100', xBairro: 'Centro',
                CEP: '01001000', xCpl: 'Sala 1',
            ),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<obra>')
        ->toContain('<end>')
        ->toContain('<CEP>01001000</CEP>')
        ->toContain('<xLgr>Rua Teste</xLgr>')
        ->toContain('<nro>100</nro>')
        ->toContain('<xCpl>Sala 1</xCpl>')
        ->toContain('<xBairro>Centro</xBairro>')
        ->not->toContain('<cMun>')
        ->not->toContain('<cObra>')
        ->not->toContain('<cCIB>');
});

it('builds obra with end choice using endExt', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        obra: new Obra(
            end: new EnderecoObra(
                xLgr: '5th Avenue', nro: '350', xBairro: 'Manhattan',
                endExt: new EnderecoExteriorObra(cEndPost: '10001', xCidade: 'New York', xEstProvReg: 'NY'),
            ),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<obra>')
        ->toContain('<end>')
        ->toContain('<endExt>')
        ->toContain('<cEndPost>10001</cEndPost>')
        ->toContain('<xCidade>New York</xCidade>')
        ->toContain('<xEstProvReg>NY</xEstProvReg>')
        ->toContain('<xLgr>5th Avenue</xLgr>')
        ->toContain('<nro>350</nro>')
        ->toContain('<xBairro>Manhattan</xBairro>')
        ->not->toContain('<CEP>');
});

it('builds obra without optional inscImobFisc', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        obra: new Obra(cObra: '67890'),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<obra>')
        ->toContain('<cObra>67890</cObra>')
        ->not->toContain('<inscImobFisc>');
});

it('throws when multiple obra choices are set', function () {
    expect(fn () => new Obra(cObra: '67890', cCIB: '11111'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('handles accented characters in field values', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(
            cTribNac: '01.01.01.000',
            xDescServ: 'Consultoria em gestão tributária',
            cNBS: '123456789',
        ),
        cLocPrestacao: '3501608',
    );

    $element = $builder->build($doc, $serv);
    $xml = $doc->saveXML($element);

    // Verify accented characters survive round-trip
    $reparsed = new DOMDocument;
    expect($reparsed->loadXML($xml))->toBeTrue();
    expect($reparsed->getElementsByTagName('xDescServ')->item(0)->textContent)
        ->toBe('Consultoria em gestão tributária');
});

it('builds atvEvento with idAtvEvt choice', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        atvEvento: new AtividadeEvento(
            xNome: 'Festival de Musica',
            dtIni: '2026-01-01',
            dtFim: '2026-01-03',
            idAtvEvt: 'EVT001',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<atvEvento>')
        ->toContain('<xNome>Festival de Musica</xNome>')
        ->toContain('<dtIni>2026-01-01</dtIni>')
        ->toContain('<dtFim>2026-01-03</dtFim>')
        ->toContain('<idAtvEvt>EVT001</idAtvEvt>')
        ->not->toContain('<end>');
});

it('builds atvEvento with end choice using CEP', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        atvEvento: new AtividadeEvento(
            xNome: 'Show',
            dtIni: '2026-02-01',
            dtFim: '2026-02-02',
            end: new EnderecoSimples(
                xLgr: 'Rua Evento',
                nro: '200',
                xBairro: 'Bairro Evento',
                CEP: '01001000',
                xCpl: 'Bloco A',
            ),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<atvEvento>')
        ->toContain('<end>')
        ->toContain('<CEP>01001000</CEP>')
        ->toContain('<xLgr>Rua Evento</xLgr>')
        ->toContain('<nro>200</nro>')
        ->toContain('<xCpl>Bloco A</xCpl>')
        ->toContain('<xBairro>Bairro Evento</xBairro>')
        ->not->toContain('<idAtvEvt>');
});

it('builds atvEvento with end choice using endExt', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        atvEvento: new AtividadeEvento(
            xNome: 'Conferencia',
            dtIni: '2026-03-01',
            dtFim: '2026-03-05',
            end: new EnderecoSimples(
                xLgr: 'Broadway',
                nro: '500',
                xBairro: 'Midtown',
                endExt: new EnderecoExteriorObra(cEndPost: '10036', xCidade: 'New York', xEstProvReg: 'NY'),
            ),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<atvEvento>')
        ->toContain('<end>')
        ->toContain('<endExt>')
        ->toContain('<cEndPost>10036</cEndPost>')
        ->toContain('<xCidade>New York</xCidade>')
        ->toContain('<xEstProvReg>NY</xEstProvReg>')
        ->toContain('<xLgr>Broadway</xLgr>')
        ->toContain('<nro>500</nro>')
        ->toContain('<xBairro>Midtown</xBairro>')
        ->not->toContain('<CEP>')
        ->not->toContain('<idAtvEvt>');
});

it('builds infoCompl with all fields', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        infoCompl: new InfoComplementar(
            idDocTec: 'ART-123',
            docRef: 'REF-456',
            xPed: '789',
            xItemPed: ['item1', 'item2'],
            xInfComp: 'Informacao complementar',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<infoCompl>')
        ->toContain('<idDocTec>ART-123</idDocTec>')
        ->toContain('<docRef>REF-456</docRef>')
        ->toContain('<xPed>789</xPed>')
        ->toContain('<gItemPed>')
        ->toContain('<xItemPed>item1</xItemPed>')
        ->toContain('<xItemPed>item2</xItemPed>')
        ->toContain('<xInfComp>Informacao complementar</xInfComp>');
});

it('builds infoCompl with only xItemPed array', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'Serviço X', cNBS: '123456789'),
        cLocPrestacao: '3501608',
        infoCompl: new InfoComplementar(
            xItemPed: ['pedido1', 'pedido2', 'pedido3'],
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<infoCompl>')
        ->toContain('<gItemPed>')
        ->toContain('<xItemPed>pedido1</xItemPed>')
        ->toContain('<xItemPed>pedido2</xItemPed>')
        ->toContain('<xItemPed>pedido3</xItemPed>')
        ->not->toContain('<idDocTec>')
        ->not->toContain('<docRef>')
        ->not->toContain('<xPed>')
        ->not->toContain('<xInfComp>');
});
