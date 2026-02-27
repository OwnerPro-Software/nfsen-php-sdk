# Reescrita nfse-nacional — Plano de Implementação

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Reescrever o pacote nfse-nacional com namespace `Pulsar\NfseNacional`, integração nativa com Laravel HTTP client (mTLS via tmpfile), testes automatizados e API pública fluente.

**Architecture:** Pacote Laravel com suporte standalone. `NfseClient::for()` (via container) ou `NfseClient::forStandalone()` (sem Laravel) recebem cert PFX + prefeitura e retornam instância pronta; `emitir()`, `cancelar()` e `consultar()->nfse/dps/danfse/eventos()` orquestram builders XML, assinatura, compressão e HTTP. Código novo vive em `src-new/` (namespace `Pulsar\NfseNacional`); código legado coexiste em `src/` (namespace `Hadder\NfseNacional`) via dual autoload até Task 18 (limpeza: `src/` → `src-old/`, `src-new/` → `src/`).

> **Nota standalone:** Em modo standalone (sem Laravel bootado), os Laravel Events (`NfseEmitted`, `NfseFailed`, etc.) **não são disparados** — o `dispatchEvent()` silencia a ausência do dispatcher. Todas as demais funcionalidades (emitir, cancelar, consultar) operam normalmente.

**Tech Stack:** PHP 8.2+, Laravel 11/12 (illuminate/http, illuminate/support), nfephp-org/sped-common, Pest 3 + orchestra/testbench 9.

---

## Convenções

- Namespace: `Pulsar\NfseNacional`
- Testes rodam com: `./vendor/bin/pest`
- Fixtures de cert: `tests/fixtures/certs/fake.pfx` (senha: `secret`) — sem OID ICP-Brasil
- Fixtures de cert: `tests/fixtures/certs/fake-icpbr.pfx` (senha: `secret`) — com OID ICP-Brasil (CNPJ extraível via `Certificate::getCnpj()`)
- Fixtures de cert: `tests/fixtures/certs/expired.pfx` (senha: `secret`) — certificado expirado (fixture estática)
- Fixtures de resposta: `tests/fixtures/responses/*.json`
- Todos os `stdClass` internos de DpsData mantêm propriedades em **minúsculas** (padrão atual via `propertiesToLower`)

---


> **Parte 3 de 3** — Tasks 15–18 (Integração): ServiceProvider, NfseClient, Feature tests, limpeza final.

> **Pré-requisitos completos (Partes 1 e 2):** Todos os builders XML (DpsBuilder, PrestadorBuilder, TomadorBuilder, ServicoBuilder, ValoresBuilder, EventoBuilder), NfseHttpClient, ConsultaBuilder, NfseClientContract, Events e suas respectivas test suites já existem. `tests/TestCase.php`, `tests/Pest.php` com `uses()`, `tests/helpers.php` e `tests/datasets.php` já estão configurados.

> **IMPORTANTE — Ordem de execução:** As Tasks 15 e 16 foram reordenadas neste plano. A Task 15 (ServiceProvider) DEVE ser executada ANTES da Task 16 (NfseClient) para que os feature tests usem o path via container.

---

## Task 15: ServiceProvider, Facade e Config

> **Executar ANTES da Task 16 (NfseClient).** O ServiceProvider precisa existir para que os feature tests usem o path via container.

**Files:**
- Create: `src-new/NfseNacionalServiceProvider.php`
- Create: `src-new/Facades/NfseNacional.php`
- Create: `config/nfse-nacional.php`
- Create: `tests/Feature/ServiceProviderTest.php`

**Step 1: Escrever teste**

`tests/Feature/ServiceProviderTest.php`:
```php
<?php

use Pulsar\NfseNacional\Facades\NfseNacional;
use Pulsar\NfseNacional\NfseClient;

it('resolves NfseClient from container', function () {
    $client = app(NfseClient::class);
    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('NfseNacional facade resolves NfseClient', function () {
    expect(NfseNacional::getFacadeRoot())->toBeInstanceOf(NfseClient::class);
});

it('config nfse-nacional is published', function () {
    expect(config('nfse-nacional.ambiente'))->not->toBeNull();
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Feature/ServiceProviderTest.php --no-coverage
```
Expected: FAIL

