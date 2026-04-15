<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Operations\Decorators\Concerns\AttachesDanfsePdf;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;
use OwnerPro\Nfsen\Tests\Fakes\FakeRendersDanfse;

covers(AttachesDanfsePdf::class);

// Harness mínimo expondo attachPdf() para testes isolados.
// Implementa renderer() exigido pelo trait (abstract private).
function makeAttacher(RendersDanfse $r): object
{
    return new class($r)
    {
        use AttachesDanfsePdf {
            attachPdf as public;
        }

        public function __construct(private readonly RendersDanfse $renderer) {}

        private function renderer(): RendersDanfse
        {
            return $this->renderer;
        }
    };
}

it('anexa pdf quando resposta tem sucesso + xml', function () {
    $spy = new FakeRendersDanfse;
    $attacher = makeAttacher($spy);

    $result = $attacher->attachPdf(new NfseResponse(
        sucesso: true,
        chave: 'CHAVE',
        xml: '<xml/>',
    ));

    expect($spy->toPdfCalls)->toBe(1);
    expect($spy->xmlsReceived)->toBe(['<xml/>']);
    expect($result->pdf)->toBe('%PDF-1.4 fake');
    expect($result->pdfErrors)->toBe([]);
    expect($result->sucesso)->toBeTrue();
    expect($result->chave)->toBe('CHAVE');
});

it('não chama renderer quando sucesso é false', function () {
    $spy = new FakeRendersDanfse;
    $attacher = makeAttacher($spy);

    $original = new NfseResponse(
        sucesso: false,
        erros: [new ProcessingMessage(descricao: 'X')],
    );
    $result = $attacher->attachPdf($original);

    expect($spy->toPdfCalls)->toBe(0);
    expect($result)->toBe($original);
});

it('não chama renderer quando xml é null (mesmo com sucesso)', function () {
    $spy = new FakeRendersDanfse;
    $attacher = makeAttacher($spy);

    $original = new NfseResponse(sucesso: true, xml: null);
    $result = $attacher->attachPdf($original);

    expect($spy->toPdfCalls)->toBe(0);
    expect($result)->toBe($original);
});

it('popula pdfErrors quando render falha', function () {
    $spy = FakeRendersDanfse::failing('render quebrou');
    $attacher = makeAttacher($spy);

    $result = $attacher->attachPdf(new NfseResponse(
        sucesso: true,
        chave: 'CHAVE',
        xml: '<xml/>',
    ));

    expect($result->sucesso)->toBeTrue();
    expect($result->chave)->toBe('CHAVE');
    expect($result->pdf)->toBeNull();
    expect($result->pdfErrors)->toHaveCount(1);
    expect($result->pdfErrors[0]->descricao)->toBe('render quebrou');
});

it('preserva todos os campos do NfseResponse original ao anexar pdf', function () {
    $spy = new FakeRendersDanfse;
    $attacher = makeAttacher($spy);

    $result = $attacher->attachPdf(new NfseResponse(
        sucesso: true,
        chave: 'K',
        xml: '<x/>',
        idDps: 'DPS1',
        alertas: [new ProcessingMessage(descricao: 'alert')],
        erros: [],
        tipoAmbiente: 2,
        versaoAplicativo: 'v1',
        dataHoraProcessamento: '2026-04-15T10:00:00',
    ));

    expect($result->chave)->toBe('K');
    expect($result->idDps)->toBe('DPS1');
    expect($result->alertas)->toHaveCount(1);
    expect($result->tipoAmbiente)->toBe(2);
    expect($result->versaoAplicativo)->toBe('v1');
    expect($result->dataHoraProcessamento)->toBe('2026-04-15T10:00:00');
    expect($result->pdf)->toBe('%PDF-1.4 fake');
});
