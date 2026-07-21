<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Adapters\NfseHttpClient;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Exceptions\RequestNotDeliveredException;
use OwnerPro\Nfsen\Support\TempFileFactory;

it('posts json payload to given url', function () {
    Http::fake(['*' => Http::response(['sucesso' => true], 201)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $response = $client->post('https://example.com/nfse', ['key' => 'value']);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://example.com/nfse' &&
        $req->isJson()
    );

    expect($response)->toBe(['sucesso' => true]);
});

it('performs GET request', function () {
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $response = $client->get('https://example.com/nfse/CHAVE123');

    expect($response)->toHaveKey('nfseXmlGZipB64');
});

it('getResponse treats an empty 204 as no content, not an unreadable body', function () {
    Http::fake(['*' => Http::response(null, 204)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $response = $client->getResponse('https://example.com/contribuintes/DFe/0');

    expect($response->statusCode)->toBe(204)
        ->and($response->json)->toBe([])
        ->and($response->body)->toBe('');
});

it('getResponse keeps a 200 with an empty body indeterminate', function () {
    // Só o 204 define corpo vazio; num 200 o corpo ausente segue ininterpretável.
    Http::fake(['*' => Http::response(null, 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->getResponse('https://example.com/contribuintes/DFe/0'))
        ->toThrow(IndeterminateResultException::class);
});

it('returns parsed JSON on 5xx when the body carries a SEFIN rejection', function () {
    // Envelope de erro estruturado prova que a requisição chegou à SEFIN, foi
    // processada e rejeitada — definitivo, apesar do 5xx.
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'Falha', 'codigo' => 'E500']]], 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $result = $client->post('https://example.com/nfse', []);

    expect($result)->toHaveKey('erros')
        ->and($result['erros'][0]['codigo'])->toBe('E500');
});

it('throws IndeterminateResultException on POST 5xx without a SEFIN rejection', function (mixed $body, string $rotulo) {
    // Sem rejeição estruturada, um 5xx não distingue "proxy falhou antes da SEFIN"
    // de "SEFIN gravou a nota e falhou depois". Tratar como definitivo autorizaria
    // o caller a reemitir com o mesmo nDPS.
    Http::fake(['*' => Http::response($body, 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(IndeterminateResultException::class, 'sem rejeição estruturada da SEFIN')
        ->and($rotulo)->not->toBeEmpty();
})->with([
    'corpo JSON de gateway' => [['message' => 'Internal server error'], 'gateway'],
    'corpo não-JSON' => ['Server Error', 'html'],
    'envelope de erro vazio' => [['erro' => []], 'vazio'],
]);

it('reports no transport phase for a server error, since the response arrived intact', function () {
    Http::fake(['*' => Http::response(['message' => 'Bad gateway'], 502)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    try {
        $client->post('https://example.com/nfse', []);
        test()->fail('Expected IndeterminateResultException');
    } catch (IndeterminateResultException $e) {
        expect($e->phase)->toBeNull()
            ->and($e->getMessage())->toContain('502')
            ->and($e->getMessage())->toContain('Bad gateway');
    }
});

it('keeps throwing HttpException on GET 5xx, which changes no state', function () {
    // Consulta não tem efeito colateral: não há o que reconciliar, e o erro
    // definitivo é a informação mais útil para o caller.
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    try {
        $client->get('https://example.com/nfse/CHAVE123');
        test()->fail('Expected HttpException');
    } catch (HttpException $e) {
        expect($e->getMessage())->toBe('HTTP error: 500');
        expect($e->getResponseBody())->toContain('Server Error');
    }
});

it('returns a non-envelope JSON body on 4xx, a definitive client error', function () {
    // 4xx é definitivo mesmo sem envelope da SEFIN: o servidor recusou a requisição.
    // O corpo volta ao chamador, que o classifica (sem chaveAcesso → sucesso: false).
    Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect($client->post('https://example.com/nfse', []))->toBe(['message' => 'Unauthorized']);
});

it('returns a non-envelope JSON body on GET 5xx, which changes no state', function () {
    Http::fake(['*' => Http::response(['message' => 'Internal server error'], 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect($client->get('https://example.com/nfse/CHAVE123'))->toBe(['message' => 'Internal server error']);
});

it('keeps throwing HttpException on POST 4xx, a definitive client error', function () {
    Http::fake(['*' => Http::response('Not Found', 404)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(HttpException::class, 'HTTP error: 404');
});

it('returns parsed JSON on 4xx response instead of throwing', function () {
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'Unauthorized', 'codigo' => 'E401']], 401)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $result = $client->post('https://example.com/nfse', []);

    expect($result)->toHaveKey('erro');
    expect($result['erro']['descricao'])->toBe('Unauthorized');
});

it('passes mTLS options and payload to HTTP client', function () {
    Http::fake(['*' => Http::response(['sucesso' => true], 201)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, sslVerify: false);
    $client->post('https://example.com/nfse', ['dps' => 'xml']);

    // Laravel HTTP fake exposes url, method, headers, and body.
    // Guzzle-level options (cert, ssl_key, verify) cannot be asserted
    // through Http::assertSent -- mTLS is validated by integration tests.
    Http::assertSent(fn (Request $req) => $req->url() === 'https://example.com/nfse' &&
        $req->method() === 'POST' &&
        $req->isJson() &&
        $req['dps'] === 'xml'
    );
});

it('throws NfseException when tmpfile fails for both handles', function () {
    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturn(false);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'arquivos temporários');
});

it('throws NfseException and closes first handle when second tmpfile fails', function () {
    $realHandle = tmpfile();
    $callCount = 0;

    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(function () use (&$callCount, $realHandle) {
        $callCount++;

        return $callCount === 1 ? $realHandle : false;
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'arquivos temporários');
});

it('throws NfseException and closes second handle when first tmpfile fails', function () {
    $realHandle = tmpfile();
    $callCount = 0;

    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(function () use (&$callCount, $realHandle) {
        $callCount++;

        return $callCount === 1 ? false : $realHandle;
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'arquivos temporários');
});

it('head returns 200 status code', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $status = $client->head('https://example.com/dps/DPS123');

    expect($status)->toBe(200);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://example.com/dps/DPS123' &&
        $req->method() === 'HEAD'
    );
});

it('head returns 404 status code', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $status = $client->head('https://example.com/dps/NONEXISTENT');

    expect($status)->toBe(404);
});

it('head throws HttpException on 5xx', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->head('https://example.com/dps/DPS123'))
        ->toThrow(HttpException::class);
});

it('head throws NfseException when tmpfile fails', function () {
    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturn(false);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => $client->head('https://example.com/dps/DPS123'))
        ->toThrow(NfseException::class, 'arquivos temporários');
});

it('does not follow redirects on post and throws HttpException', function () {
    Http::fake(['*' => Http::response(null, 302, ['Location' => 'https://attacker.com/capture'])]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->post('https://example.com/nfse', ['dps' => 'xml']))
        ->toThrow(HttpException::class, 'HTTP error: 302');

    Http::assertSentCount(1);
});

it('does not follow redirects on get and throws HttpException', function () {
    Http::fake(['*' => Http::response(null, 302, ['Location' => 'https://attacker.com/capture'])]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->get('https://example.com/nfse/CHAVE123'))
        ->toThrow(HttpException::class, 'HTTP error: 302');

    Http::assertSentCount(1);
});

it('does not follow redirects on head', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://attacker.com/capture'])]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $status = $client->head('https://example.com/dps/DPS123');

    expect($status)->toBe(302);

    Http::assertSentCount(1);
});

it('throws NfseException when fwrite fails on read-only handle', function () {
    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(fn () => fopen('php://memory', 'r'));

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => @$client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'escrever certificado');
});

it('throws when only one fwrite fails', function () {
    $callCount = 0;

    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(function () use (&$callCount) {
        $callCount++;

        return $callCount === 1 ? tmpfile() : fopen('php://memory', 'r');
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => @$client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'escrever certificado');
});

it('throws HttpException on 4xx with empty body', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(HttpException::class, 'HTTP error: 404');
});

it('closes first handle when second tmpfile fails', function () {
    $realHandle = tmpfile();
    $callCount = 0;

    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(function () use (&$callCount, $realHandle) {
        return ++$callCount === 1 ? $realHandle : false;
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    try {
        $client->post('https://example.com/nfse', []);
    } catch (NfseException) {
        // expected
    }

    expect(is_resource($realHandle))->toBeFalse();
});

it('closes second handle when first tmpfile fails', function () {
    $realHandle = tmpfile();
    $callCount = 0;

    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(function () use (&$callCount, $realHandle) {
        return ++$callCount === 1 ? false : $realHandle;
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    try {
        $client->post('https://example.com/nfse', []);
    } catch (NfseException) {
        // expected
    }

    expect(is_resource($realHandle))->toBeFalse();
});

it('closes certificate handles after successful request', function () {
    Http::fake(['*' => Http::response(['ok' => true])]);

    $handles = [];
    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(function () use (&$handles) {
        $handle = tmpfile();
        $handles[] = $handle;

        return $handle;
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);
    $client->post('https://example.com/nfse', []);

    expect(is_resource($handles[0]))->toBeFalse()
        ->and(is_resource($handles[1]))->toBeFalse();
});

it('sets certificate file permissions to 0600', function () {
    $paths = [];
    $tempFiles = [];
    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(function () use (&$paths, &$tempFiles) {
        $path = tempnam(sys_get_temp_dir(), 'nfse_perm_');
        chmod($path, 0644);
        $handle = fopen($path, 'w+b');
        $paths[] = $path;
        $tempFiles[] = $path;

        return $handle;
    });

    $certPerms = null;
    $keyPerms = null;
    Http::fake(function () use (&$paths, &$certPerms, &$keyPerms) {
        $certPerms = fileperms($paths[0]) & 0777;
        $keyPerms = fileperms($paths[1]) & 0777;

        return Http::response(['ok' => true]);
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);
    $client->post('https://example.com/nfse', []);

    foreach ($tempFiles as $f) {
        @unlink($f);
    }

    expect($certPerms)->toBe(0600)
        ->and($keyPerms)->toBe(0600);
});

it('passes ssl verify and certificate options for post requests', function () {
    $capturedOptions = null;
    Http::fake(function ($request, $options) use (&$capturedOptions) {
        $capturedOptions = $options;

        return Http::response(['ok' => true]);
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, sslVerify: false);
    $client->post('https://example.com/nfse', []);

    expect($capturedOptions)
        ->toHaveKey('verify', false)
        ->toHaveKey('cert')
        ->toHaveKey('ssl_key');
});

it('passes ssl verify and certificate options for head requests', function () {
    $capturedOptions = null;
    Http::fake(function ($request, $options) use (&$capturedOptions) {
        $capturedOptions = $options;

        return Http::response('', 200);
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, sslVerify: false);
    $client->head('https://example.com/dps/DPS123');

    expect($capturedOptions)
        ->toHaveKey('verify', false)
        ->toHaveKey('cert')
        ->toHaveKey('ssl_key');
});

it('getBytes returns raw response body', function () {
    Http::fake(['*' => Http::response('PDF-BINARY-CONTENT', 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $result = $client->getBytes('https://example.com/danfse/CHAVE123');

    expect($result)->toBe('PDF-BINARY-CONTENT');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://example.com/danfse/CHAVE123' &&
        $req->method() === 'GET'
    );
});

it('getBytes throws HttpException on 4xx', function () {
    Http::fake(['*' => Http::response('Not Found', 404)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->getBytes('https://example.com/danfse/INVALID'))
        ->toThrow(HttpException::class, 'HTTP error: 404');
});

it('getBytes throws HttpException on 5xx', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->getBytes('https://example.com/danfse/CHAVE123'))
        ->toThrow(HttpException::class, 'HTTP error: 500');
});

it('getBytes does not follow redirects', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://attacker.com/capture'])]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->getBytes('https://example.com/danfse/CHAVE123'))
        ->toThrow(HttpException::class);

    Http::assertSentCount(1);
});

it('getBytes does not send Accept: application/json header', function () {
    Http::fake(['*' => Http::response('PDF-CONTENT', 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $client->getBytes('https://example.com/danfse/CHAVE123');

    Http::assertSent(fn (Request $req) => ! str_contains($req->header('Accept')[0] ?? '', 'application/json'));
});

it('getResponse returns HttpResponse with status, json, and body on 200', function () {
    $jsonBody = ['StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS', 'LoteDFe' => []];
    Http::fake(['*' => Http::response($jsonBody, 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(200)
        ->body->toBe(json_encode($jsonBody));
    expect($response->json)->toBe($jsonBody);
});

it('getResponse returns HttpResponse on 429 with text body', function () {
    Http::fake(['*' => Http::response('Rate limit exceeded', 429)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(429)
        ->body->toBe('Rate limit exceeded');
    expect($response->json)->toBe([]);
});

it('getResponse returns HttpResponse on 302 redirect without following', function () {
    Http::fake(['*' => Http::response(null, 302, ['Location' => 'https://other.com'])]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(302);
    expect($response->json)->toBe([]);

    Http::assertSentCount(1);
});

it('getResponse returns HttpResponse on 500 with json body', function () {
    $errorBody = ['error' => 'Internal Server Error'];
    Http::fake(['*' => Http::response($errorBody, 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(500)
        ->body->toBe(json_encode($errorBody));
    expect($response->json)->toBe($errorBody);
});

it('converts connection failure into IndeterminateResultException on every request path', function (string $path) {
    Http::fake(['*' => function (): never {
        throw new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds with 0 bytes received');
    }]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $call = match ($path) {
        'post' => fn () => $client->post('https://example.com/nfse', []),
        'get' => fn () => $client->get('https://example.com/nfse/CHAVE123'),
        'getBytes' => fn () => $client->getBytes('https://example.com/danfse/CHAVE123'),
        'getResponse' => fn () => $client->getResponse('https://example.com/adn/DFe/0'),
        'head' => fn () => $client->head('https://example.com/dps/DPS123'),
    };

    try {
        $call();
        test()->fail('Expected IndeterminateResultException');
    } catch (IndeterminateResultException $e) {
        expect($e->getPrevious())->toBeInstanceOf(ConnectionException::class)
            ->and($e->phase)->toBe('read')
            ->and($e->getMessage())->toContain('Resultado indeterminado');
    }
})->with(['post', 'get', 'getBytes', 'getResponse', 'head']);

it('converts mid-transfer guzzle failure into IndeterminateResultException', function () {
    Http::fake(['*' => function (): never {
        throw new GuzzleRequestException(
            'cURL error 56: Recv failure: Connection reset by peer',
            new GuzzleRequest('POST', 'https://example.com/nfse'),
        );
    }]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    try {
        $client->post('https://example.com/nfse', []);
        test()->fail('Expected IndeterminateResultException');
    } catch (IndeterminateResultException $e) {
        // Laravel 13+ envelopa a RequestException do Guzzle em ConnectionException;
        // versões anteriores a propagam crua. O contrato garante apenas a conversão.
        expect($e->getPrevious())->not->toBeNull()
            ->and($e->phase)->toBe('transfer')
            ->and($e->getMessage())->toContain('cURL error 56');
    }
});

it('converts mid-transfer failure with partial error response into IndeterminateResultException', function () {
    Http::fake(['*' => function (): never {
        throw new GuzzleRequestException(
            'cURL error 18: transfer closed with outstanding read data remaining',
            new GuzzleRequest('GET', 'https://example.com/nfse'),
            new Response(500, [], 'corpo parcial'),
        );
    }]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    try {
        $client->get('https://example.com/nfse/CHAVE123');
        test()->fail('Expected IndeterminateResultException');
    } catch (IndeterminateResultException $e) {
        // A fase varia com o marshalling: no Laravel 13 toException() descarta
        // a mensagem cURL (fase indetectável → null); no 11/12 a exceção Guzzle
        // propaga crua com a mensagem preservada (sniffing → 'transfer'). O que
        // o contrato garante é a conversão, não o diagnóstico.
        expect($e->phase)->toBeIn([null, 'transfer'])
            ->and($e->getMessage())->toContain('Resultado indeterminado');
    }
});

it('throws RequestNotDeliveredException for a provably pre-send failure', function () {
    Http::fake(['*' => function (): never {
        throw new ConnectionException('cURL error 6: Could not resolve host', 0, new ConnectException(
            'cURL error 6: Could not resolve host',
            new GuzzleRequest('POST', 'https://example.com/nfse'),
            null,
            ['errno' => 6],
        ));
    }]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    try {
        $client->post('https://example.com/nfse', []);
        test()->fail('Expected RequestNotDeliveredException');
    } catch (RequestNotDeliveredException $e) {
        expect($e->phase)->toBe('dns');
    }
});

it('does not convert non-transport exceptions into IndeterminateResultException', function () {
    Http::fake(['*' => function (): never {
        throw new RuntimeException('Erro inesperado');
    }]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(RuntimeException::class, 'Erro inesperado');
});

it('throws IndeterminateResultException on 2xx with unreadable body', function (string $body) {
    Http::fake(['*' => Http::response($body, 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    try {
        $client->post('https://example.com/nfse', []);
        test()->fail('Expected IndeterminateResultException');
    } catch (IndeterminateResultException $e) {
        expect($e->phase)->toBe('body')
            ->and($e->getMessage())->toContain('HTTP 200');
    }
})->with([
    'empty body' => [''],
    'invalid json' => ['<html>Gateway error</html>'],
    'scalar json' => ['123'],
    'truncated json' => ['{"chaveAcesso":"NFS3550'],
]);

it('getResponse throws IndeterminateResultException on 2xx with unreadable body', function (string $body) {
    Http::fake(['*' => Http::response($body, 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    try {
        $client->getResponse('https://example.com/adn/DFe/0');
        test()->fail('Expected IndeterminateResultException');
    } catch (IndeterminateResultException $e) {
        expect($e->phase)->toBe('body')
            ->and($e->getMessage())->toContain('HTTP 200');
    }
})->with([
    'empty body' => [''],
    'html error page' => ['<html>Service Unavailable</html>'],
    'scalar json' => ['123'],
    'truncated json' => ['{"LoteDFe":['],
]);

it('getResponse returns HttpResponse on 200 with empty json object body', function () {
    Http::fake(['*' => Http::response('{}', 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(200)
        ->body->toBe('{}');
    expect($response->json)->toBe([]);
});
