<?php

covers(\Pulsar\NfseNacional\Support\XsdValidator::class);

use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Support\XmlDocumentLoader;
use Pulsar\NfseNacional\Support\XsdValidator;

it('throws NfseException when scheme file does not exist', function () {
    $validator = new XsdValidator('/nonexistent/path');

    expect(fn () => $validator->validate('<root/>', 'missing.xsd'))
        ->toThrow(NfseException::class, 'Schema XSD não encontrado');
});

it('throws NfseException when XML loading fails', function () {
    $loader = Mockery::mock(XmlDocumentLoader::class);
    $loader->shouldReceive('__invoke')->andReturn(false);

    $validator = new XsdValidator(__DIR__.'/../../../storage/schemes', xmlDocumentLoader: $loader);

    expect(fn () => $validator->validate('<root/>', 'DPS_v1.01.xsd'))
        ->toThrow(NfseException::class, 'falha ao carregar documento');
});

it('throws NfseException on XSD validation failure', function () {
    $validator = makeXsdValidator();

    $invalidXml = '<DPS versao="1.01" xmlns="http://www.sped.fazenda.gov.br/nfse"><invalid/></DPS>';

    expect(fn () => $validator->validate($invalidXml, 'DPS_v1.01.xsd'))
        ->toThrow(NfseException::class, 'XML inválido');
});

it('includes specific libxml error details in exception message', function () {
    $validator = makeXsdValidator();

    $invalidXml = '<DPS versao="1.01" xmlns="http://www.sped.fazenda.gov.br/nfse"><invalid/></DPS>';

    expect(fn () => $validator->validate($invalidXml, 'DPS_v1.01.xsd'))
        ->toThrow(NfseException::class, 'This element is not expected. Expected is ( {http://www.sped.fazenda.gov.br/nfse}infDPS ).');
});

it('prefixes error message with XML invalido', function () {
    $validator = makeXsdValidator();

    $invalidXml = '<DPS versao="1.01" xmlns="http://www.sped.fazenda.gov.br/nfse"><invalid/></DPS>';

    try {
        $validator->validate($invalidXml, 'DPS_v1.01.xsd');
    } catch (NfseException $e) {
        expect($e->getMessage())->toStartWith('XML inválido: ');

        return;
    }

    test()->fail('Expected NfseException was not thrown.');
});

it('trims whitespace from libxml error messages', function () {
    $validator = makeXsdValidator();

    $invalidXml = '<DPS versao="1.01" xmlns="http://www.sped.fazenda.gov.br/nfse"><invalid/></DPS>';

    try {
        $validator->validate($invalidXml, 'DPS_v1.01.xsd');
    } catch (NfseException $e) {
        // libxml error messages end with \n — trim removes it
        expect($e->getMessage())->not->toMatch('/\n/');

        return;
    }

    test()->fail('Expected NfseException was not thrown.');
});

it('restores libxml error handling after validation', function () {
    $validator = makeXsdValidator();

    $invalidXml = '<DPS versao="1.01" xmlns="http://www.sped.fazenda.gov.br/nfse"><invalid/></DPS>';

    $prevState = libxml_use_internal_errors(false);

    try {
        $validator->validate($invalidXml, 'DPS_v1.01.xsd');
    } catch (NfseException) {
        // expected
    }

    $restoredState = libxml_use_internal_errors($prevState);

    // Must be restored to false (the value we set before calling validate)
    expect($restoredState)->toBeFalse();
});

it('clears libxml errors after validation', function () {
    $validator = makeXsdValidator();

    $invalidXml = '<DPS versao="1.01" xmlns="http://www.sped.fazenda.gov.br/nfse"><invalid/></DPS>';

    libxml_use_internal_errors(true);

    try {
        $validator->validate($invalidXml, 'DPS_v1.01.xsd');
    } catch (NfseException) {
        // expected
    }

    $errors = libxml_get_errors();
    libxml_use_internal_errors(false);

    expect($errors)->toBeEmpty();
});
