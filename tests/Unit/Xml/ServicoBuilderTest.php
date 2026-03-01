<?php

use Pulsar\NfseNacional\Xml\Builders\ServicoBuilder;

function makeServMinimo(): stdClass
{
    $serv = new stdClass;
    $serv->locPrest = new stdClass;
    $serv->locPrest->cLocPrestacao = '3501608';
    $serv->cServ = new stdClass;
    $serv->cServ->cTribNac = '01.01.01.000';
    $serv->cServ->xDescServ = 'Serviço X';
    $serv->cServ->cNBS = '123456789';

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
    $serv->locPrest = new stdClass;
    $serv->locPrest->cPaisPrestacao = '01058';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<cPaisPrestacao>01058</cPaisPrestacao>')
        ->not->toContain('<cLocPrestacao>');
});

it('throws when both cLocPrestacao and cPaisPrestacao are set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->locPrest->cLocPrestacao = '3501608';
    $serv->locPrest->cPaisPrestacao = '01058';

    expect(fn () => $builder->build($doc, $serv))
        ->toThrow(InvalidArgumentException::class, 'não ambos');
});

it('throws when locPrest has no choice set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->locPrest = new stdClass;

    expect(fn () => $builder->build($doc, $serv))
        ->toThrow(InvalidArgumentException::class, 'requer cLocPrestacao');
});

it('includes optional cServ fields when set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->cServ->cTribMun = '01.01';
    $serv->cServ->cIntContrib = 'INT-001';

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
    $serv->comExt = new stdClass;
    $serv->comExt->mdPrestacao = '1';
    $serv->comExt->vincPrest = '0';
    $serv->comExt->tpMoeda = '790';
    $serv->comExt->vServMoeda = '500.00';
    $serv->comExt->mecAFComexP = '13';
    $serv->comExt->mecAFComexT = '13';
    $serv->comExt->movTempBens = '0';
    $serv->comExt->nDI = '123456';
    $serv->comExt->nRE = '789012';
    $serv->comExt->mdic = '0';

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
    $serv->comExt = new stdClass;
    $serv->comExt->mdPrestacao = '1';
    $serv->comExt->vincPrest = '0';
    $serv->comExt->tpMoeda = '790';
    $serv->comExt->vServMoeda = '500.00';
    $serv->comExt->mecAFComexP = '13';
    $serv->comExt->mecAFComexT = '13';
    $serv->comExt->movTempBens = '0';
    $serv->comExt->mdic = '0';

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
    $serv->obra->inscImobFisc = '12345';
    $serv->obra->cObra = '67890';

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
    $serv->obra->cCIB = '11111';

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
    $serv->obra->end->CEP = '01001000';
    $serv->obra->end->xLgr = 'Rua Teste';
    $serv->obra->end->nro = '100';
    $serv->obra->end->xCpl = 'Sala 1';
    $serv->obra->end->xBairro = 'Centro';

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
    $serv->obra->end->endExt = new stdClass;
    $serv->obra->end->endExt->cEndPost = '10001';
    $serv->obra->end->endExt->xCidade = 'New York';
    $serv->obra->end->endExt->xEstProvReg = 'NY';
    $serv->obra->end->xLgr = '5th Avenue';
    $serv->obra->end->nro = '350';
    $serv->obra->end->xBairro = 'Manhattan';

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
    $serv->obra->cObra = '67890';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<obra>')
        ->toContain('<cObra>67890</cObra>')
        ->not->toContain('<inscImobFisc>');
});

it('throws when multiple obra choices are set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->obra = new stdClass;
    $serv->obra->cObra = '67890';
    $serv->obra->cCIB = '11111';

    expect(fn () => $builder->build($doc, $serv))
        ->toThrow(InvalidArgumentException::class, 'apenas um');
});

it('handles accented characters in field values', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = makeServMinimo();
    $serv->cServ->xDescServ = 'Consultoria em gestão tributária';

    $element = $builder->build($doc, $serv);
    $xml = $doc->saveXML($element);

    // Verify accented characters survive round-trip
    $reparsed = new DOMDocument;
    expect($reparsed->loadXML($xml))->toBeTrue();
    expect($reparsed->getElementsByTagName('xDescServ')->item(0)->textContent)
        ->toBe('Consultoria em gestão tributária');
});
