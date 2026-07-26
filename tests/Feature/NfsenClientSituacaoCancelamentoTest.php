<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Enums\SituacaoCancelamento;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\NfseConsulter;

covers(NfsenClient::class, NfseConsulter::class);

/** @param list<array{int|null, string}> $eventos NSU e TipoEvento de cada documento do lote */
function makeLoteEventos(array $eventos, string $status = 'DOCUMENTOS_LOCALIZADOS'): array
{
    $gzipB64 = base64_encode((string) gzencode('<evento/>'));

    return [
        'StatusProcessamento' => $status,
        'LoteDFe' => array_map(fn (array $evento): array => [
            'NSU' => $evento[0],
            'ChaveAcesso' => makeChaveAcesso(),
            'TipoDocumento' => 'EVENTO',
            'TipoEvento' => $evento[1],
            'ArquivoXml' => $gzipB64,
            'DataHoraGeracao' => '2026-07-26T00:32:25',
        ], $eventos),
        'Alertas' => [],
        'Erros' => [],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-07-26T00:33:00',
    ];
}

function situacaoCancelamentoDe(array $apiResponse): SituacaoCancelamento
{
    Http::fake(['*' => Http::response($apiResponse, 200)]);

    return NfsenClient::for(makePfxContent(), 'secret', '9999999')
        ->consultar()
        ->situacaoCancelamento(makeChaveAcesso());
}

it('lê a situação do cancelamento nos eventos da nota', function (array $eventos, SituacaoCancelamento $esperada) {
    expect(situacaoCancelamentoDe(makeLoteEventos($eventos)))->toBe($esperada);
})->with([
    'só o pedido' => [[[10, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL']], SituacaoCancelamento::EmAnalise],
    'pedido deferido' => [[
        [10, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL'],
        [20, 'CANCELAMENTO_DEFERIDO_ANALISE_FISCAL'],
    ], SituacaoCancelamento::Deferido],
    'pedido indeferido' => [[
        [10, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL'],
        [20, 'CANCELAMENTO_INDEFERIDO_ANALISE_FISCAL'],
    ], SituacaoCancelamento::Indeferido],
    // O contribuinte pode pedir de novo depois de um indeferimento: a nota volta a
    // estar sob análise, e é o pedido mais recente que vale.
    'novo pedido depois do indeferimento' => [[
        [10, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL'],
        [20, 'CANCELAMENTO_INDEFERIDO_ANALISE_FISCAL'],
        [30, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL'],
    ], SituacaoCancelamento::EmAnalise],
    'eventos alheios à análise fiscal' => [[
        [10, 'CONFIRMACAO_PRESTADOR'],
        [20, 'TRIBUTOS_NFSE_RECOLHIDOS'],
    ], SituacaoCancelamento::SemPedido],
    'evento alheio depois do pedido' => [[
        [10, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL'],
        [20, 'CONFIRMACAO_PRESTADOR'],
    ], SituacaoCancelamento::EmAnalise],
]);

// O ADN não promete ordem no lote, e ordenar por posição faria um desfecho antigo
// vencer o mais recente — dizendo "deferido" de uma nota que voltou à análise.
it('decide pelo maior NSU, não pela ordem do lote', function () {
    $situacao = situacaoCancelamentoDe(makeLoteEventos([
        [30, 'CANCELAMENTO_DEFERIDO_ANALISE_FISCAL'],
        [10, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL'],
    ]));

    expect($situacao)->toBe(SituacaoCancelamento::Deferido);
});

// NSU é opcional no contrato do ADN (`DistribuicaoNSU`): sem ele, resta a ordem em
// que o lote chegou.
it('cai na ordem do lote quando os eventos vêm sem NSU', function () {
    $situacao = situacaoCancelamentoDe(makeLoteEventos([
        [null, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL'],
        [null, 'CANCELAMENTO_DEFERIDO_ANALISE_FISCAL'],
    ]));

    expect($situacao)->toBe(SituacaoCancelamento::Deferido);
});

it('reporta SemPedido quando a nota não tem evento algum', function () {
    $situacao = situacaoCancelamentoDe(makeLoteEventos([], 'NENHUM_DOCUMENTO_LOCALIZADO'));

    expect($situacao)->toBe(SituacaoCancelamento::SemPedido);
});

it('consulta o endpoint de eventos do ADN com a chave informada', function () {
    Http::fake(['*' => Http::response(makeLoteEventos([]), 200)]);

    $chave = makeChaveAcesso();
    NfsenClient::for(makePfxContent(), 'secret', '9999999')->consultar()->situacaoCancelamento($chave);

    Http::assertSent(fn (Request $req): bool => $req->method() === 'GET'
        && str_ends_with($req->url(), '/NFSe/'.$chave.'/Eventos')
    );
});

// Rejeição do ADN não é situação da nota: devolvê-la como SemPedido faria uma
// consulta que falhou passar por "nada pendente".
it('lança quando o ADN rejeita a consulta, com o motivo informado', function () {
    Http::fake(['*' => Http::response([
        'StatusProcessamento' => 'REJEICAO',
        'LoteDFe' => [],
        'Alertas' => [],
        'Erros' => [['Codigo' => 'E9001', 'Descricao' => 'Chave de acesso inexistente']],
    ], 200)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->situacaoCancelamento(makeChaveAcesso()))
        ->toThrow(NfseException::class, 'O ADN rejeitou a consulta de eventos da NFS-e: Chave de acesso inexistente');
});

it('lança com motivo genérico quando a rejeição do ADN vem sem mensagem', function () {
    Http::fake(['*' => Http::response([
        'StatusProcessamento' => 'REJEICAO',
        'LoteDFe' => [],
        'Alertas' => [],
        'Erros' => [],
    ], 200)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->situacaoCancelamento(makeChaveAcesso()))
        ->toThrow(NfseException::class, 'O ADN rejeitou a consulta de eventos da NFS-e: motivo não informado.');
});

it('recusa chaveAcesso inválida antes de chamar o ADN', function () {
    Http::fake();

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->situacaoCancelamento('123'))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});
