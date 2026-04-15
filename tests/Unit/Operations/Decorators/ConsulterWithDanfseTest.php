<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Operations\Decorators\ConsulterWithDanfse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Tests\Fakes\FakeConsultsNfse;
use OwnerPro\Nfsen\Tests\Fakes\FakeRendersDanfse;

covers(ConsulterWithDanfse::class);

it('nfse() sucesso anexa pdf', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->nfse('CHAVE_X');

    expect($inner->nfseCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(1);
    expect($resp->chave)->toBe('CHAVE_CONSULT');
    expect($resp->pdf)->toBe('%PDF-1.4 fake');
});

it('nfse() falha não chama renderer', function () {
    $inner = new FakeConsultsNfse(new NfseResponse(sucesso: false));
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->nfse('CHAVE');

    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBeNull();
});

it('dps() delega sem chamar renderer', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->dps('DPS1');

    expect($inner->dpsCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->chave)->toBe('K');
});

it('danfse() delega (retorna DanfseResponse oficial) sem chamar renderer', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->danfse('CHAVE');

    expect($inner->danfseCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBe('%PDF-official');
});

it('eventos() delega sem chamar renderer', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->eventos('CHAVE', TipoEvento::CancelamentoPorIniciativaPrestador, 1);

    expect($inner->eventosCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->sucesso)->toBeTrue();
});

it('eventos() sem nSequencial usa default 1 (mata mutantes no default)', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $decorator->eventos('CHAVE');

    expect($inner->eventosNSequencialRecebido)->toBe(1);
});

it('verificarDps() delega sem chamar renderer', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $ok = $decorator->verificarDps('DPS1');

    expect($inner->verificarDpsCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(0);
    expect($ok)->toBeTrue();
});
