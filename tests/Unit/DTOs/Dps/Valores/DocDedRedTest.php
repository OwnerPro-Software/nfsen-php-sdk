<?php

use OwnerPro\Nfsen\Dps\DTO\Valores\DocDedRed;
use OwnerPro\Nfsen\Dps\DTO\Valores\NFNFS;
use OwnerPro\Nfsen\Dps\DTO\Valores\NFSeMun;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpDedRed;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

covers(DocDedRed::class);

it('creates DocDedRed with chNFSe', function () {
    $doc = new DocDedRed(
        tpDedRed: TpDedRed::Materiais,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        chNFSe: '12345678901234567890123456789012345678901234567890',
    );

    expect($doc->chNFSe)->toBe('12345678901234567890123456789012345678901234567890');
});

it('creates DocDedRed with NFSeMun', function () {
    $doc = new DocDedRed(
        tpDedRed: TpDedRed::Servicos,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        NFSeMun: new NFSeMun(cMunNFSeMun: '3501608', nNFSeMun: '000000000000001', cVerifNFSeMun: 'ABC123'),
    );

    expect($doc->NFSeMun)->toBeInstanceOf(NFSeMun::class);
});

it('creates DocDedRed with NFNFS', function () {
    $doc = new DocDedRed(
        tpDedRed: TpDedRed::AlimentacaoBebidas,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        NFNFS: new NFNFS(nNFS: '0000001', modNFS: '000000000000001', serieNFS: '1'),
    );

    expect($doc->NFNFS)->toBeInstanceOf(NFNFS::class);
});

it('throws when no document type is set', function () {
    expect(fn () => new DocDedRed(
        tpDedRed: TpDedRed::Materiais,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when multiple document types are set', function () {
    expect(fn () => new DocDedRed(
        tpDedRed: TpDedRed::Materiais,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        chNFSe: '12345678901234567890123456789012345678901234567890',
        nDocFisc: 'DOC-001',
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});