**Step 3: Criar config**

`config/nfse-nacional.php`:
```php
<?php

use Pulsar\NfseNacional\Enums\NfseAmbiente;

return [
    'ambiente'          => env('NFSE_AMBIENTE', NfseAmbiente::HOMOLOGACAO->value),
    'prefeitura'        => env('NFSE_PREFEITURA', null),
    'certificado' => [
        'path'  => env('NFSE_CERT_PATH'),
        'senha' => env('NFSE_CERT_SENHA'),
    ],
    'timeout'           => env('NFSE_TIMEOUT', 30),
    'signing_algorithm' => env('NFSE_SIGNING_ALGORITHM', 'sha1'),
    'ssl_verify'        => env('NFSE_SSL_VERIFY', true),
];
```

**Step 4: Expandir ServiceProvider** (substitui o stub criado na Task 12)

`src-new/NfseNacionalServiceProvider.php`:
```php
<?php

namespace Pulsar\NfseNacional;

use Illuminate\Support\ServiceProvider;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Xml\DpsBuilder;

class NfseNacionalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nfse-nacional.php', 'nfse-nacional');

        $this->app->bind(NfseClient::class, function ($app) {
            $config   = $app['config']['nfse-nacional'];
            $jsonPath = __DIR__ . '/../storage/prefeituras.json';

            $client = new NfseClient(
                // fromConfig() aceita int|string: '1', '2', 'producao', 'homologacao'
                ambiente:           NfseAmbiente::fromConfig($config['ambiente']),
                timeout:            (int) $config['timeout'],
                signingAlgorithm:   $config['signing_algorithm'],
                sslVerify:          (bool) $config['ssl_verify'],
                prefeituraResolver: new PrefeituraResolver($jsonPath),
                dpsBuilder:         new DpsBuilder(__DIR__ . '/../storage/schemes'),
            );

            // Auto-configurar se cert + prefeitura estão no config
            $certPath    = $config['certificado']['path'] ?? null;
            $certSenha   = $config['certificado']['senha'] ?? null;
            $prefeitura  = $config['prefeitura'] ?? null;

            if ($certPath && $certSenha && $prefeitura && file_exists($certPath)) {
                $client->configure(file_get_contents($certPath), $certSenha, $prefeitura);
            }

            return $client;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/nfse-nacional.php' => config_path('nfse-nacional.php'),
            ], 'nfse-nacional-config');
        }
    }
}
```

**Step 5: Criar Facade**

`src-new/Facades/NfseNacional.php`:
```php
<?php

namespace Pulsar\NfseNacional\Facades;

use Illuminate\Support\Facades\Facade;
use Pulsar\NfseNacional\NfseClient;

/**
 * @method static NfseClient for(string $pfxContent, string $senha, string $prefeitura)
 */
class NfseNacional extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NfseClient::class;
    }
}
```

