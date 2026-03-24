<?php

covers(\OwnerPro\Nfsen\Dps\DTO\Valores\VDedRed::class);
use OwnerPro\Nfsen\Dps\DTO\Valores\DocDedRed;
use OwnerPro\Nfsen\Dps\DTO\Valores\VDedRed;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpDedRed;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

it('creates VDedRed with pDR', function () {
    $info = new VDedRed(pDR: '10.00');
    expect($info->pDR)->toBe('10.00');
});

it('creates VDedRed with vDR', function () {
    $info = new VDedRed(vDR: '100.00');
    expect($info->vDR)->toBe('100.00');
});

it('creates VDedRed with documentos', function () {
    $doc = new DocDedRed(
        tpDedRed: TpDedRed::Materiais,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        nDoc: 'DOC-001',
    );

    $info = new VDedRed(documentos: [$doc]);
    expect($info->documentos)->toHaveCount(1);
});

it('throws when no choice is set', function () {
    expect(fn () => new VDedRed)
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when multiple choices are set', function () {
    expect(fn () => new VDedRed(pDR: '10.00', vDR: '100.00'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when documentos is empty array', function () {
    expect(fn () => new VDedRed(documentos: []))
        ->toThrow(InvalidDpsArgument::class, 'ao menos um');
});
