<?php

use Pulsar\NfseNacional\Xml\Builders\ServicoBuilder;

it('builds serv element with locPrest and cServ', function () {
    $builder = new ServicoBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $serv     = new stdClass();
    $locPrest = new stdClass();
    $locPrest->clocprestacao = '3501608';
    $serv->locprest = $locPrest;

    $cServ = new stdClass();
    $cServ->ctribnac  = '01.01.01.000';
    $cServ->xdescserv = 'Serviço X';
    $serv->cserv = $cServ;

    $element = $builder->build($doc, $serv);
    $xml     = $doc->saveXML($element);

    expect($xml)->toContain('<locPrest>');
    expect($xml)->toContain('<cLocPrestacao>3501608</cLocPrestacao>');
    expect($xml)->toContain('<cServ>');
    expect($xml)->toContain('<cTribNac>01.01.01.000</cTribNac>');
    expect($xml)->toContain('<xDescServ>Serviço X</xDescServ>');
});
