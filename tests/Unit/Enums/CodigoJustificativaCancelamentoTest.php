<?php

use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;

covers(CodigoJustificativaCancelamento::class);

it('has erro emissao value', function (): void {
    expect(CodigoJustificativaCancelamento::ErroEmissao->value)->toBe('1');
});

it('has servico nao prestado value', function (): void {
    expect(CodigoJustificativaCancelamento::ServicoNaoPrestado->value)->toBe('2');
});

it('has outros value', function (): void {
    expect(CodigoJustificativaCancelamento::Outros->value)->toBe('9');
});
