<?php

use Pulsar\NfseNacional\Dps\DTO\Valores\DocDedRed;
use Pulsar\NfseNacional\Dps\DTO\Valores\InfoDedRed;
use Pulsar\NfseNacional\Enums\Dps\Valores\TipoDedRed;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('creates InfoDedRed with pDR', function () {
    $info = new InfoDedRed(pDR: '10.00');
    expect($info->pDR)->toBe('10.00');
});

it('creates InfoDedRed with vDR', function () {
    $info = new InfoDedRed(vDR: '100.00');
    expect($info->vDR)->toBe('100.00');
});

it('creates InfoDedRed with documentos', function () {
    $doc = new DocDedRed(
        tpDedRed: TipoDedRed::Materiais,
        dtEmiDoc: '2026-01-15',
        vDedutivelRedutivel: '100.00',
        vDeducaoReducao: '50.00',
        nDoc: 'DOC-001',
    );

    $info = new InfoDedRed(documentos: [$doc]);
    expect($info->documentos)->toHaveCount(1);
});

it('throws when no choice is set', function () {
    expect(fn () => new InfoDedRed)
        ->toThrow(InvalidDpsArgument::class, 'exatamente um');
});

it('throws when multiple choices are set', function () {
    expect(fn () => new InfoDedRed(pDR: '10.00', vDR: '100.00'))
        ->toThrow(InvalidDpsArgument::class, 'exatamente um');
});

it('throws when documentos is empty array', function () {
    expect(fn () => new InfoDedRed(documentos: []))
        ->toThrow(InvalidDpsArgument::class, 'ao menos um');
});
