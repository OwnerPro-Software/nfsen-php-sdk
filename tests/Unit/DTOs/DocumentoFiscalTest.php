<?php

use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;
use OwnerPro\Nfsen\Responses\DocumentoFiscal;

covers(DocumentoFiscal::class);

it('constructs with all fields', function () {
    $doc = new DocumentoFiscal(
        nsu: 42,
        chaveAcesso: makeChaveAcesso(),
        tipoDocumento: TipoDocumentoFiscal::Nfse,
        tipoEvento: TipoEventoDistribuicao::Cancelamento,
        arquivoXml: '<NFSe/>',
        dataHoraGeracao: '2026-04-08T14:30:00',
    );

    expect($doc)
        ->nsu->toBe(42)
        ->chaveAcesso->toBe(makeChaveAcesso())
        ->tipoDocumento->toBe(TipoDocumentoFiscal::Nfse)
        ->tipoEvento->toBe(TipoEventoDistribuicao::Cancelamento)
        ->arquivoXml->toBe('<NFSe/>')
        ->dataHoraGeracao->toBe('2026-04-08T14:30:00');
});

it('constructs with nullable fields as null', function () {
    $doc = new DocumentoFiscal(
        nsu: null,
        chaveAcesso: null,
        tipoDocumento: TipoDocumentoFiscal::Nenhum,
        tipoEvento: null,
        arquivoXml: null,
        dataHoraGeracao: null,
    );

    expect($doc)
        ->nsu->toBeNull()
        ->chaveAcesso->toBeNull()
        ->tipoEvento->toBeNull()
        ->arquivoXml->toBeNull()
        ->dataHoraGeracao->toBeNull();
});

it('creates from API array with PascalCase keys', function () {
    $xml = '<NFSe/>';
    $gzipB64 = base64_encode((string) gzencode($xml));

    $doc = DocumentoFiscal::fromArray([
        'NSU' => 1,
        'ChaveAcesso' => makeChaveAcesso(),
        'TipoDocumento' => 'NFSE',
        'TipoEvento' => 'CANCELAMENTO',
        'ArquivoXml' => $gzipB64,
        'DataHoraGeracao' => '2026-04-08T14:30:00',
    ]);

    expect($doc)
        ->nsu->toBe(1)
        ->chaveAcesso->toBe(makeChaveAcesso())
        ->tipoDocumento->toBe(TipoDocumentoFiscal::Nfse)
        ->tipoEvento->toBe(TipoEventoDistribuicao::Cancelamento)
        ->arquivoXml->toBe($xml)
        ->dataHoraGeracao->toBe('2026-04-08T14:30:00');
});

it('creates from API array without optional fields', function () {
    $doc = DocumentoFiscal::fromArray([
        'TipoDocumento' => 'DPS',
    ]);

    expect($doc)
        ->nsu->toBeNull()
        ->chaveAcesso->toBeNull()
        ->tipoDocumento->toBe(TipoDocumentoFiscal::Dps)
        ->tipoEvento->toBeNull()
        ->arquivoXml->toBeNull()
        ->dataHoraGeracao->toBeNull();
});

it('decompresses ArquivoXml from gzip base64', function () {
    $xml = '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse"><infDPS/></DPS>';
    $gzipB64 = base64_encode((string) gzencode($xml));

    $doc = DocumentoFiscal::fromArray([
        'TipoDocumento' => 'DPS',
        'ArquivoXml' => $gzipB64,
    ]);

    expect($doc->arquivoXml)->toBe($xml);
});

it('handles null ArquivoXml', function () {
    $doc = DocumentoFiscal::fromArray([
        'TipoDocumento' => 'NFSE',
        'ArquivoXml' => null,
    ]);

    expect($doc->arquivoXml)->toBeNull();
});
