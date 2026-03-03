<?php

use Pulsar\NfseNacional\Dps\DTO\InfDPS\SubstituicaoData;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;

it('creates SubstituicaoData with all fields', function () {
    $subst = new SubstituicaoData(
        chSubstda: '12345678901234567890123456789012345678901234567890',
        cMotivo: CodigoJustificativaSubstituicao::Outros,
        xMotivo: 'Motivo de teste para substituição',
    );

    expect($subst->chSubstda)->toBe('12345678901234567890123456789012345678901234567890');
    expect($subst->cMotivo)->toBe(CodigoJustificativaSubstituicao::Outros);
    expect($subst->xMotivo)->toBe('Motivo de teste para substituição');
});

it('creates SubstituicaoData without optional xMotivo', function () {
    $subst = new SubstituicaoData(
        chSubstda: '12345678901234567890123456789012345678901234567890',
        cMotivo: CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
    );

    expect($subst->xMotivo)->toBeNull();
});
