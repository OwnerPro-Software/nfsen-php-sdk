<?php

use OwnerPro\Nfsen\Dps\DTO\InfDPS\Subst;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;

covers(Subst::class);

it('creates Subst with all fields', function () {
    $subst = new Subst(
        chSubstda: '12345678901234567890123456789012345678901234567890',
        cMotivo: CodigoJustificativaSubstituicao::Outros,
        xMotivo: 'Motivo de teste para substituição',
    );

    expect($subst->chSubstda)->toBe('12345678901234567890123456789012345678901234567890');
    expect($subst->cMotivo)->toBe(CodigoJustificativaSubstituicao::Outros);
    expect($subst->xMotivo)->toBe('Motivo de teste para substituição');
});

it('creates Subst without optional xMotivo', function () {
    $subst = new Subst(
        chSubstda: '12345678901234567890123456789012345678901234567890',
        cMotivo: CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
    );

    expect($subst->xMotivo)->toBeNull();
});
