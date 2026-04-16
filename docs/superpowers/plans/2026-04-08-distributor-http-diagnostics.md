# Distributor HTTP Diagnostics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give NfseDistributor full HTTP diagnostics (status code, body raw, JSON) so consumers can diagnose unexpected API responses.

**Architecture:** New `SendsRawHttpRequests` interface with `getResponse()` returning an `HttpResponse` DTO (statusCode + json + body). `NfseHttpClient` implements both interfaces. `NfseDistributor` switches to `SendsRawHttpRequests`, and `DistribuicaoResponse` gains a `fromHttpResponse()` factory that handles non-2xx, empty body, and delegates to existing `fromApiResult()` for valid JSON.

**Tech Stack:** PHP 8.3, Laravel Http, Pest 4

**Quality gates (run after each task):**
```bash
./vendor/bin/pest --coverage --min=100 --parallel
./vendor/bin/pest --mutate --min=100 --parallel
./vendor/bin/pest --type-coverage --min=100
./vendor/bin/rector --dry-run
./vendor/bin/phpstan analyse
./vendor/bin/psalm --taint-analysis
./vendor/bin/pint -p
```

---

### Task 1: Create `HttpResponse` DTO

**Files:**
- Create: `src/Responses/HttpResponse.php`
- Create: `tests/Unit/DTOs/HttpResponseTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

use OwnerPro\Nfsen\Responses\HttpResponse;

covers(HttpResponse::class);

it('constructs with all fields', function () {
    $response = new HttpResponse(
        statusCode: 200,
        json: ['StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS'],
        body: '{"StatusProcessamento":"DOCUMENTOS_LOCALIZADOS"}',
    );

    expect($response)
        ->statusCode->toBe(200)
        ->json->toBe(['StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS'])
        ->body->toBe('{"StatusProcessamento":"DOCUMENTOS_LOCALIZADOS"}');
});

it('constructs with empty json and non-json body', function () {
    $response = new HttpResponse(
        statusCode: 429,
        json: [],
        body: 'Rate limit exceeded',
    );

    expect($response)
        ->statusCode->toBe(429)
        ->json->toBe([])
        ->body->toBe('Rate limit exceeded');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/DTOs/HttpResponseTest.php`
Expected: FAIL — class HttpResponse not found

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

