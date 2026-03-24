<?php

use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;

covers(CodigoJustificativaSubstituicao::class);

it('has desenquadramento simples nacional value', function (): void {
    expect(CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional->value)->toBe('01');
});

it('has enquadramento simples nacional value', function (): void {
    expect(CodigoJustificativaSubstituicao::EnquadramentoSimplesNacional->value)->toBe('02');
});

it('has inclusao retroativa imunidade isencao value', function (): void {
    expect(CodigoJustificativaSubstituicao::InclusaoRetroativaImunidadeIsencao->value)->toBe('03');
});

it('has exclusao retroativa imunidade isencao value', function (): void {
    expect(CodigoJustificativaSubstituicao::ExclusaoRetroativaImunidadeIsencao->value)->toBe('04');
});

it('has rejeicao tomador intermediario value', function (): void {
    expect(CodigoJustificativaSubstituicao::RejeicaoTomadorIntermediario->value)->toBe('05');
});

it('has outros value', function (): void {
    expect(CodigoJustificativaSubstituicao::Outros->value)->toBe('99');
});
