<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\NfsenClient;

covers(NfsenClient::class);

function makeDistribuicaoApiResponse(string $status = 'DOCUMENTOS_LOCALIZADOS', ?array $lote = null): array
{
    $xml = '<NFSe/>';
    $gzipB64 = base64_encode((string) gzencode($xml));

    return [
        'StatusProcessamento' => $status,
        'LoteDFe' => $lote ?? [
            ['NSU' => 1, 'ChaveAcesso' => makeChaveAcesso(), 'TipoDocumento' => 'NFSE', 'ArquivoXml' => $gzipB64, 'DataHoraGeracao' => '2026-04-08T14:30:00'],
        ],
        'Alertas' => [],
        'Erros' => [],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];
}

it('distribuicao() returns DistributesNfse', function () {
    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    expect($client->distribuicao())->toBeInstanceOf(DistributesNfse::class);
});

it('distribuicao()->documentos returns lote with documents', function () {
    Http::fake(['*' => Http::response(makeDistribuicaoApiResponse(), 200)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->distribuicao()->documentos(0);

    expect($response->sucesso)->toBeTrue();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::DocumentosLocalizados);
    expect($response->lote)->toHaveCount(1);
    expect($response->lote[0]->tipoDocumento)->toBe(TipoDocumentoFiscal::Nfse);
    expect($response->lote[0]->arquivoXml)->toBe('<NFSe/>');

    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'adn.producaorestrita.nfse.gov.br/contribuintes/DFe/0') &&
        str_contains($req->url(), 'lote=true') &&
        $req->method() === 'GET'
    );
});

it('distribuicao()->documento sends lote=false', function () {
    Http::fake(['*' => Http::response(makeDistribuicaoApiResponse(), 200)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $client->distribuicao()->documento(42);

    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'DFe/42') &&
        str_contains($req->url(), 'lote=false')
    );
});

it('distribuicao()->documentos with custom cnpjConsulta', function () {
    Http::fake(['*' => Http::response(makeDistribuicaoApiResponse(), 200)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $client->distribuicao()->documentos(0, '99999999000100');

    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'cnpjConsulta=99999999000100'));
});

it('distribuicao()->eventos returns events for chave', function () {
    Http::fake(['*' => Http::response(makeDistribuicaoApiResponse(), 200)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $response = $client->distribuicao()->eventos($chave);

    expect($response->sucesso)->toBeTrue();

    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'adn.producaorestrita.nfse.gov.br/contribuintes/NFSe/'.$chave.'/Eventos') &&
        $req->method() === 'GET'
    );
});

it('distribuicao()->documentos handles no documents found', function () {
    Http::fake(['*' => Http::response(makeDistribuicaoApiResponse('NENHUM_DOCUMENTO_LOCALIZADO', []), 200)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->distribuicao()->documentos(999);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::NenhumDocumentoLocalizado);
    expect($response->lote)->toBeEmpty();
});

it('distribuicao()->documentos handles rejection on HTTP 400', function () {
    Http::fake(['*' => Http::response([
        'StatusProcessamento' => 'REJEICAO',
        'Erros' => [['Codigo' => 'E001', 'Descricao' => 'CNPJ inválido']],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ], 400)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->distribuicao()->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->descricao)->toBe('CNPJ inválido');
});

it('distribuicao()->documentos handles server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->distribuicao()->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->mensagem)->toBe('HTTP error: 500');
});

it('distribuicao()->eventos throws on invalid chave', function () {
    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->distribuicao()->eventos('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('distribuicao()->documentos preserves HTTP status code on 429', function () {
    Http::fake(['*' => Http::response('Too Many Requests', 429)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->distribuicao()->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0])
        ->codigo->toBe('HTTP_429')
        ->complemento->toBe('Too Many Requests');
});

it('distribuicao()->documentos handles empty 200 response', function () {
    Http::fake(['*' => Http::response(null, 200)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->distribuicao()->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0])->codigo->toBe('EMPTY_RESPONSE');
});

it('distribuicao()->documentos handles 302 redirect', function () {
    Http::fake(['*' => Http::response(null, 302, ['Location' => 'https://other.com'])]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->distribuicao()->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0])->codigo->toBe('HTTP_302');
});
