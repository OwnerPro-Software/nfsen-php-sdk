<?php

use OwnerPro\Nfsen\Dps\DTO\InfDPS\InfDPS;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\Subst;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\CMotivoEmisTI;

covers(InfDPS::class, Subst::class);

it('InfDPS::fromArray creates instance from array', function () {
    $dto = InfDPS::fromArray([
        'tpAmb' => '2',
        'dhEmi' => '2026-02-27T10:00:00-03:00',
        'verAplic' => '1.0',
        'serie' => '1',
        'nDPS' => '1',
        'dCompet' => '2026-02-27',
        'tpEmit' => '1',
        'cLocEmi' => '3501608',
    ]);

    expect($dto)->toBeInstanceOf(InfDPS::class)
        ->and($dto->serie)->toBe('1');
});

it('InfDPS::fromArray preserves optional fields', function () {
    $dto = InfDPS::fromArray([
        'tpAmb' => '2',
        'dhEmi' => '2026-02-27T10:00:00-03:00',
        'verAplic' => '1.0',
        'serie' => '1',
        'nDPS' => '1',
        'dCompet' => '2026-02-27',
        'tpEmit' => '1',
        'cLocEmi' => '3501608',
        'cMotivoEmisTI' => '1',
        'chNFSeRej' => 'CHAVE_REJEITADA_123',
    ]);

    expect($dto->cMotivoEmisTI)->toBe(CMotivoEmisTI::ImportacaoServico)
        ->and($dto->chNFSeRej)->toBe('CHAVE_REJEITADA_123');
});

it('InfDPS::fromArray casts nDPS to string', function () {
    $dto = InfDPS::fromArray([
        'tpAmb' => '2',
        'dhEmi' => '2026-02-27T10:00:00-03:00',
        'verAplic' => '1.0',
        'serie' => '1',
        'nDPS' => 42,
        'dCompet' => '2026-02-27',
        'tpEmit' => '1',
        'cLocEmi' => '3501608',
    ]);

    expect($dto->nDPS)->toBe('42');
});

it('InfDPS::fromArray throws ValueError for invalid tpAmb', function () {
    InfDPS::fromArray([
        'tpAmb' => '99',
        'dhEmi' => '2026-02-27T10:00:00-03:00',
        'verAplic' => '1.0',
        'serie' => '1',
        'nDPS' => '1',
        'dCompet' => '2026-02-27',
        'tpEmit' => '1',
        'cLocEmi' => '3501608',
    ]);
})->throws(ValueError::class);

it('Subst::fromArray creates instance from array', function () {
    $dto = Subst::fromArray([
        'chSubstda' => '12345678901234567890123456789012345678901234567890',
        'cMotivo' => '01',
        'xMotivo' => 'Motivo teste',
    ]);

    expect($dto)->toBeInstanceOf(Subst::class)
        ->and($dto->chSubstda)->toBe('12345678901234567890123456789012345678901234567890')
        ->and($dto->xMotivo)->toBe('Motivo teste');
});
