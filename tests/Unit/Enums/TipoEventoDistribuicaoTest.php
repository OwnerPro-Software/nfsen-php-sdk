<?php

use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;

covers(TipoEventoDistribuicao::class);

it('has 18 cases', function () {
    expect(TipoEventoDistribuicao::cases())->toHaveCount(18);
});

it('maps correct string values', function (TipoEventoDistribuicao $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [TipoEventoDistribuicao::Cancelamento, 'CANCELAMENTO'],
    [TipoEventoDistribuicao::SolicitacaoCancelamentoAnaliseFiscal, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL'],
    [TipoEventoDistribuicao::CancelamentoPorSubstituicao, 'CANCELAMENTO_POR_SUBSTITUICAO'],
    [TipoEventoDistribuicao::CancelamentoDeferidoAnaliseFiscal, 'CANCELAMENTO_DEFERIDO_ANALISE_FISCAL'],
    [TipoEventoDistribuicao::CancelamentoIndeferidoAnaliseFiscal, 'CANCELAMENTO_INDEFERIDO_ANALISE_FISCAL'],
    [TipoEventoDistribuicao::ConfirmacaoPrestador, 'CONFIRMACAO_PRESTADOR'],
    [TipoEventoDistribuicao::RejeicaoPrestador, 'REJEICAO_PRESTADOR'],
    [TipoEventoDistribuicao::ConfirmacaoTomador, 'CONFIRMACAO_TOMADOR'],
    [TipoEventoDistribuicao::RejeicaoTomador, 'REJEICAO_TOMADOR'],
    [TipoEventoDistribuicao::ConfirmacaoIntermediario, 'CONFIRMACAO_INTERMEDIARIO'],
    [TipoEventoDistribuicao::RejeicaoIntermediario, 'REJEICAO_INTERMEDIARIO'],
    [TipoEventoDistribuicao::ConfirmacaoTacita, 'CONFIRMACAO_TACITA'],
    [TipoEventoDistribuicao::AnulacaoRejeicao, 'ANULACAO_REJEICAO'],
    [TipoEventoDistribuicao::CancelamentoPorOficio, 'CANCELAMENTO_POR_OFICIO'],
    [TipoEventoDistribuicao::BloqueioPorOficio, 'BLOQUEIO_POR_OFICIO'],
    [TipoEventoDistribuicao::DesbloqueioPorOficio, 'DESBLOQUEIO_POR_OFICIO'],
    [TipoEventoDistribuicao::InclusaoNfseDan, 'INCLUSAO_NFSE_DAN'],
    [TipoEventoDistribuicao::TributosNfseRecolhidos, 'TRIBUTOS_NFSE_RECOLHIDOS'],
]);

it('creates from valid string', function () {
    expect(TipoEventoDistribuicao::from('CANCELAMENTO'))
        ->toBe(TipoEventoDistribuicao::Cancelamento);
});

it('throws ValueError for invalid string', function () {
    expect(fn () => TipoEventoDistribuicao::from('INVALID'))
        ->toThrow(ValueError::class);
});

it('tryFrom returns null for invalid string', function () {
    expect(TipoEventoDistribuicao::tryFrom('INVALID'))->toBeNull();
});