**Step 6: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Feature/ServiceProviderTest.php --no-coverage
```
Expected: PASS

**Step 7: Rodar toda a suite**

```bash
./vendor/bin/pest --no-coverage
```
Expected: PASS (todos os testes)

**Step 8: Commit**

```bash
git add src-new/NfseNacionalServiceProvider.php src-new/Facades/ config/
git commit -m "feat: add ServiceProvider, Facade e config — NfseClient ligado ao container Laravel"
```

---


---

## Task 16: NfseClient — emitir e consultar

**Files:**
- Create: `src-new/NfseClient.php`
- Create: `tests/fixtures/responses/emitir_sucesso.json`
- Create: `tests/fixtures/responses/emitir_rejeicao.json`
- Create: `tests/fixtures/responses/consultar_nfse.json`
- Create: `tests/fixtures/responses/consultar_dps.json`
- Create: `tests/fixtures/responses/consultar_danfse.json`
- Create: `tests/fixtures/responses/consultar_eventos.json`
- Create: `tests/Feature/NfseClientEmitirTest.php`

**Step 1: Criar fixtures de resposta**

`tests/fixtures/responses/emitir_sucesso.json`:
```json
{
  "chNFSe": "35016082026022700000000000000000000000000000000001",
  "nProtNFSe": "135016080000001"
}
```

`tests/fixtures/responses/emitir_rejeicao.json`:
```json
{
  "erros": [
    {"codigo": "E001", "descricao": "CNPJ do prestador inválido"}
  ]
}
```

`tests/fixtures/responses/consultar_nfse.json`:
```json
{
  "nfseXmlGZipB64": "__PLACEHOLDER__"
}
```
> Substituir `__PLACEHOLDER__` em tempo de teste ou criar o valor correto:
```bash
php -r "echo base64_encode(gzencode('<NFSe xmlns=\"http://www.sped.fazenda.gov.br/nfse\"/>'));"
```
Copiar o output e substituir `__PLACEHOLDER__`.

`tests/fixtures/responses/consultar_dps.json`:
```json
{
  "dpsXmlGZipB64": ""
}
```

`tests/fixtures/responses/consultar_danfse.json`:
```json
{
  "danfseUrl": "https://danfse.exemplo.com/CHAVE123"
}
```

`tests/fixtures/responses/consultar_eventos.json`:
```json
{
  "eventos": []
}
```

**Step 2: Escrever testes de feature**

`tests/Feature/NfseClientEmitirTest.php`:
```php
<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\NfseClient;

// makePfxContent() definida em tests/helpers.php (criado na Task 8)

it('emitir returns success NfseResponse', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_sucesso.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->not->toBeNull();
})->with('dpsData');

it('emitir returns rejection NfseResponse on erro field', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->not->toBeNull();
})->with('dpsData');

it('consultar()->nfse returns success NfseResponse', function () {
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->nfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toContain('<NFSe');
});

it('consultar()->dps returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(['dps' => 'dados'], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->dps('CHAVE123');

    expect($response->sucesso)->toBeTrue();
});

it('throws InvalidArgumentException for invalid IBGE code', function () {
    expect(fn () => NfseClient::for(makePfxContent(), 'secret', '123'))
        ->toThrow(\InvalidArgumentException::class, 'IBGE');
});

it('forStandalone creates client without Laravel container', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_STANDALONE'], 200)]);

    $client = NfseClient::forStandalone(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_STANDALONE');
})->with('dpsData');

it('emitir returns rejection with erros array', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('CNPJ');
})->with('dpsData');
```

**Step 3: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Feature/ --no-coverage
```
Expected: FAIL

**Step 4: Implementar NfseClient**

> **Nota multi-tenant:** `NfseClient::for()` sempre sobrescreve a configuração do container com os parâmetros passados. Isso é intencional para suportar multi-tenant (cada tenant com seu cert/prefeitura). Para single-tenant usando cert/prefeitura do `config/nfse-nacional.php`, usar `app(NfseClient::class)` direto sem `for()`.

