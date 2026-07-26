<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Events\NfseCancelled;
use OwnerPro\Nfsen\Events\NfseFiscalAnalysisRequested;
use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\NfseCanceller;

covers(NfsenClient::class, NfseCanceller::class);

const CHAVE_ANALISE_FISCAL = '12345678901234567890123456789012345678901234567890';

it('solicitarAnaliseFiscalCancelamento envia o evento e101103', function () {
    Http::fake(['*' => Http::response(
        json_decode((string) file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        201
    )]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->solicitarAnaliseFiscalCancelamento(
        CHAVE_ANALISE_FISCAL,
        CodigoJustificativaCancelamento::ErroEmissao,
        'Prazo de cancelamento direto expirado'
    );

    expect($response->sucesso)->toBeTrue()
        ->and($response->chave)->toBe(CHAVE_ANALISE_FISCAL);

    Http::assertSent(function (Request $req): bool {
        $xml = (string) gzdecode((string) base64_decode((string) $req['pedidoRegistroEventoXmlGZipB64']));

        return $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/'.CHAVE_ANALISE_FISCAL.'/eventos'
            && str_contains($xml, '<e101103>')
            && str_contains($xml, '<xDesc>Solicitação de Análise Fiscal para Cancelamento de NFS-e</xDesc>')
            && str_contains($xml, 'Id="PRE'.CHAVE_ANALISE_FISCAL.'101103"');
    });
});

// A NFS-e segue válida até o fisco deferir: anunciar cancelamento aqui faria o
// consumidor baixar a nota de uma solicitação que ainda pode ser indeferida (evento
// 105105 em consultar()->eventos()).
it('solicitarAnaliseFiscalCancelamento dispara NfseFiscalAnalysisRequested, nunca NfseCancelled', function () {
    Event::fake();
    Http::fake(['*' => Http::response(
        json_decode((string) file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        201
    )]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $client->solicitarAnaliseFiscalCancelamento(
        CHAVE_ANALISE_FISCAL,
        CodigoJustificativaCancelamento::ErroEmissao,
        'Prazo de cancelamento direto expirado'
    );

    Event::assertDispatched(NfseFiscalAnalysisRequested::class, fn (NfseFiscalAnalysisRequested $e): bool => $e->chave === CHAVE_ANALISE_FISCAL);
    Event::assertNotDispatched(NfseCancelled::class);
    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e): bool => $e->operacao === 'solicitar_analise_fiscal');
});

it('solicitarAnaliseFiscalCancelamento reporta a rejeição da SEFIN', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['erro' => [['codigo' => 'E0900', 'descricao' => 'Solicitação já registrada']]], 200)]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->solicitarAnaliseFiscalCancelamento(
        CHAVE_ANALISE_FISCAL,
        CodigoJustificativaCancelamento::ErroEmissao,
        'Prazo de cancelamento direto expirado'
    );

    expect($response->sucesso)->toBeFalse()
        ->and($response->erros[0]->codigo)->toBe('E0900');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e): bool => $e->operacao === 'solicitar_analise_fiscal' && $e->codigoErro === 'E0900');
});

it('solicitarAnaliseFiscalCancelamento aceita codigoMotivo em string', function () {
    Http::fake(['*' => Http::response(
        json_decode((string) file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        201
    )]);

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->solicitarAnaliseFiscalCancelamento(CHAVE_ANALISE_FISCAL, '2', 'Servico nao foi prestado ao tomador');

    expect($response->sucesso)->toBeTrue();

    Http::assertSent(function (Request $req): bool {
        $xml = (string) gzdecode((string) base64_decode((string) $req['pedidoRegistroEventoXmlGZipB64']));

        return str_contains($xml, '<cMotivo>2</cMotivo>');
    });
});

it('solicitarAnaliseFiscalCancelamento recusa chaveAcesso inválida', function () {
    Http::fake();

    $client = NfsenClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->solicitarAnaliseFiscalCancelamento('123', CodigoJustificativaCancelamento::ErroEmissao, 'Prazo de cancelamento expirado'))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});
