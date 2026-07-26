<?php

use OwnerPro\Nfsen\Enums\EventoCancelamento;

covers(EventoCancelamento::class);

// Tag e xDesc não são rótulo interno: o XSD enumera o texto exato de cada um
// (TE101101 e TE101103 em tiposEventos_v1.01.xsd), e o Id do pedido concatena o
// código. Errar qualquer um dos três faz a SEFIN recusar o documento.
it('deriva a tag do elemento a partir do código do evento', function (EventoCancelamento $evento, string $tag): void {
    expect($evento->tag())->toBe($tag);
})->with([
    [EventoCancelamento::Cancelamento, 'e101101'],
    [EventoCancelamento::SolicitacaoAnaliseFiscal, 'e101103'],
]);

it('usa exatamente o xDesc que o XSD enumera para o evento', function (EventoCancelamento $evento): void {
    $doc = new DOMDocument;
    $doc->load(__DIR__.'/../../../storage/schemes/tiposEventos_v1.01.xsd');

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

    $doXsd = $xpath->evaluate(sprintf(
        'string(//xs:complexType[@name="TE%s"]//xs:element[@name="xDesc"]//xs:enumeration/@value)',
        $evento->value,
    ));

    expect($doXsd)->not->toBe('')
        ->and($evento->xDesc())->toBe($doXsd);
})->with([
    [EventoCancelamento::Cancelamento],
    [EventoCancelamento::SolicitacaoAnaliseFiscal],
]);
