<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Operations\Decorators\SubstitutorWithDanfse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Tests\Fakes\FakeRendersDanfse;
use OwnerPro\Nfsen\Tests\Fakes\FakeSubstitutesNfse;

covers(SubstitutorWithDanfse::class);

it('substituir sucesso anexa pdf — render chamado exatamente 1x', function (DpsData $data) {
    $inner = new FakeSubstitutesNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new SubstitutorWithDanfse($inner, $renderer);

    $resp = $decorator->substituir(
        'CHAVE_ANTIGA',
        $data,
        CodigoJustificativaSubstituicao::Outros,
        'descr',
    );

    expect($inner->substituirCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(1);
    expect($resp->chave)->toBe('CHAVE_SUBST');
    expect($resp->pdf)->toBe('%PDF-1.4 fake');
})->with('dpsData');

it('não chama renderer quando substituir falha', function (DpsData $data) {
    $inner = new FakeSubstitutesNfse(new NfseResponse(sucesso: false));
    $renderer = new FakeRendersDanfse;
    $decorator = new SubstitutorWithDanfse($inner, $renderer);

    $resp = $decorator->substituir(
        'CHAVE',
        $data,
        CodigoJustificativaSubstituicao::Outros,
    );

    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBeNull();
})->with('dpsData');

it('render falha anexa pdfErrors', function (DpsData $data) {
    $inner = new FakeSubstitutesNfse;
    $renderer = FakeRendersDanfse::failing('boom');
    $decorator = new SubstitutorWithDanfse($inner, $renderer);

    $resp = $decorator->substituir(
        'CHAVE',
        $data,
        CodigoJustificativaSubstituicao::Outros,
    );

    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->toBeNull();
    expect($resp->pdfErrors[0]->descricao)->toBe('boom');
})->with('dpsData');