final readonly class HttpResponse
{
    /** @param array<string, mixed> $json */
    public function __construct(
        public int $statusCode,
        public array $json,
        public string $body,
    ) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/DTOs/HttpResponseTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Responses/HttpResponse.php tests/Unit/DTOs/HttpResponseTest.php
git commit -m "feat: add HttpResponse DTO with statusCode, json, and body"
```

---

### Task 2: Create `SendsRawHttpRequests` interface

**Files:**
- Create: `src/Contracts/Driven/SendsRawHttpRequests.php`

- [ ] **Step 1: Write the interface**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

use OwnerPro\Nfsen\Responses\HttpResponse;

interface SendsRawHttpRequests
{
    public function getResponse(string $url): HttpResponse;
}
```

- [ ] **Step 2: Verify static analysis passes**

Run: `./vendor/bin/phpstan analyse src/Contracts/Driven/SendsRawHttpRequests.php`
Expected: No errors

- [ ] **Step 3: Commit**

```bash
git add src/Contracts/Driven/SendsRawHttpRequests.php
git commit -m "feat: add SendsRawHttpRequests interface"
```

---

### Task 3: Implement `getResponse()` in `NfseHttpClient`

**Files:**
- Modify: `src/Adapters/NfseHttpClient.php`
- Modify: `tests/Unit/Http/NfseHttpClientTest.php`

- [ ] **Step 1: Write the tests**

Add to `tests/Unit/Http/NfseHttpClientTest.php`:

```php
it('getResponse returns HttpResponse with status, json, and body on 200', function () {
    $jsonBody = ['StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS', 'LoteDFe' => []];
    Http::fake(['*' => Http::response($jsonBody, 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(200)
        ->json->toBe($jsonBody)
        ->body->toBe(json_encode($jsonBody));
});

it('getResponse returns HttpResponse on 429 with text body', function () {
    Http::fake(['*' => Http::response('Rate limit exceeded', 429)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(429)
        ->json->toBe([])
        ->body->toBe('Rate limit exceeded');
});

it('getResponse returns HttpResponse on 302 redirect without following', function () {
    Http::fake(['*' => Http::response(null, 302, ['Location' => 'https://other.com'])]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(302)
        ->json->toBe([]);

    Http::assertSentCount(1);
});

it('getResponse returns HttpResponse on 500 with json body', function () {
    $errorBody = ['error' => 'Internal Server Error'];
    Http::fake(['*' => Http::response($errorBody, 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(500)
        ->json->toBe($errorBody)
        ->body->toBe(json_encode($errorBody));
});

it('getResponse returns HttpResponse on 200 with empty body', function () {
    Http::fake(['*' => Http::response(null, 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $response = $client->getResponse('https://example.com/adn/DFe/0');

    expect($response)
        ->statusCode->toBe(200)
        ->json->toBe([])
        ->body->toBe('');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Http/NfseHttpClientTest.php --filter="getResponse"`
Expected: FAIL — method getResponse not found

- [ ] **Step 3: Write the implementation**

Edit `src/Adapters/NfseHttpClient.php`:

1. Add `use OwnerPro\Nfsen\Contracts\Driven\SendsRawHttpRequests;` and `use OwnerPro\Nfsen\Responses\HttpResponse;` to imports.
2. Change class declaration from `implements SendsHttpRequests` to `implements SendsHttpRequests, SendsRawHttpRequests`.
3. Add the method:

```php
public function getResponse(string $url): HttpResponse
{
    return $this->withCertificateFiles(function (string $certPath, string $keyPath) use ($url): HttpResponse {
        $response = Http::connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->acceptJson()
            ->withOptions([
                'verify' => $this->sslVerify,
                'cert' => $certPath,
                'ssl_key' => $keyPath,
                'allow_redirects' => false,
            ])
            ->get($url);

        /** @var array<string, mixed> $json */
        $json = (array) ($response->json() ?? []);

        return new HttpResponse($response->status(), $json, $response->body());
    });
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Http/NfseHttpClientTest.php`
Expected: ALL PASS

- [ ] **Step 5: Run quality gates**

- [ ] **Step 6: Commit**

```bash
git add src/Adapters/NfseHttpClient.php tests/Unit/Http/NfseHttpClientTest.php
git commit -m "feat: implement getResponse() in NfseHttpClient"
```

---

### Task 4: Add `fromHttpResponse()` to `DistribuicaoResponse`

**Files:**
- Modify: `src/Responses/DistribuicaoResponse.php`
- Modify: `tests/Unit/DTOs/DistribuicaoResponseTest.php`

- [ ] **Step 1: Write the tests**

Add to `tests/Unit/DTOs/DistribuicaoResponseTest.php`:

```php
use OwnerPro\Nfsen\Responses\HttpResponse;

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

it('fromHttpResponse returns HTTP error on 302 redirect', function () {
    $httpResponse = new HttpResponse(302, [], '');

    $response = DistribuicaoResponse::fromHttpResponse($httpResponse);

    expect($response)
        ->sucesso->toBeFalse()
        ->statusProcessamento->toBe(StatusDistribuicao::Rejeicao)
        ->erros->toHaveCount(1);
    expect($response->erros[0])
        ->codigo->toBe('HTTP_302')
        ->descricao->toBe('A API retornou HTTP 302.');
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/DTOs/DistribuicaoResponseTest.php --filter="fromHttpResponse"`
Expected: FAIL — method fromHttpResponse not found

- [ ] **Step 3: Write the implementation**

Edit `src/Responses/DistribuicaoResponse.php`. Add import for `HttpResponse` and add two new methods:

```php
public static function fromHttpResponse(HttpResponse $response): self
{
    if ($response->statusCode >= 300) {
        return self::fromNon2xxResponse($response);
    }

    if ($response->json === []) {
        return new self(
            sucesso: false,
            statusProcessamento: StatusDistribuicao::Rejeicao,
            lote: [],
            alertas: [],
            erros: [new ProcessingMessage(
                mensagem: 'Resposta vazia da API',
                codigo: 'EMPTY_RESPONSE',
                descricao: sprintf('A API retornou HTTP %d com corpo vazio.', $response->statusCode),
            )],
            tipoAmbiente: null,
            versaoAplicativo: null,
            dataHoraProcessamento: null,
        );
    }

    return self::fromApiResult($response->json);
}

private static function fromNon2xxResponse(HttpResponse $response): self
{
    if ($response->json !== [] && isset($response->json['StatusProcessamento'])) {
        return self::fromApiResult($response->json);
    }

    $body = $response->body;

    return new self(
        sucesso: false,
        statusProcessamento: StatusDistribuicao::Rejeicao,
        lote: [],
        alertas: [],
        erros: [new ProcessingMessage(
            mensagem: sprintf('HTTP error: %d', $response->statusCode),
            codigo: sprintf('HTTP_%d', $response->statusCode),
            descricao: sprintf('A API retornou HTTP %d.', $response->statusCode),
            complemento: $body !== '' ? $body : null,
        )],
        tipoAmbiente: null,
        versaoAplicativo: null,
        dataHoraProcessamento: null,
    );
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/DTOs/DistribuicaoResponseTest.php`
Expected: ALL PASS

- [ ] **Step 5: Run quality gates**

- [ ] **Step 6: Commit**

```bash
git add src/Responses/DistribuicaoResponse.php tests/Unit/DTOs/DistribuicaoResponseTest.php
git commit -m "feat: add fromHttpResponse() to DistribuicaoResponse"
```

---

### Task 5: Switch `NfseDistributor` to `SendsRawHttpRequests`

**Files:**
- Modify: `src/Operations/NfseDistributor.php`
- Modify: `tests/Unit/Operations/NfseDistributorTest.php`

- [ ] **Step 1: Rewrite the test helpers and update existing tests**

The test file `tests/Unit/Operations/NfseDistributorTest.php` needs its fake HTTP client changed from `SendsHttpRequests` to `SendsRawHttpRequests`, and the tests that tested `handleHttpError()` via `HttpException` need to be replaced with tests that exercise `fromHttpResponse()` paths via `HttpResponse`.

Replace the full file content with:

```php
<?php

use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Contracts\Driven\SendsRawHttpRequests;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Operations\NfseDistributor;
use OwnerPro\Nfsen\Responses\HttpResponse;

covers(NfseDistributor::class);

function makeFakeDistribuicaoJson(string $status = 'DOCUMENTOS_LOCALIZADOS', ?array $lote = null): array
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

function makeFakeRawHttpClient(int $statusCode, array $json, ?string $body = null): SendsRawHttpRequests
{
    return new class($statusCode, $json, $body) implements SendsRawHttpRequests
    {
        /** @var list<string> */
        public array $urls = [];

        public function __construct(
            private readonly int $statusCode,
            private readonly array $json,
            private readonly ?string $body,
        ) {}

        public function getResponse(string $url): HttpResponse
        {
            $this->urls[] = $url;

            return new HttpResponse(
                $this->statusCode,
                $this->json,
                $this->body ?? json_encode($this->json, JSON_THROW_ON_ERROR),
            );
        }
    };
}

function makeDistributor(SendsRawHttpRequests $httpClient): NfseDistributor
{
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');

    return new NfseDistributor($httpClient, $resolver, '9999999', 'https://adn.base', '12345678000195');
}

// --- URL construction tests ---

it('documentos sends GET with lote=true and default cnpjConsulta', function () {
    $json = makeFakeDistribuicaoJson();
    $httpClient = makeFakeRawHttpClient(200, $json);
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeTrue();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::DocumentosLocalizados);
    expect($response->lote)->toHaveCount(1);
    expect($response->lote[0]->tipoDocumento)->toBe(TipoDocumentoFiscal::Nfse);
    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/0?cnpjConsulta=12345678000195&lote=true');
});

it('documentos uses provided cnpjConsulta over default', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);

    $distributor->documentos(0, '99999999000100');

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/0?cnpjConsulta=99999999000100&lote=true');
});

it('documento sends GET with lote=false', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);

    $distributor->documento(42);

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/42?cnpjConsulta=12345678000195&lote=false');
});

it('documento uses provided cnpjConsulta', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);

    $distributor->documento(42, '99999999000100');

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/42?cnpjConsulta=99999999000100&lote=false');
});

it('eventos sends GET with chave in URL', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);
    $chave = makeChaveAcesso();

    $response = $distributor->eventos($chave);

    expect($response->sucesso)->toBeTrue();
    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/NFSe/'.$chave.'/Eventos');
});

it('eventos throws InvalidArgumentException for invalid chave', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $distributor = makeDistributor($httpClient);

    expect(fn () => $distributor->eventos('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

// --- Status handling tests ---

it('handles NENHUM_DOCUMENTO_LOCALIZADO status', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson('NENHUM_DOCUMENTO_LOCALIZADO', []));
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(999);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::NenhumDocumentoLocalizado);
    expect($response->lote)->toBeEmpty();
});

it('handles REJEICAO status with errors', function () {
    $json = [
        'StatusProcessamento' => 'REJEICAO',
        'LoteDFe' => null,
        'Alertas' => null,
        'Erros' => [['Codigo' => 'E001', 'Descricao' => 'CNPJ inválido']],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];
    $httpClient = makeFakeRawHttpClient(200, $json);
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('CNPJ inválido');
});

// --- HTTP error handling tests ---

it('handles HTTP 500 with non-JSON body', function () {
    $httpClient = makeFakeRawHttpClient(500, [], 'Server Error');
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0])
        ->codigo->toBe('HTTP_500')
        ->complemento->toBe('Server Error');
});

it('handles HTTP 500 with structured ADN JSON body', function () {
    $json = [
        'StatusProcessamento' => 'REJEICAO',
        'Erros' => [['Codigo' => 'E500', 'Descricao' => 'Erro interno']],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ];
    $httpClient = makeFakeRawHttpClient(500, $json);
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->descricao)->toBe('Erro interno');
});

it('handles HTTP 500 with JSON missing StatusProcessamento', function () {
    $json = ['message' => 'Internal Server Error'];
    $httpClient = makeFakeRawHttpClient(500, $json);
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->codigo)->toBe('HTTP_500');
});

it('handles HTTP 429 rate limiting', function () {
    $httpClient = makeFakeRawHttpClient(429, [], 'Too Many Requests');
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0])
        ->codigo->toBe('HTTP_429')
        ->complemento->toBe('Too Many Requests');
});

it('handles HTTP 200 with empty body', function () {
    $httpClient = makeFakeRawHttpClient(200, [], '');
    $distributor = makeDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0])
        ->codigo->toBe('EMPTY_RESPONSE')
        ->descricao->toBe('A API retornou HTTP 200 com corpo vazio.');
});

// --- URL construction edge cases ---

it('buildUrl trims leading slash from path', function () {
    $tmpJson = tempnam(sys_get_temp_dir(), 'pref');
    file_put_contents($tmpJson, json_encode([
        '9999998' => ['operations' => ['distribute_documents' => '/contribuintes/DFe/{NSU}']],
    ]));

    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());

    try {
        $resolver = new PrefeituraResolver($tmpJson);
        $distributor = new NfseDistributor($httpClient, $resolver, '9999998', 'https://adn.base', '12345678000195');
        $distributor->documentos(0);

        expect($httpClient->urls[0])->toStartWith('https://adn.base/contribuintes/');
    } finally {
        unlink($tmpJson);
    }
});

it('buildUrl returns baseUrl when path is empty', function () {
    $tmpJson = tempnam(sys_get_temp_dir(), 'pref');
    file_put_contents($tmpJson, json_encode([
        '9999998' => ['operations' => ['distribute_documents' => '']],
    ]));

    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());

    try {
        $resolver = new PrefeituraResolver($tmpJson);
        $distributor = new NfseDistributor($httpClient, $resolver, '9999998', 'https://adn.base', '12345678000195');
        $distributor->documentos(0);

        expect($httpClient->urls[0])->toStartWith('https://adn.base?');
    } finally {
        unlink($tmpJson);
    }
});

it('buildUrl trims trailing slash from baseUrl', function () {
    $httpClient = makeFakeRawHttpClient(200, makeFakeDistribuicaoJson());
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $distributor = new NfseDistributor($httpClient, $resolver, '9999999', 'https://adn.base/', '12345678000195');

    $distributor->documentos(0);

    expect($httpClient->urls[0])->toStartWith('https://adn.base/contribuintes/');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseDistributorTest.php`
Expected: FAIL — constructor expects SendsRawHttpRequests but tests pass it

- [ ] **Step 3: Rewrite `NfseDistributor`**

Replace full content of `src/Operations/NfseDistributor.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driven\ResolvesOperations;
use OwnerPro\Nfsen\Contracts\Driven\SendsRawHttpRequests;
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Pipeline\Concerns\ValidatesChaveAcesso;
use OwnerPro\Nfsen\Responses\DistribuicaoResponse;

final readonly class NfseDistributor implements DistributesNfse
{
    use ValidatesChaveAcesso;

    public function __construct(
        private SendsRawHttpRequests $httpClient,
        private ResolvesOperations $resolver,
        private string $codigoIbge,
        private string $adnBaseUrl,
        private string $cnpjAutor,
    ) {}

    public function documentos(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse
    {
        return $this->fetchDfe($nsu, $cnpjConsulta, lote: true);
    }

    public function documento(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse
    {
        return $this->fetchDfe($nsu, $cnpjConsulta, lote: false);
    }

    public function eventos(string $chave): DistribuicaoResponse
    {
        $this->validateChaveAcesso($chave);
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'distribute_events', ['ChaveAcesso' => $chave]);
        $url = $this->buildUrl($this->adnBaseUrl, $path);

        return $this->executeRequest($url);
    }

    private function fetchDfe(int $nsu, ?string $cnpjConsulta, bool $lote): DistribuicaoResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'distribute_documents', ['NSU' => $nsu]);
        $url = $this->buildUrl($this->adnBaseUrl, $path);
        $url .= '?'.http_build_query([
            'cnpjConsulta' => $cnpjConsulta ?? $this->cnpjAutor,
            'lote' => $lote ? 'true' : 'false',
        ]);

        return $this->executeRequest($url);
    }

    private function executeRequest(string $url): DistribuicaoResponse
    {
        $httpResponse = $this->httpClient->getResponse($url);

        return DistribuicaoResponse::fromHttpResponse($httpResponse);
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
```

- [ ] **Step 4: Run unit tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseDistributorTest.php`
Expected: ALL PASS

- [ ] **Step 5: Commit**

```bash
git add src/Operations/NfseDistributor.php tests/Unit/Operations/NfseDistributorTest.php
git commit -m "refactor: switch NfseDistributor to SendsRawHttpRequests"
```

---

### Task 6: Update wiring in `NfsenClient`

**Files:**
- Modify: `src/NfsenClient.php` (line 119)
- Modify: `tests/Feature/NfsenClientDistribuicaoTest.php`

- [ ] **Step 1: Update `NfsenClient::forStandalone()`**

In `src/NfsenClient.php` line 119, `NfseDistributor` already receives `$httpClient` which is a `NfseHttpClient` instance. Since `NfseHttpClient` now implements `SendsRawHttpRequests`, the wiring works as-is. No code change needed in `NfsenClient.php` — PHP will accept the `NfseHttpClient` since it implements `SendsRawHttpRequests`.

Verify by running the feature tests:

Run: `./vendor/bin/pest tests/Feature/NfsenClientDistribuicaoTest.php`
Expected: ALL PASS

- [ ] **Step 2: Add feature test for HTTP error diagnostics**

Add to `tests/Feature/NfsenClientDistribuicaoTest.php`:

```php
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
```

- [ ] **Step 3: Run feature tests**

Run: `./vendor/bin/pest tests/Feature/NfsenClientDistribuicaoTest.php`
Expected: ALL PASS

- [ ] **Step 4: Run full quality gates**

Run all quality checks from the header. If pint or rector change files, re-run the full test suite.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/NfsenClientDistribuicaoTest.php
git commit -m "test: add feature tests for HTTP diagnostics in distribuicao"
```

---

### Task 7: Update CHANGELOG and README

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `README.md` (if needed)

- [ ] **Step 1: Update CHANGELOG**

Replace the existing `## [2.1.1]` entry in `CHANGELOG.md` with:

```markdown
## [2.2.0] - 2026-04-08

### Added
- `HttpResponse` DTO com `statusCode`, `json` e `body` para respostas HTTP completas.
- Interface `SendsRawHttpRequests` com método `getResponse()` para acesso a respostas HTTP sem perda de informação.
- `DistribuicaoResponse::fromHttpResponse()` — novo factory method que preserva HTTP status code e body raw em cenários de erro.
- `NfseHttpClient` agora implementa `SendsRawHttpRequests` além de `SendsHttpRequests`.

### Changed
- `NfseDistributor` usa `SendsRawHttpRequests::getResponse()` em vez de `SendsHttpRequests::get()`, preservando HTTP status code e body raw em todas as respostas de erro.

### Fixed
- Respostas HTTP 4xx com body vazio (ex: 429 rate limiting), redirects (3xx) e respostas 2xx com corpo vazio agora são diagnosticáveis — o `DistribuicaoResponse` inclui o status code no `codigo` do erro e o body raw no `complemento`.

## [2.1.1] - 2026-04-08

### Fixed
- `DistribuicaoResponse::fromApiResult()` agora inclui o JSON completo da API no campo `complemento` e as chaves presentes no `descricao` quando `StatusProcessamento` é ausente ou inválido, facilitando o diagnóstico de respostas inesperadas.
```

- [ ] **Step 2: Check if README needs updates**

The README already documents `ProcessingMessage` fields including `codigo` and `complemento`. The new error codes (`HTTP_429`, `EMPTY_RESPONSE`, etc.) are runtime values, not a public API change. No README change needed.

- [ ] **Step 3: Commit**

```bash
git add CHANGELOG.md
git commit -m "chore: update changelog for 2.2.0"
```

---

### Task 8: Final verification and tag

- [ ] **Step 1: Run the complete quality gate suite**

```bash
./vendor/bin/pest --coverage --min=100 --parallel
./vendor/bin/pest --mutate --min=100 --parallel
./vendor/bin/pest --type-coverage --min=100
./vendor/bin/rector --dry-run
./vendor/bin/phpstan analyse
./vendor/bin/psalm --taint-analysis
./vendor/bin/pint -p
```

If pint or rector made changes, re-run the full suite.

- [ ] **Step 2: Tag the release**

```bash
git tag v2.2.0
```
