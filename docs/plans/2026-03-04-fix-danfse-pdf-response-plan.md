# Fix DANFSe PDF Response & Rename Execute Methods — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix the DANFSe endpoint to return raw PDF bytes instead of a non-existent JSON field, and rename execute methods for clarity.

**Architecture:** Add `getBytes()` to the HTTP layer for binary responses. Rename `executeGet` → `executeAndDecompress`, `executeGetRaw` → `execute`, add new `executeAndDownload`. Rewrite `DanfseResponse` to carry `?string $pdf` instead of `?string $url`.

**Tech Stack:** PHP 8.2+, Laravel HTTP Client, Pest, PHPStan, Psalm, Rector, Pint

**Design doc:** `docs/plans/2026-03-04-fix-danfse-pdf-response-design.md`

---

### Task 1: Add `getBytes()` to HTTP layer

**Files:**
- Modify: `src/Contracts/Driven/SendsHttpRequests.php:15-18`
- Modify: `src/Adapters/NfseHttpClient.php:39-59`
- Test: `tests/Unit/Http/NfseHttpClientTest.php`

**Step 1: Write failing tests for `getBytes`**

Add to `tests/Unit/Http/NfseHttpClientTest.php` at the end of the file:

```php
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
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class, 'HTTP error: 404');
});

it('getBytes throws HttpException on 5xx', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->getBytes('https://example.com/danfse/CHAVE123'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class, 'HTTP error: 500');
});

it('getBytes does not follow redirects', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://attacker.com/capture'])]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->getBytes('https://example.com/danfse/CHAVE123'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);

    Http::assertSentCount(1);
});

it('getBytes does not send Accept: application/json header', function () {
    Http::fake(['*' => Http::response('PDF-CONTENT', 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);
    $client->getBytes('https://example.com/danfse/CHAVE123');

    Http::assertSent(fn (Request $req) => ! str_contains($req->header('Accept')[0] ?? '', 'application/json'));
});
```

**Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Http/NfseHttpClientTest.php --filter="getBytes"`
Expected: FAIL — method `getBytes` does not exist

**Step 3: Add `getBytes` to contract**

In `src/Contracts/Driven/SendsHttpRequests.php`, add between `get()` and `head()`:

```php
    public function getBytes(string $url): string;
```

**Step 4: Implement `getBytes` in `NfseHttpClient`**

In `src/Adapters/NfseHttpClient.php`, add after the `head()` method (after line 59):

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

            if (! $response->successful()) {
                throw HttpException::fromResponse($response->status(), $response->body());
            }

            return $response->body();
        });
    }
```

**Step 5: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Http/NfseHttpClientTest.php`
Expected: ALL PASS

**Step 6: Commit**

```bash
git add src/Contracts/Driven/SendsHttpRequests.php src/Adapters/NfseHttpClient.php tests/Unit/Http/NfseHttpClientTest.php
git commit -m "feat: add getBytes() to HTTP layer for binary responses"
```

---

### Task 2: Rename `executeGet` → `executeAndDecompress` and `executeGetRaw` → `execute`

This task renames methods across contracts, pipeline, and all callers. No behavior change.

**Files:**
- Modify: `src/Contracts/Driving/ExecutesNfseRequests.php` (full file)
- Modify: `src/Pipeline/NfseResponsePipeline.php:25,83` (method names + phpdoc)
- Modify: `src/Operations/NfseConsulter.php:35,41,73,112` (call sites)
- Test: `tests/Unit/Operations/NfseConsulterTest.php` (rename in fake + test names)
- Test: `tests/Unit/Pipeline/NfseResponsePipelineTest.php` (rename in fake + test names)

**Step 1: Rename in contract**

Replace the full content of `src/Contracts/Driving/ExecutesNfseRequests.php`:

```php
<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Responses\NfseResponse;

interface ExecutesNfseRequests
{
    public function executeAndDecompress(string $url): NfseResponse;

    public function executeHead(string $url): int;

