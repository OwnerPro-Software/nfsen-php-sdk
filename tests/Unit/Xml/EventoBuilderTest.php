<?php

use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Xml\Builders\EventoBuilder;

function parseEventoXml(string $xml): DOMXPath
{
    $doc = new DOMDocument;
    $doc->loadXML($xml);
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

    return $xpath;
}

it('builds evento xml for e101101 with all required elements', function () {
    $builder = new EventoBuilder;

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-02-27T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: 'CHAVE50CARACTERES1234567890123456789012345678901',
        motivo: MotivoCancelamento::ErroEmissao,
        descricao: 'Erro ao emitir',
    );

    $xpath = parseEventoXml($xml);

    // Root element with correct namespace
    $root = $xpath->query('/n:pedRegEvento')->item(0);
    expect($root)->not->toBeNull();
    expect($root->namespaceURI)->toBe('http://www.sped.fazenda.gov.br/nfse');

    // infPedReg Id
    $infPedReg = $xpath->query('/n:pedRegEvento/n:infPedReg')->item(0);
    expect($infPedReg->getAttribute('Id'))
        ->toBe('PRECHAVE50CARACTERES1234567890123456789012345678901101101');

    // Header fields
    expect($xpath->query('//n:tpAmb')->item(0)->textContent)->toBe('2');
    expect($xpath->query('//n:verAplic')->item(0)->textContent)->toBe('1.0');
    expect($xpath->query('//n:dhEvento')->item(0)->textContent)->toBe('2026-02-27T10:00:00-03:00');
    expect($xpath->query('//n:CNPJAutor')->item(0)->textContent)->toBe('12345678000195');

    // chNFSe and event-specific block
    expect($xpath->query('//n:chNFSe')->item(0)->textContent)
        ->toBe('CHAVE50CARACTERES1234567890123456789012345678901');
    expect($xpath->query('//n:e101101')->item(0))->not->toBeNull();
    expect($xpath->query('//n:xDesc')->item(0)->textContent)->toBe('Cancelamento de NFS-e');
    expect($xpath->query('//n:cMotivo')->item(0)->textContent)->toBe('e101101');
    expect($xpath->query('//n:xMotivo')->item(0)->textContent)->toBe('Erro ao emitir');
});

it('builds evento xml for e105102 with CPF autor', function () {
    $builder = new EventoBuilder;

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-02-27T10:00:00-03:00',
        cnpjAutor: null,
        cpfAutor: '12345678901',
        chNFSe: 'CHAVE50CARACTERES1234567890123456789012345678901',
        motivo: MotivoCancelamento::Outros,
        descricao: 'Motivo diverso',
    );

    $xpath = parseEventoXml($xml);

    $infPedReg = $xpath->query('/n:pedRegEvento/n:infPedReg')->item(0);
    expect($infPedReg->getAttribute('Id'))
        ->toBe('PRECHAVE50CARACTERES1234567890123456789012345678901105102');

    expect($xpath->query('//n:CPFAutor')->item(0)->textContent)->toBe('12345678901');
    expect($xpath->query('//n:CNPJAutor')->item(0))->toBeNull();

    expect($xpath->query('//n:e105102')->item(0))->not->toBeNull();
    expect($xpath->query('//n:xDesc')->item(0)->textContent)->toBe('Cancelamento de NFS-e por Substituicao');
    expect($xpath->query('//n:cMotivo')->item(0)->textContent)->toBe('e105102');
    expect($xpath->query('//n:xMotivo')->item(0)->textContent)->toBe('Motivo diverso');
});