`src-new/NfseClient.php`:
```php
<?php

namespace Pulsar\NfseNacional;

use Illuminate\Container\Container;
use Pulsar\NfseNacional\Certificates\CertificateManager;
use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseFailed;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Http\NfseHttpClient;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Signing\XmlSigner;
use Pulsar\NfseNacional\Xml\Builders\EventoBuilder;
use Pulsar\NfseNacional\Xml\DpsBuilder;

class NfseClient implements NfseClientContract
{
    private ?CertificateManager $certManager = null;
    private ?string $prefeitura = null;
    private ?NfseHttpClient $httpClient = null;

    public function __construct(
        private readonly NfseAmbiente $ambiente,
        private readonly int $timeout,
        private readonly string $signingAlgorithm,
        private readonly bool $sslVerify,
        private readonly PrefeituraResolver $prefeituraResolver,
        private readonly DpsBuilder $dpsBuilder,
    ) {}

    /**
     * Factory via Laravel container — usa config do ServiceProvider como base.
     * Se o container Laravel não estiver disponível, faz fallback para forStandalone().
     */
    public static function for(string $pfxContent, string $senha, string $prefeitura): static
    {
        if (class_exists(\Illuminate\Container\Container::class)
            && \Illuminate\Container\Container::getInstance()->bound(static::class)
        ) {
            return app(static::class)->configure($pfxContent, $senha, $prefeitura);
        }

        return static::forStandalone($pfxContent, $senha, $prefeitura);
    }

    /**
     * Factory standalone — não depende do container Laravel.
     */
    public static function forStandalone(
        string $pfxContent,
        string $senha,
        string $prefeitura,
        NfseAmbiente $ambiente = NfseAmbiente::HOMOLOGACAO,
        int $timeout = 30,
        string $signingAlgorithm = 'sha1',
        bool $sslVerify = true,
        ?string $prefeiturasJsonPath = null,
        ?string $schemesPath = null,
    ): static {
        $jsonPath    = $prefeiturasJsonPath ?? __DIR__ . '/../storage/prefeituras.json';
        $schemasPath = $schemesPath ?? __DIR__ . '/../storage/schemes';

        $instance = new static(
            ambiente:           $ambiente,
            timeout:            $timeout,
            signingAlgorithm:   $signingAlgorithm,
            sslVerify:          $sslVerify,
            prefeituraResolver: new PrefeituraResolver($jsonPath),
            dpsBuilder:         new DpsBuilder($schemasPath),
        );

        return $instance->configure($pfxContent, $senha, $prefeitura);
    }

    public function configure(string $pfxContent, string $senha, string $prefeitura): static
    {
        // Validate IBGE early
        $this->prefeituraResolver->resolveSeFinUrl($prefeitura, $this->ambiente);

        $this->certManager = new CertificateManager($pfxContent, $senha);
        $this->prefeitura  = $prefeitura;
        $this->httpClient  = new NfseHttpClient($this->certManager->getCertificate(), $this->timeout, $this->sslVerify);

        return $this;
    }

    private function ensureConfigured(): void
    {
        if ($this->certManager === null || $this->prefeitura === null || $this->httpClient === null) {
            throw new \Pulsar\NfseNacional\Exceptions\NfseException(
                'NfseClient não configurado. Use NfseClient::for() ou configure certificado/prefeitura no config/nfse-nacional.php.'
            );
        }
    }

    /**
     * Dispatch de events opcional — silencia se não houver dispatcher (modo standalone).
     * Em modo standalone sem Laravel, events não são disparados.
     */
    private function dispatchEvent(object $event): void
    {
        if (function_exists('event')) {
            try {
                event($event);
            } catch (\Throwable) {
                // No event dispatcher available (standalone mode)
            }
        }
    }

    public function emitir(DpsData $data): NfseResponse
    {
        $this->ensureConfigured();
        $operacao = 'emitir';
        $this->dispatchEvent(new NfseRequested($operacao, []));

        try {
            // DpsBuilder retorna sem <?xml...?> (saveXML($doc->documentElement))
            $xml     = $this->dpsBuilder->build($data);
            $signer  = new XmlSigner($this->certManager->getCertificate(), $this->signingAlgorithm);
            // Signer retorna só o elemento assinado — adiciona declaração XML uma única vez
            $signed  = '<?xml version="1.0" encoding="UTF-8"?>' . $signer->sign($xml, 'infDPS', 'DPS');
            $payload = ['dpsXmlGZipB64' => base64_encode(gzencode($signed))];

            $seFinUrl   = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
            $opPath     = $this->prefeituraResolver->resolveOperation($this->prefeitura, 'emitir_nfse');
            $url        = rtrim($seFinUrl, '/') . ($opPath ? '/' . ltrim($opPath, '/') : '');

            $result = $this->httpClient->post($url, $payload);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Rejeição sem descrição';
                $codigo = $result['erros'][0]['codigo'] ?? 'UNKNOWN';
                $this->dispatchEvent(new NfseRejected($operacao, $codigo));
                return new NfseResponse(false, null, null, $erro);
            }

            $chave = $result['chNFSe'] ?? null;
            $this->dispatchEvent(new NfseEmitted($chave ?? ''));

            return new NfseResponse(true, $chave, null, null);
        } catch (HttpException $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Cancelar NFSe. O CNPJ/CPF do autor é extraído do certificado via OID ICP-Brasil.
     * Para certs sem OID ICP-Brasil, getCnpj()/getCpf() retornam string vazia.
     * Testes de cancelar devem usar fake-icpbr.pfx (com OID) para validar extração.
     */
    public function cancelar(string $chave, MotivoCancelamento $motivo, string $descricao): NfseResponse
    {
        $this->ensureConfigured();
        $operacao = 'cancelar';
        $this->dispatchEvent(new NfseRequested($operacao, compact('chave')));

        try {
            $cert = $this->certManager->getCertificate();
            $cnpj = $cert->getCnpj() ?: null;
            $cpf  = $cert->getCpf() ?: null;

            $xml = (new EventoBuilder())->build(
                tpAmb:     $this->ambiente->value,
                verAplic:  '1.0',
                dhEvento:  date('c'),
                cnpjAutor: $cnpj,
                cpfAutor:  $cpf,
                chNFSe:    $chave,
                motivo:    $motivo,
                descricao: $descricao,
            );

            $signer  = new XmlSigner($cert, $this->signingAlgorithm);
            // EventoBuilder retorna sem <?xml...?> — adiciona declaração uma única vez
            $signed  = '<?xml version="1.0" encoding="UTF-8"?>' . $signer->sign($xml, 'infPedReg', 'pedRegEvento');
            $payload = ['pedidoRegistroEventoXmlGZipB64' => base64_encode(gzencode($signed))];

            $seFinUrl  = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
            $opPath    = $this->prefeituraResolver->resolveOperation(
                $this->prefeitura, 'cancelar_nfse', ['chave' => $chave]
            );
            $url = rtrim($seFinUrl, '/') . ($opPath ? '/' . ltrim($opPath, '/') : '');

            $result = $this->httpClient->post($url, $payload);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro   = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Rejeição';
                $codigo = $result['erros'][0]['codigo'] ?? 'UNKNOWN';
                $this->dispatchEvent(new NfseRejected($operacao, $codigo));
                return new NfseResponse(false, null, null, $erro);
            }

            $this->dispatchEvent(new NfseCancelled($chave));
            return new NfseResponse(true, $chave, null, null);
        } catch (HttpException $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    public function consultar(): ConsultaBuilder
    {
        $this->ensureConfigured();
        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
        $adnUrl   = $this->prefeituraResolver->resolveAdnUrl($this->prefeitura, $this->ambiente);
        return new ConsultaBuilder(
            $this, $seFinUrl, $adnUrl,
            $this->prefeituraResolver, $this->prefeitura,
        );
    }

    public function executeGet(string $url): NfseResponse
    {
        $this->ensureConfigured();
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao, compact('url')));

        try {
            $result = $this->httpClient->get($url);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? 'UNKNOWN'));
                return new NfseResponse(false, null, null, $erro);
            }

            // NFSe/DPS XML: descomprime se vier gzip+base64
            $xml = null;
            $gzipB64 = $result['nfseXmlGZipB64'] ?? $result['dpsXmlGZipB64'] ?? null;
            if ($gzipB64) {
                $xml = gzdecode(base64_decode($gzipB64)) ?: null;
            }

            $this->dispatchEvent(new NfseQueried('nfse'));
            return new NfseResponse(true, null, $xml, null);
        } catch (HttpException $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    public function executeGetRaw(string $url): array
    {
        $this->ensureConfigured();
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao, compact('url')));

        try {
            $result = $this->httpClient->get($url);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? 'UNKNOWN'));
                throw new \Pulsar\NfseNacional\Exceptions\NfseException($erro);
            }

            $this->dispatchEvent(new NfseQueried($operacao));
            return $result;
        } catch (HttpException $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }
}
```