    /**
     * Retorna JSON cru da API.
     *
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
}
```

Note: `danfseUrl` removed from phpdoc. `executeAndDownload` added (will be implemented in Task 3).

**Step 2: Rename in pipeline**

In `src/Pipeline/NfseResponsePipeline.php`:
- Line 25: rename `executeGet` → `executeAndDecompress`
- Line 83: rename `executeGetRaw` → `execute`
- Lines 76,95: remove `danfseUrl` from both phpdoc blocks

Add a stub for `executeAndDownload` at the end (before `executeHead`):

```php
    public function executeAndDownload(string $url): string
    {
        throw new \RuntimeException('Not yet implemented');
    }
```

**Step 3: Rename call sites in `NfseConsulter`**

In `src/Operations/NfseConsulter.php`:
- Line 35: `$this->client->executeGet(` → `$this->client->executeAndDecompress(`
- Line 41: `$this->client->executeGetRaw(` → `$this->client->execute(`
- Line 73: `$this->client->executeGetRaw(` → `$this->client->execute(`
- Line 112: `$this->client->executeGetRaw(` → `$this->client->execute(`

**Step 4: Rename in `FakeNfseClientForConsulta` and test names**

In `tests/Unit/Operations/NfseConsulterTest.php`:
- Line 17: `executeGet` → `executeAndDecompress`
- Line 24: `executeGetRaw` → `execute`
- Add stub: `public function executeAndDownload(string $url): string { return ''; }`
- Line 46: test name `'calls executeGet with'` → `'calls executeAndDecompress with'`
- Line 57: test name `'calls executeGetRaw with'` → `'calls execute with'`
- All anonymous class implementations of `ExecutesNfseRequests`: rename methods **and add `executeAndDownload` stub** to each one
  (lines 69,75, 99,105, 132,138, 162,168, 194,200, 224,230, 259,265, 298,306)
  Each anonymous class must add: `public function executeAndDownload(string $url): string { return ''; }`

In `tests/Unit/Pipeline/NfseResponsePipelineTest.php`:
- Update `buildResponsePipeline` helper: add `getBytes` to fake `SendsHttpRequests`:
  ```php
  public function getBytes(string $url): string
  {
      if ($this->throwOnGet) {
          throw $this->throwOnGet;
      }
      return '';
  }
  ```
- Line 59 comment: `// --- executeGet ---` → `// --- executeAndDecompress ---`
- All `executeGet(` calls in test bodies → `executeAndDecompress(`
- All `'executeGet'` in test names → `'executeAndDecompress'`
- Line 152 comment: `// --- executeGetRaw ---` → `// --- execute ---`
- All `executeGetRaw(` calls in test bodies → `execute(`
- All `'executeGetRaw'` in test names → `'execute'`

**Step 5: Run full test suite**

Run: `./vendor/bin/pest --parallel`
Expected: ALL PASS (pure rename, no behavior change)

**Step 6: Commit**

```bash
git add src/Contracts/Driving/ExecutesNfseRequests.php src/Pipeline/NfseResponsePipeline.php src/Operations/NfseConsulter.php tests/Unit/Operations/NfseConsulterTest.php tests/Unit/Pipeline/NfseResponsePipelineTest.php
git commit -m "refactor: rename executeGet/executeGetRaw to executeAndDecompress/execute"
```

---

### Task 3: Implement `executeAndDownload` in pipeline

**Files:**
- Modify: `src/Pipeline/NfseResponsePipeline.php` (replace stub)
- Test: `tests/Unit/Pipeline/NfseResponsePipelineTest.php`

**Step 1: Write failing tests**

Add to `tests/Unit/Pipeline/NfseResponsePipelineTest.php`. First update `buildResponsePipeline` to accept a `?string $getBytesResult` and `?\Throwable $throwOnGetBytes`:

Update the helper function signature and fake class to add:

```php
function buildResponsePipeline(
    ?array $getResult = null,
    ?int $headResult = null,
    ?\Throwable $throwOnGet = null,
    ?\Throwable $throwOnHead = null,
    ?string $getBytesResult = null,
    ?\Throwable $throwOnGetBytes = null,
): NfseResponsePipeline {
```

And in the anonymous class add:

```php
        private readonly ?string $getBytesResult,
        private readonly ?\Throwable $throwOnGetBytes,
```

And the method:

```php
        public function getBytes(string $url): string
        {
            if ($this->throwOnGetBytes) {
                throw $this->throwOnGetBytes;
            }

            return $this->getBytesResult ?? '';
        }
```

Then add tests:

```php
// --- executeAndDownload ---

it('returns raw bytes and dispatches events on successful executeAndDownload', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(getBytesResult: 'PDF-BINARY-CONTENT');

    $result = $pipeline->executeAndDownload('https://example.com/danfse/CHAVE');

    expect($result)->toBe('PDF-BINARY-CONTENT');
    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e): bool => $e->operacao === 'consultar');
    Event::assertDispatched(NfseQueried::class);
});

it('dispatches NfseFailed when executeAndDownload throws', function (): void {
    Event::fake();

    $pipeline = buildResponsePipeline(throwOnGetBytes: new RuntimeException('Connection failed'));

    try {
        $pipeline->executeAndDownload('https://example.com/danfse/CHAVE');
    } catch (RuntimeException) {
        // expected
    }

    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e): bool => $e->operacao === 'consultar' && $e->message === 'Connection failed');
});
```

**Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Pipeline/NfseResponsePipelineTest.php --filter="executeAndDownload"`
Expected: FAIL — `RuntimeException: Not yet implemented`

**Step 3: Implement `executeAndDownload`**

In `src/Pipeline/NfseResponsePipeline.php`, replace the stub with:

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

**Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Pipeline/NfseResponsePipelineTest.php`
Expected: ALL PASS

**Step 5: Commit**

```bash
git add src/Pipeline/NfseResponsePipeline.php tests/Unit/Pipeline/NfseResponsePipelineTest.php
git commit -m "feat: implement executeAndDownload in pipeline for binary responses"
```

---

### Task 4: Rewrite `DanfseResponse` DTO

**Files:**
- Modify: `src/Responses/DanfseResponse.php` (full rewrite)
- Rewrite: `tests/Unit/DTOs/DanfseResponseTest.php`
- Delete: `tests/fixtures/responses/consultar_danfse.json`

**Step 1: Rewrite `DanfseResponseTest.php`**

Replace the full content of `tests/Unit/DTOs/DanfseResponseTest.php`:

```php
<?php

covers(\Pulsar\NfseNacional\Responses\DanfseResponse::class);

use Pulsar\NfseNacional\Responses\DanfseResponse;
use Pulsar\NfseNacional\Responses\ProcessingMessage;

it('success response carries pdf bytes and no erros', function () {
    $response = new DanfseResponse(true, 'PDF-BINARY-CONTENT');

    expect($response)
        ->sucesso->toBeTrue()
        ->pdf->toBe('PDF-BINARY-CONTENT')
        ->erros->toBeEmpty();
});

it('failure response carries erros and no pdf', function () {
    $erros = [new ProcessingMessage(descricao: 'NFSe não encontrada', codigo: 'E404')];

    $response = new DanfseResponse(false, erros: $erros);

    expect($response)
        ->sucesso->toBeFalse()
        ->pdf->toBeNull();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('NFSe não encontrada');
});

it('defaults all optional fields', function () {
    $response = new DanfseResponse(true);

    expect($response)
        ->pdf->toBeNull()
        ->erros->toBeEmpty();
});
```

**Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/DTOs/DanfseResponseTest.php`
Expected: FAIL — property `pdf` does not exist on DanfseResponse

**Step 3: Rewrite `DanfseResponse`**

Replace `src/Responses/DanfseResponse.php`:

```php
<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Responses;

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

**Step 4: Delete the fake fixture**

```bash
rm tests/fixtures/responses/consultar_danfse.json
```

**Step 5: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/DTOs/DanfseResponseTest.php`
Expected: ALL PASS

**Step 6: Commit**

```bash
git add src/Responses/DanfseResponse.php tests/Unit/DTOs/DanfseResponseTest.php
git rm tests/fixtures/responses/consultar_danfse.json
git commit -m "refactor: DanfseResponse carries raw PDF bytes instead of URL"
```

---

### Task 5: Rewrite `NfseConsulter::danfse()` with empty guard and error parsing

**Files:**
- Modify: `src/Operations/NfseConsulter.php:7-16,67-96` (imports + danfse method)
- Rewrite: danfse-related tests in `tests/Unit/Operations/NfseConsulterTest.php`

**Step 1: Rewrite danfse tests in `NfseConsulterTest.php`**

The `FakeNfseClientForConsulta` already has `executeAndDownload` from Task 2. Update its default return:

```php
public function executeAndDownload(string $url): string
{
    $this->calls[] = $url;

    return 'FAKE-PDF-BYTES';
}
```

Replace the danfse tests (the ones starting at "danfse returns failure" and "danfse returns success with danfseUrl"):

```php
it('danfse returns success with pdf bytes', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            return 'PDF-BINARY-CONTENT';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeTrue();
    expect($response->pdf)->toBe('PDF-BINARY-CONTENT');
});

it('danfse returns failure on empty response', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            return '';
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->codigo)->toBe('EMPTY_RESPONSE');
});

it('danfse returns failure with parsed JSON errors on HttpException', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            $e = \Pulsar\NfseNacional\Exceptions\HttpException::fromResponse(
                400,
                json_encode(['erros' => [['descricao' => 'DANFSe não encontrada', 'codigo' => '404']]]),
            );
            throw $e;
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toBe('DANFSe não encontrada');
    expect($response->erros[0]->codigo)->toBe('404');
});

it('danfse returns failure with raw error on non-JSON HttpException', function () {
    $fakeClient = new class implements ExecutesNfseRequests
    {
        public function executeAndDecompress(string $url): NfseResponse
        {
            return new NfseResponse(true);
        }

        public function execute(string $url): array
        {
            return [];
        }

        public function executeAndDownload(string $url): string
        {
            throw \Pulsar\NfseNacional\Exceptions\HttpException::fromResponse(500, 'Server Error');
        }

        public function executeHead(string $url): int
        {
            return 200;
        }
    };

    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $builder = new NfseConsulter($fakeClient, 'https://sefin.base', '', $resolver, '9999999');

    $response = $builder->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->codigo)->toBe('500');
    expect($response->erros[0]->descricao)->toBe('Server Error');
});
```

**Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseConsulterTest.php --filter="danfse returns"`
Expected: FAIL — `danfse()` still calls `execute()` which returns array, not PDF

**Step 3: Rewrite `danfse()` and add `parseHttpError()`**

In `src/Operations/NfseConsulter.php`, add `use Pulsar\NfseNacional\Exceptions\HttpException;` to imports.

Replace the `danfse()` method (lines 67-96) with:

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
```

Add the `parseHttpError` private method before `buildUrl`:

```php
    /** @return list<ProcessingMessage> */
    private static function parseHttpError(HttpException $e): array
    {
        $body = $e->getResponseBody();

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);

