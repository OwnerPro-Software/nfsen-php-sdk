<?php

use Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder;

it('builds prest element with CNPJ', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->CNPJ = '12345678000195';
    $prest->xNome = 'Empresa Teste';
    $regTrib = new stdClass;
    $regTrib->opSimpNac = 1;
    $regTrib->regEspTrib = 0;
    $prest->regTrib = $regTrib;

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
    $prest->CPF = '12345678901';
    $prest->xNome = 'Pessoa Física';
    $regTrib = new stdClass;
    $regTrib->opSimpNac = 0;
    $regTrib->regEspTrib = 0;
    $prest->regTrib = $regTrib;

    $element = $builder->build($doc, $prest);

    expect($doc->saveXML($element))->toContain('<CPF>12345678901</CPF>');
});

it('includes CAEPF and IM when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->CNPJ = '12345678000195';
    $prest->CAEPF = '12345678901234';
    $prest->IM = '9876543';
    $prest->xNome = 'Empresa';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 1;
    $prest->regTrib->regEspTrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<CAEPF>12345678901234</CAEPF>')
        ->toContain('<IM>9876543</IM>');
});

it('builds prest element with NIF', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->NIF = 'NIF999';
    $prest->xNome = 'Empresa Estrangeira';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 0;
    $prest->regTrib->regEspTrib = 0;

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
    $prest->cNaoNIF = '1';
    $prest->xNome = 'Empresa Estrangeira';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 0;
    $prest->regTrib->regEspTrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<cNaoNIF>1</cNaoNIF>')
        ->not->toContain('<CNPJ>')
        ->not->toContain('<CPF>')
        ->not->toContain('<NIF>');
});

it('throws when both CNPJ and CPF are set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->CNPJ = '12345678000195';
    $prest->CPF = '12345678901';
    $prest->xNome = 'Empresa';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 1;
    $prest->regTrib->regEspTrib = 0;

    expect(fn () => $builder->build($doc, $prest))
        ->toThrow(InvalidArgumentException::class, 'apenas um');
});

it('throws when no identification is set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->xNome = 'Empresa';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 1;
    $prest->regTrib->regEspTrib = 0;

    expect(fn () => $builder->build($doc, $prest))
        ->toThrow(InvalidArgumentException::class, 'requer CNPJ');
});

it('includes fone and email when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->CNPJ = '12345678000195';
    $prest->xNome = 'Empresa';
    $prest->fone = '11999998888';
    $prest->email = 'empresa@test.com';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 1;
    $prest->regTrib->regEspTrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<fone>11999998888</fone>')
        ->toContain('<email>empresa@test.com</email>');
});

it('includes regApTribSN when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->CNPJ = '12345678000195';
    $prest->xNome = 'Empresa';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 1;
    $prest->regTrib->regApTribSN = 2;
    $prest->regTrib->regEspTrib = 0;

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)->toContain('<regApTribSN>2</regApTribSN>');
});

it('builds endNac address block', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass;
    $prest->CNPJ = '12345678000195';
    $prest->xNome = 'Empresa';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 1;
    $prest->regTrib->regEspTrib = 0;

    $prest->end = new stdClass;
    $prest->end->endNac = new stdClass;
    $prest->end->endNac->cMun = '3501608';
    $prest->end->endNac->CEP = '01001000';
    $prest->end->xLgr = 'Rua Teste';
    $prest->end->nro = '100';
    $prest->end->xBairro = 'Centro';

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
    $prest->CNPJ = '12345678000195';
    $prest->xNome = 'Empresa';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 1;
    $prest->regTrib->regEspTrib = 0;

    $prest->end = new stdClass;
    $prest->end->endExt = new stdClass;
    $prest->end->endExt->cPais = '01058';
    $prest->end->endExt->cEndPost = '10001';
    $prest->end->endExt->xCidade = 'New York';
    $prest->end->endExt->xEstProvReg = 'NY';
    $prest->end->xLgr = '5th Avenue';
    $prest->end->nro = '200';
    $prest->end->xBairro = 'Manhattan';

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
    $prest->CNPJ = '12345678000195';
    $prest->xNome = 'Empresa';
    $prest->regTrib = new stdClass;
    $prest->regTrib->opSimpNac = 1;
    $prest->regTrib->regEspTrib = 0;

    $prest->end = new stdClass;
    $prest->end->endNac = new stdClass;
    $prest->end->endNac->cMun = '3501608';
    $prest->end->endNac->CEP = '01001000';
    $prest->end->xLgr = 'Rua Teste';
    $prest->end->nro = '100';
    $prest->end->xCpl = 'Andar 5';
    $prest->end->xBairro = 'Centro';

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)->toContain('<xCpl>Andar 5</xCpl>');
});
