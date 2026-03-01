<?php

use Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder;

it('builds prest element with CNPJ', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cnpj = '12345678000195';
    $prest->xnome = 'Empresa Teste';
    $regTrib = new stdClass;
    $regTrib->opsimpnac = 1;
    $regTrib->regesptrib = 0;
    $prest->regtrib = $regTrib;

    $element = $builder->build($doc, $prest);
    $doc->appendChild($element);

    expect($doc->saveXML($element))->toContain('<CNPJ>12345678000195</CNPJ>');
    expect($doc->saveXML($element))->toContain('<xNome>Empresa Teste</xNome>');
    expect($doc->saveXML($element))->toContain('<regTrib>');
    expect($doc->saveXML($element))->toContain('<opSimpNac>1</opSimpNac>');
});

it('builds prest element with CPF when no CNPJ', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cpf = '12345678901';
    $prest->xnome = 'Pessoa Física';
    $regTrib = new stdClass;
    $regTrib->opsimpnac = 0;
    $regTrib->regesptrib = 0;
    $prest->regtrib = $regTrib;

    $element = $builder->build($doc, $prest);

    expect($doc->saveXML($element))->toContain('<CPF>12345678901</CPF>');
});

it('includes CAEPF and IM when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cnpj = '12345678000195';
    $prest->caepf = '12345678901234';
    $prest->im = '9876543';
    $prest->xnome = 'Empresa';
    $prest->regtrib = new stdClass;
    $prest->regtrib->opsimpnac = 1;
    $prest->regtrib->regesptrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<CAEPF>12345678901234</CAEPF>')
        ->toContain('<IM>9876543</IM>');
});

it('builds prest element with NIF', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->nif = 'NIF999';
    $prest->xnome = 'Empresa Estrangeira';
    $prest->regtrib = new stdClass;
    $prest->regtrib->opsimpnac = 0;
    $prest->regtrib->regesptrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<NIF>NIF999</NIF>')
        ->not->toContain('<CNPJ>')
        ->not->toContain('<CPF>');
});

it('builds prest element with cNaoNIF', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cnaonif = '1';
    $prest->xnome = 'Empresa Estrangeira';
    $prest->regtrib = new stdClass;
    $prest->regtrib->opsimpnac = 0;
    $prest->regtrib->regesptrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<cNaoNIF>1</cNaoNIF>')
        ->not->toContain('<CNPJ>')
        ->not->toContain('<CPF>')
        ->not->toContain('<NIF>');
});

it('uses CNPJ over CPF when both are set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cnpj = '12345678000195';
    $prest->cpf = '12345678901';
    $prest->xnome = 'Empresa';
    $prest->regtrib = new stdClass;
    $prest->regtrib->opsimpnac = 1;
    $prest->regtrib->regesptrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<CNPJ>12345678000195</CNPJ>')
        ->not->toContain('<CPF>');
});

it('includes fone and email when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cnpj = '12345678000195';
    $prest->xnome = 'Empresa';
    $prest->fone = '11999998888';
    $prest->email = 'empresa@test.com';
    $prest->regtrib = new stdClass;
    $prest->regtrib->opsimpnac = 1;
    $prest->regtrib->regesptrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<fone>11999998888</fone>')
        ->toContain('<email>empresa@test.com</email>');
});

it('includes regApTribSN when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cnpj = '12345678000195';
    $prest->xnome = 'Empresa';
    $prest->regtrib = new stdClass;
    $prest->regtrib->opsimpnac = 1;
    $prest->regtrib->regaptribsn = 2;
    $prest->regtrib->regesptrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)->toContain('<regApTribSN>2</regApTribSN>');
});

it('builds endNac address block', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cnpj = '12345678000195';
    $prest->xnome = 'Empresa';
    $prest->regtrib = new stdClass;
    $prest->regtrib->opsimpnac = 1;
    $prest->regtrib->regesptrib = 0;

    $prest->end = new stdClass;
    $prest->end->endnac = new stdClass;
    $prest->end->endnac->cmun = '3501608';
    $prest->end->endnac->cep = '01001000';
    $prest->end->xlgr = 'Rua Teste';
    $prest->end->nro = '100';
    $prest->end->xbairro = 'Centro';

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<endNac>')
        ->toContain('<cMun>3501608</cMun>')
        ->toContain('<CEP>01001000</CEP>')
        ->toContain('<xLgr>Rua Teste</xLgr>')
        ->toContain('<nro>100</nro>')
        ->toContain('<xBairro>Centro</xBairro>');
});

it('builds endExt address block', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cnpj = '12345678000195';
    $prest->xnome = 'Empresa';
    $prest->regtrib = new stdClass;
    $prest->regtrib->opsimpnac = 1;
    $prest->regtrib->regesptrib = 0;

    $prest->end = new stdClass;
    $prest->end->endext = new stdClass;
    $prest->end->endext->cpais = '01058';
    $prest->end->endext->cendpost = '10001';
    $prest->end->endext->xcidade = 'New York';
    $prest->end->endext->xestprovreg = 'NY';
    $prest->end->xlgr = '5th Avenue';
    $prest->end->nro = '200';
    $prest->end->xbairro = 'Manhattan';

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<endExt>')
        ->toContain('<cPais>01058</cPais>')
        ->toContain('<cEndPost>10001</cEndPost>')
        ->toContain('<xCidade>New York</xCidade>')
        ->toContain('<xEstProvReg>NY</xEstProvReg>');
});

it('includes xCpl in address when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->cnpj = '12345678000195';
    $prest->xnome = 'Empresa';
    $prest->regtrib = new stdClass;
    $prest->regtrib->opsimpnac = 1;
    $prest->regtrib->regesptrib = 0;

    $prest->end = new stdClass;
    $prest->end->endnac = new stdClass;
    $prest->end->endnac->cmun = '3501608';
    $prest->end->endnac->cep = '01001000';
    $prest->end->xlgr = 'Rua Teste';
    $prest->end->nro = '100';
    $prest->end->xcpl = 'Andar 5';
    $prest->end->xbairro = 'Centro';

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)->toContain('<xCpl>Andar 5</xCpl>');
});