        if (is_array($decoded) && (! empty($decoded['erros']) || isset($decoded['erro']))) {
            return ProcessingMessage::fromApiResult($decoded);
        }

        return [new ProcessingMessage(
            mensagem: 'HTTP error: '.$e->getCode(),
            codigo: (string) $e->getCode(),
            descricao: $body,
        )];
    }
```

Remove the `use Pulsar\NfseNacional\Support\GzipCompressor;` import if `GzipCompressor` is no longer used in this file. Check: it's still used in `eventos()`, so keep it.

**Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseConsulterTest.php`
Expected: ALL PASS

**Step 5: Commit**

```bash
git add src/Operations/NfseConsulter.php tests/Unit/Operations/NfseConsulterTest.php
git commit -m "fix: danfse() returns raw PDF bytes, handles empty body and HTTP errors"
```

---

### Task 6: Fix feature tests and event dispatch test

**Files:**
- Modify: `tests/Feature/NfseClientConsultarTest.php:9-22,73-81,152-164`
- Modify: `tests/Feature/EventsDispatchTest.php:118-127`

**Step 1: Fix feature tests**

In `tests/Feature/NfseClientConsultarTest.php`, replace the danfse tests:

Replace the first danfse test (lines 9-22):
```php
it('consultar()->danfse returns DanfseResponse with pdf', function () {
    $chave = makeChaveAcesso();
    Http::fake(['*' => Http::response('PDF-BINARY-CONTENT', 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->danfse($chave);

    expect($response->sucesso)->toBeTrue();
    expect($response->pdf)->toBe('PDF-BINARY-CONTENT');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://adn.producaorestrita.nfse.gov.br/danfse/'.$chave &&
        $req->method() === 'GET'
    );
});
```

