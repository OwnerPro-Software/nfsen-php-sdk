<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\Decorators\Concerns\AttachesDanfsePdf;
use OwnerPro\Nfsen\Operations\NfseConsulter;
use OwnerPro\Nfsen\Operations\NfseEmitter;
use OwnerPro\Nfsen\Pipeline\NfseResponsePipeline;
use OwnerPro\Nfsen\Responses\DistribuicaoResponse;

covers(
    NfsenClient::class,
    NfseEmitter::class,
    NfseConsulter::class,
    NfseResponsePipeline::class,
    DistribuicaoResponse::class,
    AttachesDanfsePdf::class,
);

/**
 * O `raw` existe para o que a normalização não alcança: envelope renomeado, campo
 * novo, mensagem em forma desconhecida. Por isso cada corpo abaixo traz uma chave que
 * o SDK não lê — se o payload chegasse filtrado pelos campos mapeados, o campo serviria
 * só para repetir o que já está tipado.
 */
function makeRawClient(array $body, int $status): NfsenClient
{
    Http::fake(['*' => Http::response($body, $status)]);

    return NfsenClient::forStandalone(makeIcpBrPfxContent(), 'secret', '9999999', validateIdentity: false);
}

function makeRawGzip(string $xml = '<X/>'): string
{
    return base64_encode((string) gzencode($xml));
}

it('emitir preserva o corpo decodificado', function (DpsData $data, array $body, int $status, bool $sucesso) {
    $response = makeRawClient($body, $status)->emitir($data);

    expect($response->sucesso)->toBe($sucesso)
        ->and($response->raw)->toBe($body);
})->with('dpsData')->with([
    'sucesso' => [['chaveAcesso' => 'CHAVE_RAW', 'nfseXmlGZipB64' => makeRawGzip(), 'campoDesconhecido' => 'x'], 201, true],
    'rejeição' => [['erro' => [['codigo' => 'E0822', 'descricao' => 'Prazo expirado']], 'campoDesconhecido' => 'x'], 200, false],
    'sem chaveAcesso' => [['idDps' => 'DPS1', 'campoDesconhecido' => 'x'], 200, false],
]);

it('cancelar preserva o corpo decodificado', function (array $body, int $status, bool $sucesso) {
    $response = makeRawClient($body, $status)->cancelar(
        makeChaveAcesso(),
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal',
    );

    expect($response->sucesso)->toBe($sucesso)
        ->and($response->raw)->toBe($body);
})->with([
    'sucesso' => [['eventoXmlGZipB64' => makeRawGzip(), 'campoDesconhecido' => 'x'], 201, true],
    'rejeição' => [['erro' => [['codigo' => 'E0840', 'descricao' => 'Evento já vinculado']], 'campoDesconhecido' => 'x'], 200, false],
]);

it('consultar()->nfse preserva o corpo decodificado', function (array $body, int $status, bool $sucesso) {
    $response = makeRawClient($body, $status)->consultar()->nfse(makeChaveAcesso());

    expect($response->sucesso)->toBe($sucesso)
        ->and($response->raw)->toBe($body);
})->with([
    'sucesso' => [['chaveAcesso' => 'CHAVE_RAW', 'nfseXmlGZipB64' => makeRawGzip(), 'campoDesconhecido' => 'x'], 200, true],
    'rejeição' => [['erro' => [['codigo' => 'E999', 'descricao' => 'Nota inexistente']], 'campoDesconhecido' => 'x'], 200, false],
]);

it('consultar()->dps preserva o corpo decodificado', function (array $body, int $status, bool $sucesso) {
    $response = makeRawClient($body, $status)->consultar()->dps('DPS123');

    expect($response->sucesso)->toBe($sucesso)
        ->and($response->raw)->toBe($body);
})->with([
    'sucesso' => [['chaveAcesso' => 'CHAVE_RAW', 'campoDesconhecido' => 'x'], 200, true],
    'rejeição' => [['erro' => [['codigo' => 'E999', 'descricao' => 'DPS rejeitada']], 'campoDesconhecido' => 'x'], 200, false],
    '404' => [['campoDesconhecido' => 'x'], 404, false],
]);

it('consultar()->eventos preserva o corpo decodificado', function (array $body, int $status, bool $sucesso) {
    $response = makeRawClient($body, $status)->consultar()->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBe($sucesso)
        ->and($response->raw)->toBe($body);
})->with([
    'sucesso na forma do SefinNacional' => [['eventos' => [['arquivoXml' => base64_encode(makeRawGzip())]], 'campoDesconhecido' => 'x'], 200, true],
    'sucesso na forma do swagger' => [['eventoXmlGZipB64' => makeRawGzip(), 'campoDesconhecido' => 'x'], 200, true],
    'rejeição' => [['erro' => [['codigo' => 'E999', 'descricao' => 'Evento rejeitado']], 'campoDesconhecido' => 'x'], 200, false],
    '404' => [['campoDesconhecido' => 'x'], 404, false],
]);

it('distribuicao preserva o corpo decodificado', function () {
    $body = [
        'StatusProcessamento' => 'NENHUM_DOCUMENTO_LOCALIZADO',
        'LoteDFe' => [],
        'Alertas' => [],
        'Erros' => [],
        'campoDesconhecido' => 'x',
    ];

    $response = makeRawClient($body, 200)->distribuicao()->documentos(0);

    expect($response->raw)->toBe($body);
});

// O envelope irreconhecível é o caso que o campo existe para servir: a normalização
// não tem o que ler, e o corpo cru é a única informação que resta.
it('distribuicao preserva o corpo decodificado quando o envelope é irreconhecível', function () {
    $body = ['status' => 'algo que o ADN nunca mandou'];

    $response = makeRawClient($body, 200)->distribuicao()->documentos(0);

    expect($response->sucesso)->toBeFalse()
        ->and($response->erros[0]->codigo)->toBe('INVALID_RESPONSE')
        ->and($response->raw)->toBe($body);
});

it('distribuicao devolve raw vazio quando o corpo não é JSON legível', function () {
    Http::fake(['*' => Http::response('<html>gateway</html>', 502)]);

    $client = NfsenClient::forStandalone(makeIcpBrPfxContent(), 'secret', '9999999', validateIdentity: false);
    $response = $client->distribuicao()->documentos(0);

    expect($response->sucesso)->toBeFalse()
        ->and($response->raw)->toBe([]);
});

it('distribuicao devolve raw vazio quando o 2xx vem com corpo vazio', function () {
    Http::fake(['*' => Http::response('', 204)]);

    $client = NfsenClient::forStandalone(makeIcpBrPfxContent(), 'secret', '9999999', validateIdentity: false);
    $response = $client->distribuicao()->documentos(0);

    expect($response->erros[0]->codigo)->toBe('EMPTY_RESPONSE')
        ->and($response->raw)->toBe([]);
});

it('o DANFSe automático não descarta o corpo decodificado', function (DpsData $data) {
    $body = [
        'chaveAcesso' => '33033021211222333000181000000000001026010000010000',
        'nfseXmlGZipB64' => makeRawGzip((string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-autorizada.xml')),
        'campoDesconhecido' => 'x',
    ];
    Http::fake(['*' => Http::response($body, 201)]);

    $client = NfsenClient::forStandalone(
        pfxContent: makePfxContent(),
        senha: 'secret',
        prefeitura: '9999999',
        validateIdentity: false,
        danfse: true,
    );

    $response = $client->emitir($data);

    expect($response->pdf)->not->toBeNull()
        ->and($response->raw)->toBe($body);
})->with('dpsData');
