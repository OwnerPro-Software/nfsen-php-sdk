<?php

use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Responses\DistribuicaoResponse;
use OwnerPro\Nfsen\Responses\DocumentoFiscal;
use OwnerPro\Nfsen\Responses\HttpResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;

covers(DistribuicaoResponse::class);

it('constructs with all fields', function () {
    $doc = new DocumentoFiscal(1, makeChaveAcesso(), TipoDocumentoFiscal::Nfse, null, '<NFSe/>', '2026-04-08T14:30:00');
    $alerta = new ProcessingMessage(codigo: 'A001');
    $erro = new ProcessingMessage(codigo: 'E001');

    $response = new DistribuicaoResponse(
        sucesso: true,
        statusProcessamento: StatusDistribuicao::DocumentosLocalizados,
        lote: [$doc],
        alertas: [$alerta],
        erros: [$erro],
        tipoAmbiente: 2,
        versaoAplicativo: '1.0.0',
        dataHoraProcessamento: '2026-04-08T14:30:00',
    );

    expect($response)
        ->sucesso->toBeTrue()
        ->statusProcessamento->toBe(StatusDistribuicao::DocumentosLocalizados)
        ->lote->toHaveCount(1)
        ->alertas->toHaveCount(1)
        ->erros->toHaveCount(1)
        ->tipoAmbiente->toBe(2)
        ->versaoAplicativo->toBe('1.0.0')
        ->dataHoraProcessamento->toBe('2026-04-08T14:30:00');
});

it('defaults optional collections to empty', function () {
    $response = new DistribuicaoResponse(
        sucesso: false,
        statusProcessamento: StatusDistribuicao::NenhumDocumentoLocalizado,
        lote: [],
        alertas: [],
        erros: [],
        tipoAmbiente: null,
        versaoAplicativo: null,
        dataHoraProcessamento: null,
    );

    expect($response)
        ->sucesso->toBeFalse()
        ->lote->toBeEmpty()
        ->alertas->toBeEmpty()
        ->erros->toBeEmpty()
        ->tipoAmbiente->toBeNull()
        ->versaoAplicativo->toBeNull()
        ->dataHoraProcessamento->toBeNull();
});

it('creates from API result with documents', function () {
    $xml = '<NFSe/>';
    $gzipB64 = base64_encode((string) gzencode($xml));

    $result = [
        'StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS',
        'LoteDFe' => [
            ['NSU' => 1, 'ChaveAcesso' => makeChaveAcesso(), 'TipoDocumento' => 'NFSE', 'ArquivoXml' => $gzipB64, 'DataHoraGeracao' => '2026-04-08T14:30:00'],
            ['NSU' => 2, 'TipoDocumento' => 'EVENTO', 'TipoEvento' => 'CANCELAMENTO', 'ArquivoXml' => $gzipB64],
        ],
        'Alertas' => [['Codigo' => 'A001', 'Descricao' => 'Alerta']],
        'Erros' => [],
        'TipoAmbiente' => 'PRODUCAO',
        'VersaoAplicativo' => '2.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];

    $response = DistribuicaoResponse::fromApiResult($result);

    expect($response)
        ->sucesso->toBeTrue()
        ->statusProcessamento->toBe(StatusDistribuicao::DocumentosLocalizados)
        ->lote->toHaveCount(2)
        ->alertas->toHaveCount(1)
        ->erros->toBeEmpty()
        ->tipoAmbiente->toBe(1)
        ->versaoAplicativo->toBe('2.0')
        ->dataHoraProcessamento->toBe('2026-04-08T15:00:00');

    expect($response->lote[0])
        ->nsu->toBe(1)
        ->tipoDocumento->toBe(TipoDocumentoFiscal::Nfse)
        ->arquivoXml->toBe($xml);
});

it('creates from API result with no documents', function () {
    $result = [
        'StatusProcessamento' => 'NENHUM_DOCUMENTO_LOCALIZADO',
        'LoteDFe' => null,
        'Alertas' => null,
        'Erros' => null,
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];

    $response = DistribuicaoResponse::fromApiResult($result);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::NenhumDocumentoLocalizado)
        ->lote->toBeEmpty()
        ->alertas->toBeEmpty()
        ->erros->toBeEmpty()
        ->tipoAmbiente->toBe(2);
});