**Step 5: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Feature/ --no-coverage
```
Expected: PASS (6 testes)

**Step 6: Commit**

```bash
git add src-new/NfseClient.php tests/Feature/ tests/fixtures/responses/
git commit -m "feat: add NfseClient — emitir, cancelar, consultar com events"
```

---


---

## Task 17: Feature test — cancelar, consultar, events

**Files:**
- Create: `tests/fixtures/responses/cancelar_sucesso.json`
- Create: `tests/fixtures/responses/cancelar_rejeicao.json`
- Create: `tests/Feature/NfseClientCancelarTest.php`
- Create: `tests/Feature/NfseClientConsultarTest.php`
- Create: `tests/Feature/EventsDispatchTest.php`

**Step 0: Verificar que helpers.php já está carregado**

`tests/helpers.php` já foi criado na Task 8 com `makePfxContent()` e `makeTestCertificate()`. Verificar que todos os feature tests existentes ainda passam:

```bash
./vendor/bin/pest tests/Feature/ --no-coverage
```
Expected: PASS

**Step 1: Criar fixtures**

`tests/fixtures/responses/cancelar_sucesso.json`:
```json
{
  "chNFSe": "35016082026022700000000000000000000000000000000001",
  "dhRegistro": "2026-02-27T10:00:00-03:00"
}
```

`tests/fixtures/responses/cancelar_rejeicao.json`:
```json
{
  "erros": [
    {"codigo": "E201", "descricao": "NFSe não encontrada para cancelamento"}
  ]
}
```

**Step 2: Escrever testes**

`tests/Feature/NfseClientCancelarTest.php`:
```php
<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\NfseClient;

