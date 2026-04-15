<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Operations\Decorators\EmitterWithDanfse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Tests\Fakes\FakeEmitsNfse;
use OwnerPro\Nfsen\Tests\Fakes\FakeRendersDanfse;

covers(EmitterWithDanfse::class);

it('emitir sucesso anexa pdf', function (DpsData $data) {
    $inner = new FakeEmitsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitir($data);

    expect($inner->emitirCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(1);
    expect($resp->chave)->toBe('CHAVE_EMIT');
    expect($resp->pdf)->toBe('%PDF-1.4 fake');
})->with('dpsData');

it('emitirDecisaoJudicial sucesso anexa pdf', function (DpsData $data) {
    $inner = new FakeEmitsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitirDecisaoJudicial($data);

    expect($inner->emitirDecisaoJudicialCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(1);
    expect($resp->chave)->toBe('CHAVE_DECISAO');
    expect($resp->pdf)->toBe('%PDF-1.4 fake');
})->with('dpsData');

it('não chama renderer quando emit retorna falha', function (DpsData $data) {
    $inner = new FakeEmitsNfse(
        emitirResponse: new NfseResponse(sucesso: false),
    );
    $renderer = new FakeRendersDanfse;
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitir($data);

    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBeNull();
    expect($resp->sucesso)->toBeFalse();
})->with('dpsData');

it('não chama renderer quando xml é null', function (DpsData $data) {
    $inner = new FakeEmitsNfse(
        emitirResponse: new NfseResponse(sucesso: true, chave: 'K', xml: null),
    );
    $renderer = new FakeRendersDanfse;
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitir($data);

    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBeNull();
})->with('dpsData');

it('render falha → pdf null, pdfErrors populado, sucesso preservado', function (DpsData $data) {
    $inner = new FakeEmitsNfse;
    $renderer = FakeRendersDanfse::failing('dompdf quebrou');
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitir($data);

    expect($resp->sucesso)->toBeTrue();
    expect($resp->chave)->toBe('CHAVE_EMIT');
    expect($resp->pdf)->toBeNull();
    expect($resp->pdfErrors)->toHaveCount(1);
    expect($resp->pdfErrors[0]->descricao)->toBe('dompdf quebrou');
})->with('dpsData');