Replace the danfse error test (lines 73-81):
```php
it('consultar()->danfse returns failure on HTTP error', function () {
    Http::fake(['*' => Http::response('Not Found', 404)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->codigo)->toBe('404');
});
```

Replace the Santa Ana test (lines 152-164):
```php
it('consultar()->danfse uses Santa Ana de Parnaiba custom operation path', function () {
    $chave = makeChaveAcesso();
    Http::fake(['*' => Http::response('PDF-CONTENT', 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3547304');
    $response = $client->consultar()->danfse($chave);

    expect($response->sucesso)->toBeTrue();

    Http::assertSent(fn (Request $req) => $req->url() === 'https://producaorestrita.simplissweb.com.br/nfse/'.$chave &&
        $req->method() === 'GET'
    );
});
```

**Step 2: Fix event dispatch tests**

In `tests/Feature/EventsDispatchTest.php`, replace lines 118-127 (success test):

```php
it('dispatches NfseRequested and NfseQueried on consultar danfse', function () {
    Event::fake();
    Http::fake(['*' => Http::response('PDF-CONTENT', 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->consultar()->danfse(makeChaveAcesso());

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'consultar');
    Event::assertDispatched(NfseQueried::class);
});
```

Also replace lines 145-154 (rejection test). With the new flow, danfse HTTP errors are caught by `NfseConsulter::danfse()` after the pipeline dispatches `NfseFailed`. `NfseRejected` is no longer dispatched for danfse (it was only dispatched when the pipeline parsed JSON error bodies via `executeGetRaw`). Replace with:

```php
it('dispatches NfseFailed on consultar danfse HTTP error', function () {
    Event::fake();
    Http::fake(['*' => Http::response('Not Found', 404)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->consultar()->danfse(makeChaveAcesso());

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'consultar');
    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e) => $e->operacao === 'consultar');
    Event::assertNotDispatched(NfseQueried::class);
});
```

**Step 3: Run all feature tests**

Run: `./vendor/bin/pest tests/Feature/ --parallel`
Expected: ALL PASS

**Step 4: Commit**

```bash
git add tests/Feature/NfseClientConsultarTest.php tests/Feature/EventsDispatchTest.php
git commit -m "test: fix feature tests for PDF binary DANFSe response"
```

---

### Task 7: Update documentation and example

**Files:**
- Modify: `README.md:177-179`
- Modify: `examples/ConsultarDanfse.php:11,29-31`

**Step 1: Update README**

In `README.md`, replace lines 177-179:

```php
// Obter PDF do DANFSE
$response = $client->consultar()->danfse($chave);
// $response->pdf contém o conteúdo binário do PDF
file_put_contents('danfse.pdf', $response->pdf);
```

**Step 2: Update example**

Replace `examples/ConsultarDanfse.php`:

```php
<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\NfseClient;

// -------------------------------------------------------------------
// Consultar DANFSE – PDF binário (Standalone – sem Laravel)
// -------------------------------------------------------------------

$pfxContent = file_get_contents(__DIR__.'/certificado.pfx');
$senha = 'senha_certificado';
$prefeitura = 'PREFEITURA';

$client = NfseClient::forStandalone(
    pfxContent: $pfxContent,
    senha: $senha,
    prefeitura: $prefeitura,
    ambiente: NfseAmbiente::HOMOLOGACAO,
);

$chaveNfse = '00000000000000000000000000000000000000000000000000';

$response = $client->consultar()->danfse($chaveNfse);

if ($response->sucesso) {
    file_put_contents('danfse.pdf', $response->pdf);
    echo "DANFSE salvo em danfse.pdf\n";
} else {
    echo "Falha na consulta do DANFSE:\n";
    foreach ($response->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} – {$erro->descricao}\n";
    }
}
```

**Step 3: Commit**

```bash
git add README.md examples/ConsultarDanfse.php
git commit -m "docs: update DANFSe documentation for PDF binary response"
```

---

### Task 8: Run full quality checks

**Step 1: Run full test suite**

```bash
./vendor/bin/pest --coverage --min=100 --parallel
```

Expected: ALL PASS, 100% coverage

**Step 2: Run mutation tests**

```bash
./vendor/bin/pest --mutate --min=100 --parallel
```

Expected: 100% mutation score

**Step 3: Run type coverage**

```bash
./vendor/bin/pest --type-coverage --min=100
```

Expected: 100% type coverage

**Step 4: Run quality checks**

```bash
./vendor/bin/rector --dry-run
./vendor/bin/phpstan analyse
./vendor/bin/psalm --taint-analysis
./vendor/bin/pint -p
```

Expected: All pass. If pint changes any file, re-run the full test suite.

**Step 5: Final commit if pint changed files**

```bash
# Only if pint made changes:
git add -A
git commit -m "style: apply pint formatting"
```

---

## Task Dependency Graph

```
Task 1 (getBytes HTTP layer)
    ↓
Task 2 (rename methods)
    ↓
Task 3 (executeAndDownload pipeline)
    ↓
Task 4 (DanfseResponse DTO)
    ↓
Task 5 (rewrite danfse() in consulter)
    ↓
Task 6 (fix feature + event tests)
    ↓
Task 7 (docs + example)
    ↓
Task 8 (full quality checks)
```

All tasks are sequential — each depends on the previous.
