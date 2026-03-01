<?php

use Pulsar\NfseNacional\Xml\Builders\TomadorBuilder;

it('builds toma element with CNPJ', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->CNPJ = '98765432000111';
    $toma->xNome = 'Tomador Ltda';

    $element = $builder->build($doc, $toma);

    expect($doc->saveXML($element))->toContain('<CNPJ>98765432000111</CNPJ>');
    expect($doc->saveXML($element))->toContain('<xNome>Tomador Ltda</xNome>');
});

it('builds toma element with CPF', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->CPF = '12345678901';
    $toma->xNome = 'Pessoa Física';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<CPF>12345678901</CPF>')
        ->not->toContain('<CNPJ>');
});

it('builds toma element with NIF', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->NIF = 'NIF12345';
    $toma->xNome = 'Estrangeiro';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<NIF>NIF12345</NIF>')
        ->not->toContain('<CNPJ>')
        ->not->toContain('<CPF>');
});

it('builds toma element with cNaoNIF', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->cNaoNIF = '1';
    $toma->xNome = 'Estrangeiro';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<cNaoNIF>1</cNaoNIF>')
        ->not->toContain('<NIF>');
});

it('includes CAEPF and IM when set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->CPF = '12345678901';
    $toma->CAEPF = '12345678901234';
    $toma->IM = '1234567';
    $toma->xNome = 'Pessoa';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<CAEPF>12345678901234</CAEPF>')
        ->toContain('<IM>1234567</IM>');
});

it('builds endNac address block', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->CNPJ = '98765432000111';
    $toma->xNome = 'Tomador';

    $toma->end = new stdClass;
    $toma->end->endNac = new stdClass;
    $toma->end->endNac->cMun = '3501608';
    $toma->end->endNac->CEP = '01001000';
    $toma->end->xLgr = 'Rua Teste';
    $toma->end->nro = '100';
    $toma->end->xBairro = 'Centro';

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
    $toma->CNPJ = '98765432000111';
    $toma->xNome = 'Tomador';

    $toma->end = new stdClass;
    $toma->end->endExt = new stdClass;
    $toma->end->endExt->cPais = '01058';
    $toma->end->endExt->cEndPost = '10001';
    $toma->end->endExt->xCidade = 'New York';
    $toma->end->endExt->xEstProvReg = 'NY';
    $toma->end->xLgr = '5th Avenue';
    $toma->end->nro = '200';
    $toma->end->xBairro = 'Manhattan';

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
    $toma->CNPJ = '98765432000111';
    $toma->xNome = 'Tomador';

    $toma->end = new stdClass;
    $toma->end->endNac = new stdClass;
    $toma->end->endNac->cMun = '3501608';
    $toma->end->endNac->CEP = '01001000';
    $toma->end->xLgr = 'Rua Teste';
    $toma->end->nro = '100';
    $toma->end->xCpl = 'Sala 5';
    $toma->end->xBairro = 'Centro';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)->toContain('<xCpl>Sala 5</xCpl>');
});

it('throws when both CNPJ and CPF are set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->CNPJ = '98765432000111';
    $toma->CPF = '12345678901';
    $toma->xNome = 'Tomador';

    expect(fn () => $builder->build($doc, $toma))
        ->toThrow(InvalidArgumentException::class, 'apenas um');
});

it('throws when no identification is set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->xNome = 'Tomador';

    expect(fn () => $builder->build($doc, $toma))
        ->toThrow(InvalidArgumentException::class, 'requer CNPJ');
});

it('includes fone and email when set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass;
    $toma->CNPJ = '98765432000111';
    $toma->xNome = 'Tomador';
    $toma->fone = '11999998888';
    $toma->email = 'tomador@test.com';

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<fone>11999998888</fone>')
        ->toContain('<email>tomador@test.com</email>');
});
