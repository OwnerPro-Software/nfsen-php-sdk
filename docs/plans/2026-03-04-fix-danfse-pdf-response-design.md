# Design: Fix DANFSe PDF Response & Rename Execute Methods

**Date:** 2026-03-04
**Status:** Reviewed

## Problem

The ADN DANFSe endpoint (`GET /danfse/{chaveAcesso}`) returns **raw PDF bytes**, not JSON.
The swagger (`storage/schemes/DANFSe.json`) confirms: the 200 response has no JSON schema.
The old library (Rainzart) handles this correctly via `Content-Type` detection.

Our implementation is broken in two ways:

1. **`NfseHttpClient::get()` forces JSON** — calls `acceptJson()` and `->json()`, so PDF bytes are lost before they reach the caller.
2. **`NfseConsulter::danfse()` reads `$result['danfseUrl']`** — a field that doesn't exist in the real API. The metadata fields (`tipoAmbiente`, `versaoAplicativo`, `dataHoraProcessamento`) also don't exist in a PDF binary response.

## Method Rename

Current names are misleading. The rename clarifies intent:

| Current | New | Returns | Purpose |
|---------|-----|---------|---------|
| `executeGet` | `executeAndDecompress` | `NfseResponse` | JSON + gzip decompression (nfse query) |
| `executeGetRaw` | `execute` | `array` | JSON as-is (dps, eventos) |
| *(new)* | `executeAndDownload` | `string` | Raw bytes from server (danfse PDF) |
| `executeHead` | `executeHead` | `int` | HTTP status only (unchanged) |

### Who calls what

| Caller | Current | New |
|--------|---------|-----|
| `NfseConsulter::nfse()` | `executeGet` | `executeAndDecompress` |
| `NfseConsulter::dps()` | `executeGetRaw` | `execute` |
| `NfseConsulter::eventos()` | `executeGetRaw` | `execute` |
| `NfseConsulter::danfse()` | `executeGetRaw` | `executeAndDownload` |
| `NfseConsulter::verificarDps()` | `executeHead` | `executeHead` |

## Changes

### 1. `SendsHttpRequests` (contract)

Add `getBytes(string $url): string` — returns raw response body without JSON parsing.

```php
interface SendsHttpRequests
{
    /** @param array<string, mixed> $payload */
    /** @return array<string, mixed> */
    public function post(string $url, array $payload): array;

    /** @return array<string, mixed> */
    public function get(string $url): array;

    public function getBytes(string $url): string;

    public function head(string $url): int;
}
```

### 2. `NfseHttpClient`

Implement `getBytes()` — same cert/SSL setup, but **no `acceptJson()`**, returns `$response->body()`. Throws `HttpException` on 4xx/5xx.

```php
public function getBytes(string $url): string
{
    return $this->withCertificateFiles(function (string $certPath, string $keyPath) use ($url): string {
        $response = Http::connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->withOptions([
                'verify' => $this->sslVerify,
                'cert' => $certPath,
                'ssl_key' => $keyPath,
                'allow_redirects' => false,
            ])
            ->get($url);

        if ($response->failed()) {
            throw HttpException::fromResponse($response->status(), $response->body());
        }

        return $response->body();
    });
}
```

### 3. `ExecutesNfseRequests` (contract)

Rename methods and add `executeAndDownload`:

```php
interface ExecutesNfseRequests
{
    public function executeAndDecompress(string $url): NfseResponse;

    /**
     * @return array{
     *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
     *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
     *     chaveAcesso?: string,
     *     idDps?: string,
     *     eventoXmlGZipB64?: string,
     *     tipoAmbiente?: int,
     *     versaoAplicativo?: string,
     *     dataHoraProcessamento?: string,
     * }
     */
    public function execute(string $url): array;

    public function executeAndDownload(string $url): string;

    public function executeHead(string $url): int;
}
```

Note: `danfseUrl` removed from the phpdoc — it never existed in the real API.

### 4. `NfseResponsePipeline`

- Rename `executeGet` → `executeAndDecompress`
- Rename `executeGetRaw` → `execute` (update phpdoc to remove `danfseUrl`)
- Add `executeAndDownload` — dispatches events, calls `$this->httpClient->getBytes()`, returns raw string

```php
public function executeAndDownload(string $url): string
{
    $operacao = 'consultar';
    $this->dispatchEvent(new NfseRequested($operacao));

    return $this->withFailureEvent($operacao, function () use ($url, $operacao): string {
        $result = $this->httpClient->getBytes($url);
        $this->dispatchEvent(new NfseQueried($operacao));

        return $result;
    });
}
```

### 5. `DanfseResponse`

Remove fake metadata fields. Replace `url` with `pdf`:

```php
final readonly class DanfseResponse
{
    /** @param list<ProcessingMessage> $erros */
    public function __construct(
        public bool $sucesso,
        public ?string $pdf = null,
        public array $erros = [],
    ) {}
}
```

### 6. `NfseConsulter::danfse()`

Use `executeAndDownload`, catch `HttpException` for error responses.

**Empty body guard:** A 200 with empty body is treated as an error — a valid PDF is never empty.

**Error body parsing:** On 4xx/5xx, attempt `json_decode` on the error body. If it contains `erro`/`erros` keys, extract structured `ProcessingMessage` objects. Fallback to raw string in `descricao`.

