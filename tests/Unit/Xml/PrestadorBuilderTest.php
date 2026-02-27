<?php

use Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder;

it('builds prest element with CNPJ', function () {
    $builder = new PrestadorBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass();
    $prest->cnpj = '12345678000195';
    $prest->xnome = 'Empresa Teste';
    $regTrib = new stdClass();
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
    $builder = new PrestadorBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass();
    $prest->cpf = '12345678901';
    $prest->xnome = 'Pessoa Física';
    $regTrib = new stdClass();
    $regTrib->opsimpnac = 0;
    $regTrib->regesptrib = 0;
    $prest->regtrib = $regTrib;

    $element = $builder->build($doc, $prest);

    expect($doc->saveXML($element))->toContain('<CPF>12345678901</CPF>');
});
