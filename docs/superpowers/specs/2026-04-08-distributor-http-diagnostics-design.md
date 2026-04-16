# Distributor HTTP Diagnostics

## Problema

`NfseHttpClient::get()` retorna `array` (JSON decodado), descartando o HTTP status code e o body raw. Quando a API ADN retorna respostas inesperadas (body vazio, HTML, rate limiting, redirect), o `NfseDistributor` nao consegue diagnosticar a causa — tudo vira `INVALID_RESPONSE` com `complemento: "[]"`.

### Cenarios afetados

| HTTP Status | Body | Comportamento atual | Status code preservado? |
|---|---|---|---|
| 4xx + body vazio | — | Retorna `[]` silenciosamente | Nao |
| 3xx (redirect) | — | Retorna `[]` silenciosamente | Nao |
| 2xx + body vazio | — | Retorna `[]` silenciosamente | Nao |
| 429 rate limit | texto | Retorna `[]` silenciosamente | Nao |
| 2xx + JSON sem StatusProcessamento | JSON inesperado | `INVALID_RESPONSE` com raw JSON (fix v2.1.1) | Nao |

## Decisoes de design

1. **Nova interface `SendsRawHttpRequests`** — nao polui `SendsHttpRequests` com metodo que so o Distributor precisa.
2. **DTO `HttpResponse` com `statusCode` + `json` + `body`** — preserva toda informacao. `body` eh redundante quando eh JSON valido, mas essencial quando a resposta nao eh JSON (HTML, texto puro).
3. **`NfseDistributor` passa a usar `getResponse()` em vez de `get()`** — callers existentes (NfseResponsePipeline, NfseConsulter) nao mudam.
4. **Novo factory method `DistribuicaoResponse::fromHttpResponse()`** — centraliza logica de diagnostico com acesso a status code e body raw.

## Arquitetura

### Componentes novos

#### `HttpResponse` (DTO)

```
src/Responses/HttpResponse.php
```

```php
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

#### `SendsRawHttpRequests` (interface)

```
src/Contracts/Driven/SendsRawHttpRequests.php
```

```php
interface SendsRawHttpRequests
{
    public function getResponse(string $url): HttpResponse;
}
```

### Componentes modificados

#### `NfseHttpClient`

Implementa `SendsRawHttpRequests` alem de `SendsHttpRequests`:

```php
final readonly class NfseHttpClient implements SendsHttpRequests, SendsRawHttpRequests
{
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

            $json = (array) ($response->json() ?? []);

            return new HttpResponse($response->status(), $json, $response->body());
        });
    }
}
```

Nota: `getResponse()` **nao lanca HttpException** — retorna tudo, incluindo 4xx/5xx. O caller decide o que fazer. Isso eh intencional: o `NfseDistributor` tem logica propria para interpretar erros ADN, diferente do tratamento generico do `request()`.

#### `NfseDistributor`

Muda de `SendsHttpRequests` para `SendsRawHttpRequests`:

```php
final readonly class NfseDistributor implements DistributesNfse
{
    public function __construct(
        private SendsRawHttpRequests $httpClient,  // era SendsHttpRequests
        private ResolvesOperations $resolver,
        private string $codigoIbge,
        private string $adnBaseUrl,
        private string $cnpjAutor,
    ) {}

    private function executeRequest(string $url): DistribuicaoResponse
    {
        $httpResponse = $this->httpClient->getResponse($url);

        return DistribuicaoResponse::fromHttpResponse($httpResponse);
        // sem try/catch — getResponse() nao lanca
        // toda logica de diagnostico dentro de fromHttpResponse()
    }
}
```

`handleHttpError()` eh removido — nao eh mais necessario.

#### `DistribuicaoResponse::fromHttpResponse()`

Novo factory method que substitui o fluxo `executeRequest()` + `handleHttpError()` + `fromApiResult()`:

```php
public static function fromHttpResponse(HttpResponse $response): self
{
    // 1. Non-2xx → erro com status code e body
    if ($response->statusCode >= 300) {
        return self::buildHttpError($response);
    }

    // 2. 2xx com body vazio
    if ($response->json === []) {
        return self::buildEmptyResponseError($response);
    }

    // 3. 2xx com JSON → caminho normal (delega ao fromApiResult existente)
    return self::fromApiResult($response->json);
}
```

Onde:
- `buildHttpError()` — cria `Rejeicao` com `codigo: "HTTP_{statusCode}"`, `descricao` com status, `complemento` com body raw (para ver se eh HTML, JSON de erro, texto de rate limit, etc.)
- `buildEmptyResponseError()` — cria `Rejeicao` com `codigo: "EMPTY_RESPONSE"`, `descricao` indica HTTP 2xx com corpo vazio

O `fromApiResult()` existente **permanece intocado** — continua tratando o caso de JSON sem `StatusProcessamento` valido (fix v2.1.1).

### Fluxo resultante

```
NfseDistributor::executeRequest($url)
  → httpClient->getResponse($url)           ← retorna HttpResponse (status + json + body)
  → DistribuicaoResponse::fromHttpResponse()
      ├─ status >= 300? → buildHttpError()   ← "HTTP 429 — Rate limit exceeded"
      ├─ json === []?   → buildEmptyResponseError() ← "HTTP 200, corpo vazio"
      └─ json ok?       → fromApiResult()   ← caminho existente
```

### Wiring (NfsenClient / ServiceProvider)

`NfseDistributor` ja recebe `SendsHttpRequests` via `NfsenClient`. Precisa mudar para injetar `SendsRawHttpRequests`. Como `NfseHttpClient` implementa ambas, o wiring muda minimamente:
- Em `NfsenClient`: o `NfseDistributor` recebe o mesmo `$httpClient`, mas tipado como `SendsRawHttpRequests`
- No `ServiceProvider`: bind de `SendsRawHttpRequests` aponta para `NfseHttpClient` (mesmo singleton)

## Nao-escopo

- **Nao muda `SendsHttpRequests`** — interface existente intocada.
- **Nao muda `NfseHttpClient::request()`** — o `get()` existente continua com o mesmo comportamento para callers SEFIN.
- **Nao muda `NfseConsulter`, `NfseResponsePipeline`, `NfseRequestPipeline`** — usam `SendsHttpRequests::get()` como antes.
- **Nao muda o truncamento de 500 chars do `HttpException`** — fora de escopo.
- **Nao trata `[]` como `NenhumDocumentoLocalizado`** — sem evidencia de que a API usa `[]` para isso.

## Testes

- `NfseHttpClient::getResponse()` — retorna `HttpResponse` com status, json, body para 2xx, 4xx, 5xx, body vazio, body nao-JSON
- `DistribuicaoResponse::fromHttpResponse()` — cenarios:
  - HTTP 200 + JSON com `StatusProcessamento` valido → sucesso (delega a `fromApiResult`)
  - HTTP 200 + JSON sem `StatusProcessamento` → `INVALID_RESPONSE` com raw JSON (comportamento v2.1.1)
  - HTTP 200 + body vazio → `EMPTY_RESPONSE`
  - HTTP 429 + body texto → `HTTP_429` com body no complemento
  - HTTP 302 + body vazio → `HTTP_302`
  - HTTP 500 + JSON de erro → `HTTP_500` com body no complemento
- `NfseDistributor::executeRequest()` — integration: usa `getResponse()`, nao lanca excepcao
- Wiring tests: `NfsenClient` injeta corretamente