it('creates from API result with rejection', function () {
    $result = [
        'StatusProcessamento' => 'REJEICAO',
        'LoteDFe' => null,
        'Alertas' => null,
        'Erros' => [['Codigo' => 'E001', 'Descricao' => 'CNPJ inválido']],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];

    $response = DistribuicaoResponse::fromApiResult($result);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('CNPJ inválido');
});

it('maps TipoAmbiente PRODUCAO to 1', function () {
    $result = [
        'StatusProcessamento' => 'NENHUM_DOCUMENTO_LOCALIZADO',
        'TipoAmbiente' => 'PRODUCAO',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];

    expect(DistribuicaoResponse::fromApiResult($result)->tipoAmbiente)->toBe(1);
});

it('maps TipoAmbiente HOMOLOGACAO to 2', function () {
    $result = [
        'StatusProcessamento' => 'NENHUM_DOCUMENTO_LOCALIZADO',
        'TipoAmbiente' => 'HOMOLOGACAO',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];

    expect(DistribuicaoResponse::fromApiResult($result)->tipoAmbiente)->toBe(2);
});

it('maps unknown TipoAmbiente to null', function () {
    $result = [
        'StatusProcessamento' => 'NENHUM_DOCUMENTO_LOCALIZADO',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];

    expect(DistribuicaoResponse::fromApiResult($result)->tipoAmbiente)->toBeNull();
});

it('returns rejection response when StatusProcessamento key is missing', function () {
    $response = DistribuicaoResponse::fromApiResult([]);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->lote->toBeEmpty()
        ->erros->toHaveCount(1);
    expect($response->erros[0])
        ->codigo->toBe('INVALID_RESPONSE')
        ->descricao->toBe('Campo StatusProcessamento ausente ou inválido. Keys: []')
        ->complemento->toBe('[]');
});

it('includes raw API response when StatusProcessamento is invalid', function () {
    $result = ['descrição' => 'caminho/para/recurso', 'StatusProcessamento' => 'UNKNOWN_VALUE'];

    $response = DistribuicaoResponse::fromApiResult($result);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0])
        ->codigo->toBe('INVALID_RESPONSE')
        ->descricao->toBe('Campo StatusProcessamento ausente ou inválido. Keys: [descrição, StatusProcessamento]')
        ->complemento->toBe('{"descrição":"caminho/para/recurso","StatusProcessamento":"UNKNOWN_VALUE"}');
});

it('fromHttpResponse delegates to fromApiResult on 2xx with valid JSON', function () {
    $json = [
        'StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS',
        'LoteDFe' => [],
        'Alertas' => [],
        'Erros' => [],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];

    $httpResponse = new HttpResponse(200, $json, json_encode($json));

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response)
        ->sucesso->toBeTrue()
        ->statusProcessamento->toBe(StatusDistribuicao::DocumentosLocalizados);
});

it('fromHttpResponse returns EMPTY_RESPONSE on 2xx with empty body', function () {
    $httpResponse = new HttpResponse(200, [], '');

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0])
        ->codigo->toBe('EMPTY_RESPONSE')
        ->descricao->toBe('A API retornou HTTP 200 com corpo vazio.');
});

it('fromHttpResponse returns EMPTY_RESPONSE on 204 with empty body', function () {
    $httpResponse = new HttpResponse(204, [], '');

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0])
        ->codigo->toBe('EMPTY_RESPONSE')
        ->descricao->toBe('A API retornou HTTP 204 com corpo vazio.');
});

it('fromHttpResponse returns HTTP error on 429 with text body', function () {
    $httpResponse = new HttpResponse(429, [], 'Rate limit exceeded');

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0])
        ->codigo->toBe('HTTP_429')
        ->descricao->toBe('A API retornou HTTP 429.')
        ->complemento->toBe('Rate limit exceeded');
});

it('fromHttpResponse returns HTTP error on 500 with JSON body', function () {
    $json = ['error' => 'Internal Server Error'];
    $body = json_encode($json);

    $httpResponse = new HttpResponse(500, $json, $body);

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0])
        ->codigo->toBe('HTTP_500')
        ->descricao->toBe('A API retornou HTTP 500.')
        ->complemento->toBe($body);
});

it('fromHttpResponse treats 299 as 2xx', function () {
    $httpResponse = new HttpResponse(299, [], '');

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response->erros[0])->codigo->toBe('EMPTY_RESPONSE');
});

