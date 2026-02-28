<?php

use Pulsar\NfseNacional\Xml\Builders\ServicoBuilder;

it('builds serv element with locPrest and cServ', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new stdClass;
    $locPrest = new stdClass;
    $locPrest->clocprestacao = '3501608';
    $serv->locprest = $locPrest;

    $cServ = new stdClass;
    $cServ->ctribnac = '01.01.01.000';
    $cServ->xdescserv = 'Serviço X';
    $serv->cserv = $cServ;

    $element = $builder->build($doc, $serv);
    $xml = $doc->saveXML($element);

    expect($xml)->toContain('<locPrest>');
    expect($xml)->toContain('<cLocPrestacao>3501608</cLocPrestacao>');
    expect($xml)->toContain('<cServ>');
    expect($xml)->toContain('<cTribNac>01.01.01.000</cTribNac>');
    expect($xml)->toContain('<xDescServ>Serviço X</xDescServ>');
});

it('includes cPaisPrestacao when set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new stdClass;
    $serv->locprest = new stdClass;
    $serv->locprest->clocprestacao = '3501608';
    $serv->locprest->cpaisprestacao = '01058';

    $serv->cserv = new stdClass;
    $serv->cserv->ctribnac = '01.01.01.000';
    $serv->cserv->xdescserv = 'Serviço X';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)->toContain('<cPaisPrestacao>01058</cPaisPrestacao>');
});

it('includes optional cServ fields when set', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new stdClass;
    $serv->locprest = new stdClass;
    $serv->locprest->clocprestacao = '3501608';

    $serv->cserv = new stdClass;
    $serv->cserv->ctribnac = '01.01.01.000';
    $serv->cserv->ctribmun = '01.01';
    $serv->cserv->xdescserv = 'Serviço X';
    $serv->cserv->cnbs = '123456789';
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

    $serv = new stdClass;
    $serv->locprest = new stdClass;
    $serv->locprest->clocprestacao = '3501608';

    $serv->cserv = new stdClass;
    $serv->cserv->ctribnac = '01.01.01.000';
    $serv->cserv->xdescserv = 'Serviço X';

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

    $serv = new stdClass;
    $serv->locprest = new stdClass;
    $serv->locprest->clocprestacao = '3501608';

    $serv->cserv = new stdClass;
    $serv->cserv->ctribnac = '01.01.01.000';
    $serv->cserv->xdescserv = 'Serviço X';

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

it('builds obra element with all fields', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new stdClass;
    $serv->locprest = new stdClass;
    $serv->locprest->clocprestacao = '3501608';

    $serv->cserv = new stdClass;
    $serv->cserv->ctribnac = '01.01.01.000';
    $serv->cserv->xdescserv = 'Serviço X';

    $serv->obra = new stdClass;
    $serv->obra->inscimobfisc = '12345';
    $serv->obra->cobra = '67890';
    $serv->obra->ccib = '11111';
    $serv->obra->end = new stdClass;
    $serv->obra->end->cep = '01001000';
    $serv->obra->end->cmun = '3501608';
    $serv->obra->end->xlgr = 'Rua Teste';
    $serv->obra->end->nro = '100';
    $serv->obra->end->xcpl = 'Sala 1';
    $serv->obra->end->xbairro = 'Centro';

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<obra>')
        ->toContain('<inscImobFisc>12345</inscImobFisc>')
        ->toContain('<cObra>67890</cObra>')
        ->toContain('<cCIB>11111</cCIB>')
        ->toContain('<end>')
        ->toContain('<CEP>01001000</CEP>')
        ->toContain('<cMun>3501608</cMun>')
        ->toContain('<xLgr>Rua Teste</xLgr>')
        ->toContain('<nro>100</nro>')
        ->toContain('<xCpl>Sala 1</xCpl>')
        ->toContain('<xBairro>Centro</xBairro>');
});

it('builds obra element without optional fields', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new stdClass;
    $serv->locprest = new stdClass;
    $serv->locprest->clocprestacao = '3501608';

    $serv->cserv = new stdClass;
    $serv->cserv->ctribnac = '01.01.01.000';
    $serv->cserv->xdescserv = 'Serviço X';

    $serv->obra = new stdClass;

    $xml = $doc->saveXML($builder->build($doc, $serv));

    expect($xml)
        ->toContain('<obra/>')
        ->not->toContain('<inscImobFisc>')
        ->not->toContain('<cObra>')
        ->not->toContain('<cCIB>')
        ->not->toContain('<end>');
});

it('handles accented characters in field values', function () {
    $builder = new ServicoBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $serv = new stdClass;
    $serv->locprest = new stdClass;
    $serv->locprest->clocprestacao = '3501608';

    $serv->cserv = new stdClass;
    $serv->cserv->ctribnac = '01.01.01.000';
    $serv->cserv->xdescserv = 'Consultoria em gestão tributária';

    $element = $builder->build($doc, $serv);
    $xml = $doc->saveXML($element);

    // Verify accented characters survive round-trip
    $reparsed = new DOMDocument;
    expect($reparsed->loadXML($xml))->toBeTrue();
    expect($reparsed->getElementsByTagName('xDescServ')->item(0)->textContent)
        ->toBe('Consultoria em gestão tributária');
});
