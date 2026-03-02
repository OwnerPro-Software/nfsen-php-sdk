<?php

use Pulsar\NfseNacional\Enums\TipoEvento;

it('has all 18 expected values', function () {
    expect(TipoEvento::cases())->toHaveCount(18);
});

it('maps correct integer values', function (TipoEvento $case, int $expectedValue) {
    expect($case->value)->toBe($expectedValue);
})->with([
    [TipoEvento::CancelamentoPorIniciativaPrestador, 101101],
    [TipoEvento::CancelamentoPorIniciativaFisco, 101103],
    [TipoEvento::CancelamentoPorDecisaoJudicial, 105102],
    [TipoEvento::CancelamentoPorDecisaoAdministrativa, 105104],
    [TipoEvento::CancelamentoPorOficio, 105105],
    [TipoEvento::AnaliseParaCancelamento, 202201],
    [TipoEvento::AnaliseParaCancelamentoDecisaoJudicial, 202205],
    [TipoEvento::SolicitacaoCancelamento, 203202],
    [TipoEvento::SolicitacaoCancelamentoDecisaoJudicial, 203206],
    [TipoEvento::RejeicaoCancelamento, 204203],
    [TipoEvento::RejeicaoCancelamentoDecisaoJudicial, 204207],
    [TipoEvento::ConclusaoCancelamento, 205204],
    [TipoEvento::ConclusaoCancelamentoDecisaoJudicial, 205208],
    [TipoEvento::SubstituicaoPorIniciativaPrestador, 305101],
    [TipoEvento::SubstituicaoPorIniciativaFisco, 305102],
    [TipoEvento::SubstituicaoPorOficio, 305103],
    [TipoEvento::BloqueioNfse, 467201],
    [TipoEvento::TravamentoNfse, 907201],
]);

it('creates from valid integer', function () {
    $evento = TipoEvento::from(101101);

    expect($evento)->toBe(TipoEvento::CancelamentoPorIniciativaPrestador);
});

it('throws ValueError for invalid integer', function () {
    expect(fn () => TipoEvento::from(999999))
        ->toThrow(ValueError::class);
});

it('tryFrom returns null for invalid integer', function () {
    expect(TipoEvento::tryFrom(999999))->toBeNull();
});
