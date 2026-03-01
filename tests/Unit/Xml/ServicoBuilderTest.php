<?php

use Pulsar\NfseNacional\Xml\Builders\ServicoBuilder;

function makeServMinimo(): stdClass
{
    $serv = new stdClass;
    $serv->locprest = new stdClass;
    $serv->locprest->clocprestacao = '3501608';
    $serv->cserv = new stdClass;
    $serv->cserv->ctribnac = '01.01.01.000';
    $serv->cserv->xdescserv = 'Serviço X';
    $serv->cserv->cnbs = '123456789';

    return $serv;
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

    $serv = makeServMinimo();
    $serv->locprest = new stdClass;
    $serv->locprest->cpaisprestacao = '01058';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<cPaisPrestacao>01058</cPaisPrestacao>')
        ->not->toContain('<cLocPrestacao>');
});

it('uses cLocPrestacao over cPaisPrestacao when both are set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->locprest->clocprestacao = '3501608';
    $serv->locprest->cpaisprestacao = '01058';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<cLocPrestacao>3501608</cLocPrestacao>')
        ->not->toContain('<cPaisPrestacao>');
});

it('includes optional cServ fields when set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->cserv->ctribmun = '01.01';
    $serv->cserv->cintcontrib = 'INT-001';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<cTribMun>01.01</cTribMun>')
        ->toContain('<cNBS>123456789</cNBS>')
        ->toContain('<cIntContrib>INT-001</cIntContrib>');
});

it('builds comExt element with all fields', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->comext = new stdClass;
    $serv->comext->mdprestacao = '1';
    $serv->comext->vincprest = '0';
    $serv->comext->tpmoeda = '790';
    $serv->comext->vservmoeda = '500.00';
    $serv->comext->mecafcomexp = '13';
    $serv->comext->mecafcomext = '13';
    $serv->comext->movtempbens = '0';
    $serv->comext->ndi = '123456';
    $serv->comext->nre = '789012';
    $serv->comext->mdic = '0';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<comExt>')
        ->toContain('<mdPrestacao>1</mdPrestacao>')
        ->toContain('<vincPrest>0</vincPrest>')
        ->toContain('<tpMoeda>790</tpMoeda>')
        ->toContain('<vServMoeda>500.00</vServMoeda>')
        ->toContain('<mecAFComexP>13</mecAFComexP>')
        ->toContain('<mecAFComexT>13</mecAFComexT>')
        ->toContain('<movTempBens>0</movTempBens>')
        ->toContain('<nDI>123456</nDI>')
        ->toContain('<nRE>789012</nRE>')
        ->toContain('<mdic>0</mdic>');
});

it('builds comExt without optional nDI and nRE', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->comext = new stdClass;
    $serv->comext->mdprestacao = '1';
    $serv->comext->vincprest = '0';
    $serv->comext->tpmoeda = '790';
    $serv->comext->vservmoeda = '500.00';
    $serv->comext->mecafcomexp = '13';
    $serv->comext->mecafcomext = '13';
    $serv->comext->movtempbens = '0';
    $serv->comext->mdic = '0';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<comExt>')
        ->not->toContain('<nDI>')
        ->not->toContain('<nRE>');
});

it('builds obra with cObra choice', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->obra = new stdClass;
    $serv->obra->inscimobfisc = '12345';
    $serv->obra->cobra = '67890';

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

    $serv = makeServMinimo();
    $serv->obra = new stdClass;
    $serv->obra->ccib = '11111';

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

    $serv = makeServMinimo();
    $serv->obra = new stdClass;
    $serv->obra->end = new stdClass;
    $serv->obra->end->cep = '01001000';
    $serv->obra->end->xlgr = 'Rua Teste';
    $serv->obra->end->nro = '100';
    $serv->obra->end->xcpl = 'Sala 1';
    $serv->obra->end->xbairro = 'Centro';

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

    $serv = makeServMinimo();
    $serv->obra = new stdClass;
    $serv->obra->end = new stdClass;
    $serv->obra->end->endext = new stdClass;
    $serv->obra->end->endext->cendpost = '10001';
    $serv->obra->end->endext->xcidade = 'New York';
    $serv->obra->end->endext->xestprovreg = 'NY';
    $serv->obra->end->xlgr = '5th Avenue';
    $serv->obra->end->nro = '350';
    $serv->obra->end->xbairro = 'Manhattan';

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

    $serv = makeServMinimo();
    $serv->obra = new stdClass;
    $serv->obra->cobra = '67890';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<obra>')
        ->toContain('<cObra>67890</cObra>')
        ->not->toContain('<inscImobFisc>');
});

it('prefers cObra over cCIB and end when multiple are set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->obra = new stdClass;
    $serv->obra->cobra = '67890';
    $serv->obra->ccib = '11111';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<cObra>67890</cObra>')
        ->not->toContain('<cCIB>');
});

it('handles accented characters in field values', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->cserv->xdescserv = 'Consultoria em gestão tributária';

    $element = $builder->build($doc, $serv);
    $xml = $doc->saveXML($element);

    // Verify accented characters survive round-trip
    $reparsed = new DOMDocument;
    expect($reparsed->loadXML($xml))->toBeTrue();
    expect($reparsed->getElementsByTagName('xDescServ')->item(0)->textContent)
        ->toBe('Consultoria em gestão tributária');
});