```php
public function danfse(string $chave): DanfseResponse
{
    $this->validateChaveAcesso($chave);
    $baseUrl = $this->adnBaseUrl ?: $this->seFinBaseUrl;
    $path = $this->resolver->resolveOperation($this->codigoIbge, 'query_danfse', ['chave' => $chave]);

    try {
        $pdf = $this->client->executeAndDownload($this->buildUrl($baseUrl, $path));

        if ($pdf === '') {
            return new DanfseResponse(
                sucesso: false,
                erros: [new ProcessingMessage(
                    mensagem: 'Resposta vazia',
                    codigo: 'EMPTY_RESPONSE',
                    descricao: 'O servidor retornou uma resposta vazia para o DANFSe.',
                )],
            );
        }

        return new DanfseResponse(sucesso: true, pdf: $pdf);
    } catch (HttpException $e) {
        return new DanfseResponse(
            sucesso: false,
            erros: self::parseHttpError($e),
        );
    }
}

/** @return list<ProcessingMessage> */
private static function parseHttpError(HttpException $e): array
{
    $body = $e->getResponseBody();
    $decoded = json_decode($body, true);

    if (is_array($decoded) && (! empty($decoded['erros']) || isset($decoded['erro']))) {
        return ProcessingMessage::fromApiResult($decoded);
    }

    return [new ProcessingMessage(
        mensagem: 'HTTP error: ' . $e->getCode(),
        codigo: (string) $e->getCode(),
        descricao: $body,
    )];
}
```

### 7. `NfseClient::forStandalone()`

No constructor changes needed — `NfseConsulter` already receives `$queryExecutor` which is the pipeline. The pipeline gets the new method.

### 8. Rename usages in `NfseConsulter`

- `nfse()`: `executeGet` → `executeAndDecompress`
- `dps()`: `executeGetRaw` → `execute`
- `eventos()`: `executeGetRaw` → `execute`

### 9. Tests

**Unit tests (`NfseConsulterTest.php`):**
- `FakeNfseClientForConsulta`: rename methods, add `executeAndDownload`
- DANFSe success test: fake returns PDF bytes, asserts `$response->pdf`
- DANFSe empty body test: fake returns `""`, asserts `$response->sucesso === false` with `EMPTY_RESPONSE` error
- DANFSe HTTP error test: fake throws `HttpException`, asserts failure with parsed errors
- DANFSe HTTP error with JSON body test: fake throws `HttpException` with JSON body containing `erros`, asserts structured `ProcessingMessage` extraction
- All other tests: rename `executeGet` → `executeAndDecompress`, `executeGetRaw` → `execute`

**DTO tests (`DanfseResponseTest.php`):**
- Rewrite all tests for new shape: `pdf` instead of `url`, no metadata fields
- Remove fixture-based test (fixture is deleted)
- Add test for success with PDF bytes
- Add test for failure with erros
- Add test for defaults (pdf null, erros empty)

**Pipeline tests (`NfseResponsePipelineTest.php`):**
- Rename test sections: `executeGet` → `executeAndDecompress`, `executeGetRaw` → `execute`
- Add `executeAndDownload` tests: success returns bytes, dispatches events
- Add `executeAndDownload` exception test: dispatches `NfseFailed`, re-throws
- Update `buildResponsePipeline` helper: add `getBytes` support to fake `SendsHttpRequests`

**Feature tests (`NfseClientConsultarTest.php`):**
- DANFSe success test: `Http::fake` returns raw PDF body (not JSON), asserts `$response->pdf`
- DANFSe error test: `Http::fake` returns 404, asserts failure response
- Santa Ana test: same with custom operation path, returns PDF body

**Events test (`EventsDispatchTest.php`):**
- Update danfse event test: `Http::fake` returns PDF body instead of JSON with `danfseUrl`

**HTTP client tests (`NfseHttpClientTest.php`):**
- Add `getBytes` tests: success returns body, failure throws `HttpException`

**Fixture changes:**
- Delete `tests/fixtures/responses/consultar_danfse.json` (was fake JSON, not real)

### 10. Documentation

**`README.md`:**
```php
// Obter PDF do DANFSE
$response = $client->consultar()->danfse($chave);
// $response->pdf contém o conteúdo binário do PDF
file_put_contents('danfse.pdf', $response->pdf);
```

**`examples/ConsultarDanfse.php`:** update to use `$response->pdf`.

## Files Changed

| File | Type |
|------|------|
| `src/Contracts/Driven/SendsHttpRequests.php` | Add `getBytes()` |
| `src/Adapters/NfseHttpClient.php` | Implement `getBytes()` |
| `src/Contracts/Driving/ExecutesNfseRequests.php` | Rename + add `executeAndDownload`, remove `danfseUrl` from phpdoc |
| `src/Pipeline/NfseResponsePipeline.php` | Rename + implement `executeAndDownload`, remove `danfseUrl` from phpdoc |
| `src/Responses/DanfseResponse.php` | `url` → `pdf`, remove metadata |
| `src/Operations/NfseConsulter.php` | Rename calls + rewrite `danfse()` with empty guard and JSON error parsing |
| `tests/Unit/DTOs/DanfseResponseTest.php` | Rewrite for new shape |
| `tests/Unit/Operations/NfseConsulterTest.php` | Rename + rewrite danfse tests + add empty/error parsing tests |
| `tests/Unit/Pipeline/NfseResponsePipelineTest.php` | Rename + add download tests |
| `tests/Unit/Http/NfseHttpClientTest.php` | Add `getBytes` tests |
| `tests/Feature/NfseClientConsultarTest.php` | Fix danfse tests for PDF |
| `tests/Feature/EventsDispatchTest.php` | Fix danfse event test for PDF |
| `tests/fixtures/responses/consultar_danfse.json` | Delete |
| `examples/ConsultarDanfse.php` | Update |
| `README.md` | Update danfse docs |

## Not Changed

- All non-DANFSe endpoints are correct per swagger audit
- `NfseClient::forStandalone()` constructor — no signature changes
- Event system — same events, just new method triggers them
- Error handling for JSON endpoints — unchanged
