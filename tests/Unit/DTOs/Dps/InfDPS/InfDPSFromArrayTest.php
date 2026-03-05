<?php

covers(
    \Pulsar\NfseNacional\Dps\DTO\InfDPS\InfDPS::class,
    \Pulsar\NfseNacional\Dps\DTO\InfDPS\SubstituicaoData::class,
);

use Pulsar\NfseNacional\Dps\DTO\InfDPS\InfDPS;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\SubstituicaoData;
use Pulsar\NfseNacional\Dps\Enums\InfDPS\CMotivoEmisTI;

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

it('SubstituicaoData::fromArray creates instance from array', function () {
    $dto = SubstituicaoData::fromArray([
        'chSubstda' => '12345678901234567890123456789012345678901234567890',
        'cMotivo' => '01',
        'xMotivo' => 'Motivo teste',
    ]);

    expect($dto)->toBeInstanceOf(SubstituicaoData::class)
        ->and($dto->chSubstda)->toBe('12345678901234567890123456789012345678901234567890')
        ->and($dto->xMotivo)->toBe('Motivo teste');
});
