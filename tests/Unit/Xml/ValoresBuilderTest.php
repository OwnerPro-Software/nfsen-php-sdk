<?php

use Pulsar\NfseNacional\Xml\Builders\ValoresBuilder;

it('builds valores element with vServPrest', function () {
    $builder = new ValoresBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass();
    $valores->vservprest = new stdClass();
    $valores->vservprest->vserv  = '100.00';

    $valores->trib              = new stdClass();
    $valores->trib->tribmun     = new stdClass();
    $valores->trib->tribmun->tribissqn  = '1';
    $valores->trib->tribmun->tpretissqn = '1';
    $valores->trib->totaltrib            = new stdClass();
    $valores->trib->totaltrib->indtottrib = '0';

    $element = $builder->build($doc, $valores);
    $xml     = $doc->saveXML($element);

    expect($xml)->toContain('<valores>');
    expect($xml)->toContain('<vServPrest>');
    expect($xml)->toContain('<vServ>100.00</vServ>');
    expect($xml)->toContain('<tribMun>');
    expect($xml)->toContain('<tribISSQN>1</tribISSQN>');
    expect($xml)->toContain('<tpRetISSQN>1</tpRetISSQN>');
    expect($xml)->toContain('<totTrib>');
    expect($xml)->toContain('<indTotTrib>0</indTotTrib>');
});
