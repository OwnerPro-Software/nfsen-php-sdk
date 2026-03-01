<?php

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