it('fromHttpResponse returns HTTP error on 300 boundary', function () {
    $httpResponse = new HttpResponse(300, [], '');

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0])
        ->codigo->toBe('HTTP_300');
});

it('fromHttpResponse returns HTTP error on 302 redirect', function () {
    $httpResponse = new HttpResponse(302, [], '');

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0])
        ->codigo->toBe('HTTP_302')
        ->descricao->toBe('A API retornou HTTP 302.')
        ->complemento->toBeNull();
});

it('fromHttpResponse parses structured ADN error on non-2xx with StatusProcessamento', function () {
    $json = [
        'StatusProcessamento' => 'REJEICAO',
        'Erros' => [['Codigo' => 'E001', 'Descricao' => 'CNPJ inválido']],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];
    $body = json_encode($json);

    $httpResponse = new HttpResponse(400, $json, $body);

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('CNPJ inválido');
});

it('keeps the whole batch when a single document cannot be parsed', function () {
    // Antes, um item ruim lançava e o chamador perdia os outros 49 do lote junto.
    $gzipB64 = base64_encode((string) gzencode('<NFSe/>'));

    $response = DistribuicaoResponse::fromApiResult([
        'StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS',
        'LoteDFe' => [
            ['NSU' => 1, 'TipoDocumento' => 'NFSE', 'ArquivoXml' => $gzipB64],
            ['NSU' => 2, 'TipoDocumento' => 'NFSE', 'ArquivoXml' => 'nao-e-base64-valido!!'],
            ['NSU' => 3, 'TipoDocumento' => 'NFSE', 'ArquivoXml' => $gzipB64],
        ],
    ]);

    expect($response->sucesso)->toBeTrue()
        ->and($response->lote)->toHaveCount(3)
        ->and($response->lote[0]->arquivoXml)->toBe('<NFSe/>')
        ->and($response->lote[1]->arquivoXml)->toBeNull()
        ->and($response->lote[1]->nsu)->toBe(2)
        ->and($response->lote[1]->parseError)->not->toBeNull()
        ->and($response->lote[2]->arquivoXml)->toBe('<NFSe/>');
});

it('delivers the rest of the page when one document carries an unreadable field', function () {
    // Tolerância, não contrato: o swagger declara o tipo de cada campo. Sem a guarda,
    // porém, uma resposta fora dele virava TypeError dentro do array_map e derrubava a
    // página inteira — inclusive os NSU de que o chamador precisa para refazer a busca.
    $response = DistribuicaoResponse::fromApiResult([
        'StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS',
        'LoteDFe' => [
            ['NSU' => 1, 'ChaveAcesso' => makeChaveAcesso(), 'TipoDocumento' => 'NFSE'],
            ['NSU' => '2', 'ChaveAcesso' => ['quebrado'], 'TipoDocumento' => 'NFSE'],
            ['NSU' => 3, 'TipoDocumento' => 'EVENTO', 'TipoEvento' => 'CANCELAMENTO'],
        ],
    ]);

    expect($response->lote)->toHaveCount(3)
        ->and(array_map(fn ($doc) => $doc->nsu, $response->lote))->toBe([1, 2, 3])
        ->and($response->lote[0]->parseError)->toBeNull()
        ->and($response->lote[1]->parseError)->toContain('Campo ChaveAcesso veio como array')
        ->and($response->lote[2]->parseError)->toBeNull();
});

it('drops a lote entry that is not an object at all', function () {
    // O swagger declara LoteDFe como array de DistribuicaoNSU. Um escalar não traz nsu
    // nem chave: não há o que preservar dele, e passá-lo adiante custaria a página. Com o descarte vindo antes do documento bom,
    // o lote só continua indexado a partir de zero se as chaves forem refeitas.
    $response = DistribuicaoResponse::fromApiResult([
        'StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS',
        'LoteDFe' => ['lixo', 7, ['NSU' => 1, 'TipoDocumento' => 'NFSE']],
    ]);

    expect($response->lote)->toHaveCount(1)
        ->and(array_keys($response->lote))->toBe([0])
        ->and($response->lote[0]->nsu)->toBe(1);
});

it('treats a LoteDFe that is not a list as an empty page', function () {
    $response = DistribuicaoResponse::fromApiResult([
        'StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS',
        'LoteDFe' => 'nada disso',
    ]);

    expect($response->lote)->toBeEmpty();
});
