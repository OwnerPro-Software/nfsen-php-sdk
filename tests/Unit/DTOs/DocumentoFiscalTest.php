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

it('leaves parseError null for an intact document', function () {
    $doc = DocumentoFiscal::fromArray([
        'NSU' => 7,
        'TipoDocumento' => 'NFSE',
        'ArquivoXml' => base64_encode((string) gzencode('<NFSe/>')),
    ]);

    expect($doc->parseError)->toBeNull();
});

it('reports a missing TipoDocumento instead of throwing', function () {
    // `DistribuicaoNSU` não declara campo obrigatório algum no swagger do ADN.
    $doc = DocumentoFiscal::fromArray(['NSU' => 12]);

    expect($doc->tipoDocumento)->toBeNull()
        ->and($doc->nsu)->toBe(12)
        ->and($doc->parseError)->toContain('TipoDocumento ausente');
});

it('reports an unknown TipoDocumento instead of throwing', function () {
    // Tipo que o governo passe a emitir e esta versão do SDK ainda não conheça.
    $doc = DocumentoFiscal::fromArray([
        'NSU' => 13,
        'TipoDocumento' => 'NFSE_SUBSTITUTA',
    ]);

    expect($doc->tipoDocumento)->toBeNull()
        ->and($doc->nsu)->toBe(13)
        ->and($doc->parseError)->toContain('TipoDocumento desconhecido: "NFSE_SUBSTITUTA"');
});

it('reports an unknown TipoEvento instead of throwing', function () {
    $doc = DocumentoFiscal::fromArray([
        'NSU' => 14,
        'TipoDocumento' => 'EVENTO',
        'TipoEvento' => 'EVENTO_FUTURO',
    ]);

    expect($doc->tipoDocumento)->toBe(TipoDocumentoFiscal::Evento)
        ->and($doc->tipoEvento)->toBeNull()
        ->and($doc->parseError)->toContain('TipoEvento desconhecido: "EVENTO_FUTURO"');
});

it('reports an undecodable ArquivoXml instead of throwing', function () {
    // As duas falhas de GzipCompressor::decompressB64() (base64 e gzip) sobem como
    // NfseException pelo mesmo catch; o gzip corrompido em si já é coberto em
    // GzipCompressorTest, e repeti-lo aqui só duplicaria o warning do gzdecode().
    $doc = DocumentoFiscal::fromArray([
        'NSU' => 15,
        'TipoDocumento' => 'NFSE',
        'ArquivoXml' => 'nao-e-base64-valido!!',
    ]);

    expect($doc->arquivoXml)->toBeNull()
        ->and($doc->nsu)->toBe(15)
        ->and($doc->tipoDocumento)->toBe(TipoDocumentoFiscal::Nfse)
        ->and($doc->parseError)->toContain('decodificar base64');
});

it('accumulates every problem of a single document', function () {
    $doc = DocumentoFiscal::fromArray([
        'NSU' => 16,
        'TipoDocumento' => 'TIPO_NOVO',
        'TipoEvento' => 'EVENTO_NOVO',
        'ArquivoXml' => 'nao-e-base64-valido!!',
    ]);

    expect($doc->parseError)
        ->toContain('TipoDocumento desconhecido')
        ->toContain('TipoEvento desconhecido')
        ->toContain('decodificar base64');
});