it('cancelar returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    // Usa fake-icpbr.pfx para que getCnpj() extraia o CNPJ via OID ICP-Brasil
    $pfx      = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client   = NfseClient::for($pfx, 'secret', '3501608');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeTrue();
});

it('cancelar returns rejection NfseResponse on erro field', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_rejeicao.json'), true),
        200
    )]);

    $pfx      = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client   = NfseClient::for($pfx, 'secret', '3501608');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('não encontrada');
});

it('cancelar works with cert without ICP-Brasil OID', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    // Usa fake.pfx (sem OID) — getCnpj() retorna vazio, XML terá CNPJAutor/CPFAutor ausentes
    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeTrue();
});
```

`tests/Feature/NfseClientConsultarTest.php`:
```php
<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\NfseClient;

it('consultar()->danfse returns DanfseResponse with url', function () {
    Http::fake(['*' => Http::response(['danfseUrl' => 'https://danfse.url/CHAVE123'], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->danfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.url/CHAVE123');
});

it('consultar()->eventos returns EventosResponse', function () {
    Http::fake(['*' => Http::response(['eventos' => [['tipo' => '101101']]], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toHaveCount(1);
});

it('consultar()->eventos returns empty array when no events', function () {
    Http::fake(['*' => Http::response(['eventos' => []], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toBeEmpty();
});
```

`tests/Feature/EventsDispatchTest.php`:
```php
<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\NfseClient;

it('dispatches NfseRequested and NfseEmitted on successful emitir', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE123'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $client->emitir($data);

    Event::assertDispatched(NfseRequested::class);
    Event::assertDispatched(NfseEmitted::class);
})->with('dpsData');

it('dispatches NfseCancelled on successful cancelar', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE123'], 200)]);

    $pfx    = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client = NfseClient::for($pfx, 'secret', '3501608');
    $client->cancelar('CHAVE50CARACTERES1234567890123456789012345678901', MotivoCancelamento::ErroEmissao, 'Erro');

    Event::assertDispatched(NfseCancelled::class);
});

it('dispatches NfseQueried on successful consultar', function () {
    Event::fake();
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $client->consultar()->nfse('CHAVE123');

    Event::assertDispatched(NfseQueried::class);
});
```

**Step 3: Rodar para confirmar falha (makePfxContent não está no escopo desses testes)**

`tests/helpers.php` já existe (criado na Task 8 com `makePfxContent()` e `makeTestCertificate()`). `tests/Pest.php` já faz `require_once` de helpers.php desde a Task 8. Nenhuma alteração necessária aqui.

**Step 4: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Feature/ --no-coverage
```
Expected: PASS

**Step 5: Rodar a suite completa**

```bash
./vendor/bin/pest --no-coverage
```
Expected: PASS (todos os testes)

**Step 6: Commit**

```bash
git add tests/
git commit -m "test: feature tests para cancelar e dispatch de events"
```

---


---

## Task 18: CHANGELOG e limpeza final

**Files:**
- Create: `CHANGELOG.md`
- Modify: `composer.json` (remover autoload `Hadder\NfseNacional`, remover `symfony/var-dumper` e `tecnickcom/tcpdf`, apontar `Pulsar` para `src/`)
- Delete: `Helpers.php` (guard `function_exists` adicionado na Task 1 — agora remover completamente)
- Rename: `src/` → `src-old/` (código legado `Hadder\NfseNacional` preservado para referência)
- Rename: `src-new/` → `src/` (código novo `Pulsar\NfseNacional` assume o diretório principal)
- Modify: `storage/prefeituras.json` (remover chaves por nome legado, manter só IBGE)

**Step 1: Renomear diretórios — src/ → src-old/, src-new/ → src/**

Mover a implementação legada (`Hadder\NfseNacional`) para `src-old/` e promover a implementação nova (`Pulsar\NfseNacional`) para `src/`:

```bash
mv src/ src-old/
mv src-new/ src/
```

> **Nota:** Após este passo, o namespace `Pulsar\NfseNacional` passa a viver em `src/` — layout padrão de pacote. O código legado fica em `src-old/` para referência e pode ser removido quando não for mais necessário.

**Step 2: Atualizar autoload no composer.json**

Remover a entrada `Hadder\NfseNacional` do PSR-4 e apontar `Pulsar\NfseNacional` para `src/` (agora que o rename foi feito):

```json
"autoload": {
  "psr-4": {
    "Pulsar\\NfseNacional\\": "src/"
  }
}
```

**Step 3: Remover dependências legadas do composer.json**

Remover `tecnickcom/tcpdf` e `symfony/var-dumper` do `require`. Verificar antes que nenhum arquivo em `src/` (já renomeado) com namespace `Pulsar\NfseNacional` as importa:

```bash
grep -r "use TCPDF\|use Symfony\\Component\\VarDumper\|use Symfony\\Component\\Debug" src/ --include="*.php"
```
Expected: nenhum resultado.

**Step 4: Remover Helpers.php**

Deletar o arquivo `Helpers.php` da raiz do projeto (o `now()` do `illuminate/support` o substitui):

```bash
rm Helpers.php
```

**Step 5: Limpar chaves por nome no prefeituras.json**

Remover entradas com chave por nome legado (ex: `americana-sp`), mantendo apenas as chaves numéricas IBGE (7 dígitos). Verificar que cada prefeitura tem apenas a entrada IBGE.

**Step 6: Criar CHANGELOG.md**

```markdown
# Changelog

## [Unreleased]

### Breaking Changes
- Requisito mínimo de PHP alterado de 8.1 para **8.2**
- Namespace alterado de `Hadder\NfseNacional` para `Pulsar\NfseNacional`
- Identificação de prefeituras exclusivamente por **código IBGE** (7 dígitos); suporte a nome legado (`americana-sp`) removido
- API pública completamente nova: `NfseClient::for($pfx, $senha, $ibge)->emitir($dpsData)`

### Added
- `NfseClient::for()` — instância configurada por tenant via container Laravel (com fallback automático para standalone)
- `NfseClient::forStandalone()` — instância sem dependência do container Laravel
- Fluent consulta: `consultar()->nfse/dps/danfse/eventos($chave)`
- DTOs tipados: `DpsData`, `NfseResponse`, `DanfseResponse`, `EventosResponse`
- Laravel Events: `NfseRequested`, `NfseEmitted`, `NfseCancelled`, `NfseQueried`, `NfseFailed`, `NfseRejected`
- mTLS via `tmpfile()` — sem escrita nomeada em disco, sem CNPJ no path
- SSL habilitado corretamente (`verify: true`)
- Validação XSD do DPS via `DpsBuilder::buildAndValidate()`

### Removed
- Namespace legado `Hadder\NfseNacional` (autoload removido)
- Dependências `symfony/var-dumper` e `tecnickcom/tcpdf`
- Arquivo `Helpers.php` com `now()` global (substituído por `illuminate/support`)
- Suporte a identificação de prefeitura por nome (chaves por nome removidas do JSON)
- Chaves duplicadas por nome no `prefeituras.json` (mantido apenas IBGE 7 dígitos)
```

**Step 7: Rodar `composer update` para limpar deps removidas**

```bash
composer update --no-dev
composer install
```

**Step 8: Rodar suite completa uma última vez**

```bash
./vendor/bin/pest --no-coverage
```
Expected: PASS (todos os testes)

**Step 9: Commit final**

```bash
git add -A
git commit -m "chore: CHANGELOG, rename src-new→src, remoção de autoload/deps legadas, Helpers.php e limpeza de prefeituras.json"
```

---

## Pendência: EmissorNacional (investigar antes de implementar)

O código legado possui URLs `nfse_homologacao`/`nfse_producao` (apontando para `EmissorNacional`) e operations `consultar_danfse_nfse_certificado`/`consultar_danfse_nfse_download` que fazem login com certificado via cookie/redirect (origem=3 no `RestCurl`) para download de DANFSe em PDF. Essas funcionalidades **não estão cobertas** neste plano.

**Ação:** Investigar se o fluxo EmissorNacional ainda é necessário ou se as APIs SeFin/ADN já cobrem todos os casos de uso. Se necessário, criar uma Task 19 futura para implementar.

---

## Resumo das tarefas

| # | Tarefa | Arquivos-chave |
|---|--------|----------------|
| 1 | Bootstrap | composer.json (dual autoload), phpunit.xml, fake.pfx, fake-icpbr.pfx, expired.pfx |
| 2 | Enums | NfseAmbiente (+ fromConfig com throw), MotivoCancelamento |
| 3 | Exceptions | NfseException, CertificateExpiredException, HttpException |
| 4 | DTOs | NfseResponse, DpsData, DanfseResponse, EventosResponse |
| 5 | CertificateManager | CertificateManager.php (expired.pfx fixture estática) |
| 6 | PrefeituraResolver | PrefeituraResolver.php |
| 7 | XmlSigner | XmlSigner.php |
| 8 | DpsBuilder cabeçalho + PrestadorBuilder | DpsBuilder.php, PrestadorBuilder.php, Pest.php, datasets.php |
| 9 | TomadorBuilder + ServicoBuilder + ValoresBuilder | Integração DpsBuilder (validação delegada ao XSD) |
| 10 | DpsBuilder XSD validation | DpsBuilder::buildAndValidate() separado de build(); teste op vazia |
| 11 | EventoBuilder | EventoBuilder.php |
| 12 | NfseHttpClient | NfseHttpClient.php, ServiceProvider stub, TestCase.php |
| 13 | NfseClientContract + ConsultaBuilder | Resolver obrigatório, DanfseResponse/EventosResponse tipados |
| 14 | Events | 6 classes: NfseRequested, NfseEmitted, NfseCancelled, NfseQueried, NfseFailed, NfseRejected |
| 15 | NfseClient (emitir + consultar) | for() com fallback standalone, forStandalone(), eventos tipados |
| 16 | ServiceProvider + Facade + Config | Auto-config via config, guard contra uso sem configure() |
| 17 | Feature tests cancelar + consultar + events | cancelar rejeição, danfse, eventos, dispatch events |
| 18 | CHANGELOG + limpeza | Rename src→src-old + src-new→src, remover autoload Hadder, deps legadas, Helpers.php, chaves por nome no JSON |
