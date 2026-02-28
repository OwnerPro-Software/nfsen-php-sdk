<?php

use Pulsar\NfseNacional\Xml\Builders\TomadorBuilder;

it('builds toma element with CNPJ', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->cnpj = '98765432000111';
    $toma->xnome = 'Tomador Ltda';

    $element = $builder->build($doc, $toma);

    expect($doc->saveXML($element))->toContain('<CNPJ>98765432000111</CNPJ>');
    expect($doc->saveXML($element))->toContain('<xNome>Tomador Ltda</xNome>');
});

it('builds toma element with CPF', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->cpf = '12345678901';
    $toma->xnome = 'Pessoa Física';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<CPF>12345678901</CPF>')
        ->not->toContain('<CNPJ>');
});

it('includes NIF and cNaoNIF when set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->nif = 'NIF12345';
    $toma->cnaonif = '1';
    $toma->xnome = 'Estrangeiro';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<NIF>NIF12345</NIF>')
        ->toContain('<cNaoNIF>1</cNaoNIF>');
});

it('includes CAEPF and IM when set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->cpf = '12345678901';
    $toma->caepf = '12345678901234';
    $toma->im = '1234567';
    $toma->xnome = 'Pessoa';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<CAEPF>12345678901234</CAEPF>')
        ->toContain('<IM>1234567</IM>');
});

it('builds endNac address block', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->cnpj = '98765432000111';
    $toma->xnome = 'Tomador';

    $toma->end = new stdClass;
    $toma->end->endnac = new stdClass;
    $toma->end->endnac->cmun = '3501608';
    $toma->end->endnac->cep = '01001000';
    $toma->end->xlgr = 'Rua Teste';
    $toma->end->nro = '100';
    $toma->end->xbairro = 'Centro';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<endNac>')
        ->toContain('<cMun>3501608</cMun>')
        ->toContain('<CEP>01001000</CEP>')
        ->toContain('<xLgr>Rua Teste</xLgr>')
        ->toContain('<nro>100</nro>')
        ->toContain('<xBairro>Centro</xBairro>')
        ->not->toContain('<endExt>');
});

it('builds endExt address block', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->cnpj = '98765432000111';
    $toma->xnome = 'Tomador';

    $toma->end = new stdClass;
    $toma->end->endext = new stdClass;
    $toma->end->endext->cpais = '01058';
    $toma->end->endext->cendpost = '10001';
    $toma->end->endext->xcidade = 'New York';
    $toma->end->endext->xestprovreg = 'NY';
    $toma->end->xlgr = '5th Avenue';
    $toma->end->nro = '200';
    $toma->end->xbairro = 'Manhattan';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<endExt>')
        ->toContain('<cPais>01058</cPais>')
        ->toContain('<cEndPost>10001</cEndPost>')
        ->toContain('<xCidade>New York</xCidade>')
        ->toContain('<xEstProvReg>NY</xEstProvReg>')
        ->not->toContain('<endNac>');
});

it('includes xCpl in address when set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->cnpj = '98765432000111';
    $toma->xnome = 'Tomador';

    $toma->end = new stdClass;
    $toma->end->endnac = new stdClass;
    $toma->end->endnac->cmun = '3501608';
    $toma->end->endnac->cep = '01001000';
    $toma->end->xlgr = 'Rua Teste';
    $toma->end->nro = '100';
    $toma->end->xcpl = 'Sala 5';
    $toma->end->xbairro = 'Centro';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)->toContain('<xCpl>Sala 5</xCpl>');
});

it('includes fone and email when set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->cnpj = '98765432000111';
    $toma->xnome = 'Tomador';
    $toma->fone = '11999998888';
    $toma->email = 'tomador@test.com';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<fone>11999998888</fone>')
        ->toContain('<email>tomador@test.com</email>');
});
