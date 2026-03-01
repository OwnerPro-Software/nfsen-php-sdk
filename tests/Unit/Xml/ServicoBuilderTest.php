<?php

use Pulsar\NfseNacional\DTOs\Dps\Servico\CodigoServico;
use Pulsar\NfseNacional\DTOs\Dps\Servico\ComercioExterior;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoExteriorObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Obra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Servico;
use Pulsar\NfseNacional\Enums\Dps\Servico\MDIC;
use Pulsar\NfseNacional\Enums\Dps\Servico\MecAFComexP;
use Pulsar\NfseNacional\Enums\Dps\Servico\MecAFComexT;
use Pulsar\NfseNacional\Enums\Dps\Servico\ModoPrestacao;
use Pulsar\NfseNacional\Enums\Dps\Servico\MovTempBens;
use Pulsar\NfseNacional\Enums\Dps\Servico\VinculoPrestacao;
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
    ))->toThrow(InvalidDpsArgument::class, 'exatamente um');
});

it('throws when locPrest has no choice set', function () {
    expect(fn () => new Servico(
        cServ: new CodigoServico(cTribNac: '01.01.01.000', xDescServ: 'X', cNBS: '1'),
    ))->toThrow(InvalidDpsArgument::class, 'exatamente um');
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
            mdPrestacao: ModoPrestacao::Transfronteirico,
            vincPrest: VinculoPrestacao::SemVinculo,
            tpMoeda: '790',
            vServMoeda: '500.00',
            mecAFComexP: MecAFComexP::PROEXFinanciamento,
            mecAFComexT: MecAFComexT::PromocaoBrasilExterior,
            movTempBens: MovTempBens::Desconhecido,
            mdic: MDIC::NaoEnviar,
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
            mdPrestacao: ModoPrestacao::Transfronteirico,
            vincPrest: VinculoPrestacao::SemVinculo,
            tpMoeda: '790',
            vServMoeda: '500.00',
            mecAFComexP: MecAFComexP::PROEXFinanciamento,
            mecAFComexT: MecAFComexT::PromocaoBrasilExterior,
            movTempBens: MovTempBens::Desconhecido,
            mdic: MDIC::NaoEnviar,
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
        ->toThrow(InvalidDpsArgument::class, 'exatamente um');
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
