<?php

use Pulsar\NfseNacional\Dps\DTO\Valores\DocDedRed;
use Pulsar\NfseNacional\Dps\DTO\Valores\DocNFNFS;
use Pulsar\NfseNacional\Dps\DTO\Valores\DocOutNFSe;
use Pulsar\NfseNacional\Dps\Enums\Valores\TipoDedRed;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('creates DocDedRed with chNFSe', function () {
    $doc = new DocDedRed(
        tpDedRed: TipoDedRed::Materiais,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        chNFSe: '12345678901234567890123456789012345678901234567890',
    );

    expect($doc->chNFSe)->toBe('12345678901234567890123456789012345678901234567890');
});

it('creates DocDedRed with NFSeMun', function () {
    $doc = new DocDedRed(
        tpDedRed: TipoDedRed::Servicos,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        NFSeMun: new DocOutNFSe(cMunNFSeMun: '3501608', nNFSeMun: '000000000000001', cVerifNFSeMun: 'ABC123'),
    );

    expect($doc->NFSeMun)->toBeInstanceOf(DocOutNFSe::class);
});

it('creates DocDedRed with NFNFS', function () {
    $doc = new DocDedRed(
        tpDedRed: TipoDedRed::AlimentacaoBebidas,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        NFNFS: new DocNFNFS(nNFS: '0000001', modNFS: '000000000000001', serieNFS: '1'),
    );

    expect($doc->NFNFS)->toBeInstanceOf(DocNFNFS::class);
});

it('throws when no document type is set', function () {
    expect(fn () => new DocDedRed(
        tpDedRed: TipoDedRed::Materiais,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
    ))->toThrow(InvalidDpsArgument::class, 'exatamente um');
});

it('throws when multiple document types are set', function () {
    expect(fn () => new DocDedRed(
        tpDedRed: TipoDedRed::Materiais,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        chNFSe: '12345678901234567890123456789012345678901234567890',
        nDocFisc: 'DOC-001',
    ))->toThrow(InvalidDpsArgument::class, 'exatamente um');
});
