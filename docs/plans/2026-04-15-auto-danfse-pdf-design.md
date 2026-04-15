# Auto-geração de DANFSE PDF em operações de NFS-e — Design

**Data**: 2026-04-15
**Status**: Proposto
**Autor**: Jonathan Martins

## Contexto

O commit `e054601` adicionou `NfsenClient::danfe($config)->toPdf($xml)` para renderizar o DANFSE localmente a partir do XML da NFS-e autorizada. Hoje a geração é um passo manual posterior ao emit.

Este design cobre duas melhorias:

1. **Auto-geração do PDF** junto ao retorno do XML nas operações que produzem/retornam NFS-e autorizada (`emitir`, `emitirDecisaoJudicial`, `substituir`, `consultar()->nfse()`), **sem acoplar as dependências DANFSE (`dompdf`, `bacon-qr-code`) ao core de emissão**.
2. **Refactor ergonômico** do acesso ao renderer: renomear `danfe()` → `danfse()`, aceitar `array` payload no lugar de `new DanfseConfig` construído pelo cliente.

O release do commit `e054601` ainda não foi publicado, portanto o rename é um hard rename sem alias de compatibilidade.

## Objetivos

- Clientes Laravel que configuram `config/nfsen.php` recebem PDF automaticamente, sem mudança de código de chamada.
- Sistemas multi-tenant passam `danfse: [...]` por requisição, sobrescrevendo configuração global.
- Clientes que não usam DANFSE permanecem sem impacto: zero overhead, zero instanciação das dependências pesadas.
- Núcleo hexagonal (`Operations/NfseEmitter`, `NfseSubstitutor`, `NfseConsulter`) permanece alheio a DANFSE.

## Não-objetivos

- Gerar PDF quando o endpoint ADN oficial estiver disponível (isso é responsabilidade de `consultar()->danfse($chave)`, que permanece inalterada).
- Cache de PDFs ou persistência.
- Geração síncrona de DANFSE para eventos (cancelamento, substituição de terceiros) — apenas o XML principal da NFS-e emitida/substituída/consultada.
(Removido — agora há sentinel `danfse: false` em `for()`/`forStandalone()`; ver seção Precedência.)

## Arquitetura

### Padrão: Decoradores sobre driving ports

Cada operação que retorna `NfseResponse` com XML ganha um decorador opcional que anexa o PDF. `NfsenClient` decide em tempo de construção se envolve ou não as operações.

```
src/Operations/
├── NfseEmitter.php                 (existente, inalterado)
├── NfseSubstitutor.php             (existente, inalterado)
├── NfseCanceller.php               (existente, inalterado)
├── NfseConsulter.php               (existente, inalterado)
├── NfseDanfseRenderer.php          (existente, inalterado)
└── Decorators/
    ├── EmitterWithDanfse.php       (novo, implements EmitsNfse)
    ├── SubstitutorWithDanfse.php   (novo, implements SubstitutesNfse)
    ├── ConsulterWithDanfse.php     (novo, implements ConsultsNfse)
    └── Concerns/
        └── AttachesDanfsePdf.php   (novo, trait compartilhado)
```

Convenção alinhada com `src/Pipeline/Concerns/DispatchesEvents.php`.

Exemplo (`EmitterWithDanfse`):

```php
final readonly class EmitterWithDanfse implements EmitsNfse
{
    use AttachesDanfsePdf;

    public function __construct(
        private EmitsNfse $inner,
        private RendersDanfse $renderer,
    ) {}

    public function emitir(DpsData|array $data): NfseResponse
    {
        return $this->attachPdf($this->inner->emitir($data));
    }

    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        return $this->attachPdf($this->inner->emitirDecisaoJudicial($data));
    }
}
```

Trait `AttachesDanfsePdf`:

```php
trait AttachesDanfsePdf
{
    private function attachPdf(NfseResponse $r): NfseResponse
    {
        if (! $r->sucesso || $r->xml === null) {
            return $r;
        }

        $danfse = $this->renderer->toPdf($r->xml);

        return new NfseResponse(
            sucesso: $r->sucesso,
            chave: $r->chave,
            xml: $r->xml,
            idDps: $r->idDps,
            alertas: $r->alertas,
            erros: $r->erros,
            tipoAmbiente: $r->tipoAmbiente,
            versaoAplicativo: $r->versaoAplicativo,
            dataHoraProcessamento: $r->dataHoraProcessamento,
            pdf: $danfse->pdf,
            pdfErrors: $danfse->erros,
        );
    }
}
```

`ConsulterWithDanfse implements ConsultsNfse` deve implementar **todos** os métodos da interface. Só `nfse($chave)` aplica `attachPdf`; os demais são pass-through literais:

```php
public function dps(string $id): NfseResponse { return $this->inner->dps($id); }
public function danfse(string $chave): DanfseResponse { return $this->inner->danfse($chave); }
public function eventos(
    string $chave,
    TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador,
    int $nSequencial = 1,
): EventsResponse {
    return $this->inner->eventos($chave, $tipoEvento, $nSequencial);
}
public function verificarDps(string $id): bool { return $this->inner->verificarDps($id); }
```

### Propriedades da escolha

- **SRP preservado**: `NfseEmitter` continua emitindo NFS-e; nunca importa `RendersDanfse`.
- **Dependências isoladas**: clientes sem DANFSE não instanciam decorators, logo `dompdf`/`bacon-qr-code` não são carregados no fluxo de emit. As libs permanecem em `require` (uso eventual via `NfsenClient::danfse()`), mas o fluxo de emissão puro não as toca.
- **Testável por camada**: decorators testados com fakes de `EmitsNfse`/`RendersDanfse`; `NfseEmitter` original não precisa de novos casos.

### Composição em `NfsenClient`

```php
/**
 * @param  array|false|null  $danfse
 *         - `null` (default): sem auto-render.
 *         - array: ativa auto-render com a config fornecida. A chave `enabled` dentro do array
 *                  é IGNORADA aqui (só tem efeito em `NfsenClient::for()` lendo config global).
 *                  Shape: logo_path?, logo_data_uri?, municipality?{name, department?, email?, logo_path?, logo_data_uri?}.
 *         - `false`: sentinel; no fluxo `forStandalone()` equivale a `null`. Útil apenas em `NfsenClient::for()`
 *                   para sobrescrever `config.danfse.enabled=true` e forçar desligamento pontual.
 */
public static function forStandalone(
    #[SensitiveParameter] string $pfxContent,
    #[SensitiveParameter] string $senha,
    string $prefeitura,
    NfseAmbiente $ambiente = NfseAmbiente::HOMOLOGACAO,
    int $timeout = 30,
    string $signingAlgorithm = 'sha1',
    bool $sslVerify = true,
    ?string $prefeiturasJsonPath = null,
    ?string $schemasPath = null,
    int $connectTimeout = 10,
    bool $validateIdentity = true,
    array|false|null $danfse = null,
): self {
    // Construção atual (inalterada): $prefeituraResolver, $certManager, $httpClient,
    // $signer, $pipeline, $queryExecutor, $seFinUrl, $adnUrl.

    $emitter     = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));
    $canceller   = new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente);
    $substitutor = new NfseSubstitutor($emitter);        // IMPORTANTE: usa $emitter cru, nunca o EmitterWithDanfse.
    $consulter   = new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura);

    if ($danfse === null || $danfse === false) {
        return new self($emitter, $canceller, $substitutor, $consulter);
    }

    $renderer = self::buildDanfseRenderer(DanfseConfig::fromArray($danfse));

    return new self(
        emitter:     new EmitterWithDanfse($emitter, $renderer),
        canceller:   $canceller,
        substitutor: new SubstitutorWithDanfse($substitutor, $renderer),
        consulter:   new ConsulterWithDanfse($consulter, $renderer),
    );
}

private static function buildDanfseRenderer(DanfseConfig $config): RendersDanfse
{
    return new NfseDanfseRenderer(
        new DanfseDataBuilder,
        new DanfseHtmlRenderer(new BaconQrCodeGenerator, $config),
        new DompdfHtmlToPdfConverter,
    );
}
```

`NfsenClient::danfse()` (método de acesso direto, ex-`danfe()`) reutiliza `buildDanfseRenderer()`.

**Invariante de wiring**: `NfseSubstitutor` recebe sempre o `$emitter` cru (não decorado). Se um refactor futuro passasse `EmitterWithDanfse` para ele e depois envolvesse o resultado em `SubstitutorWithDanfse`, o PDF renderizaria duas vezes — uma dentro do substitutor (via inner emitter decorado) e outra no decorator externo. Um teste dedicado garante: contador de chamadas no fake `RendersDanfse` assertiona exatamente **uma** invocação de `toPdf()` por `substituir()`.

## Precedência de configuração

Duas rotas convergem no parâmetro `array|false|null $danfse` de `for()`/`forStandalone()`:

- **Laravel simples**: `config/nfsen.php` com `danfse.enabled = true` e demais chaves.
- **Multi-tenant / standalone**: argumento explícito em `NfsenClient::for(..., danfse: [...])` ou `NfsenClient::forStandalone(..., danfse: [...])`.
- **Disable pontual**: `NfsenClient::for(..., danfse: false)` sobrescreve `config.enabled=true` e força desligamento nessa instância.

Lógica em `NfsenClient::for()`:

```php
// false sentinel: força desligado, ignora config global.
if ($danfse === false) {
    return self::forStandalone(...[/* demais args */], danfse: false);
}

// null + config global ativo: usa config.
if ($danfse === null && function_exists('config') && config('nfsen.danfse.enabled') === true) {
    $danfse = config('nfsen.danfse');
}

return self::forStandalone(...[/* demais args */], danfse: $danfse);
```

Nota: quando o array vem do config, inclui a chave `enabled`. `DanfseConfig::fromArray()` aceita-a na whitelist e ignora o valor — a chave só tem efeito aqui, decidindo se o array é repassado ou não.

Precedência:

| Argumento explícito | `config.enabled`    | Resultado                                    |
| ------------------- | ------------------- | -------------------------------------------- |
| `null` (default)    | `false` / ausente   | Sem auto-render                              |
| `null` (default)    | `true`              | Usa `config('nfsen.danfse')` global          |
| array               | qualquer            | Usa array explícito (sobrescreve config)     |
| `false`             | qualquer            | Força desligado (sobrescreve config global)  |

`false` só faz sentido como sentinel em `NfsenClient::for()` (onde existe config global para sobrescrever). Em `forStandalone()`, `false` e `null` produzem o mesmo resultado (sem auto-render) — aceito para ergonomia de repasse direto.

### Shape do array

```php
[
    'enabled'        => true,                        // apenas Laravel; ignorado no standalone
    'logo_path'      => '/absolute/path/logo.png',   // string|false|null
    'logo_data_uri'  => 'data:image/png;base64,...', // opcional, precedência sobre logo_path
    'municipality'   => [
        'name'          => 'São Paulo',
        'department'    => 'SF/SUBTES',
        'email'         => 'nfse@prefeitura.sp.gov.br',
        'logo_path'     => '/absolute/path/brasao.png',
        'logo_data_uri' => 'data:image/png;base64,...',
    ],
]
```

`DanfseConfig::fromArray()` ignora a chave `enabled` (é gate Laravel, não atributo do config). `MunicipalityBranding::fromArray()` ignora seções ausentes.

Consequência: se um consumidor multi-tenant copiar a estrutura do config e passar `['enabled' => false, ...]` para `forStandalone(danfse: $array)`, o auto-render **continuará ligado** — presença do array é o gate, não o valor de `enabled`. A chave `enabled` só tem efeito dentro do fluxo `NfsenClient::for()` lendo o config global.

### `config/nfsen.php`

Bloco `municipality` só é emitido quando `NFSE_DANFSE_MUN_NAME` está definido, evitando que um array parcial atinja `fromArray` e cause falha no boot:

```php
return [
    // ... chaves existentes ...
    'danfse' => [
        'enabled'       => (bool) env('NFSE_DANFSE_AUTO', false),
        'logo_path'     => env('NFSE_DANFSE_LOGO_PATH'),
        'logo_data_uri' => env('NFSE_DANFSE_LOGO_DATA_URI'),
        'municipality'  => env('NFSE_DANFSE_MUN_NAME') ? [
            'name'          => env('NFSE_DANFSE_MUN_NAME'),
            'department'    => env('NFSE_DANFSE_MUN_DEPT', ''),
            'email'         => env('NFSE_DANFSE_MUN_EMAIL', ''),
            'logo_path'     => env('NFSE_DANFSE_MUN_LOGO_PATH'),
            'logo_data_uri' => env('NFSE_DANFSE_MUN_LOGO_DATA_URI'),
        ] : null,
    ],
];
```

Como **defesa em profundidade**, `DanfseConfig::fromArray()` também trata `municipality.name` nulo ou string vazia como sinal de bloco ausente (`municipality === null` no resultado), mesmo que a configuração chegue parcial por outro caminho. Valores positivos de `name` seguem a validação estrita (tipo + não-vazio).

## Contrato do `NfseResponse`

Dois campos novos, ambos opcionais com default seguro:

```php
final readonly class NfseResponse
{
    /**
     * @param list<ProcessingMessage> $alertas
     * @param list<ProcessingMessage> $erros
     * @param list<ProcessingMessage> $pdfErrors
     */
    public function __construct(
        public bool $sucesso,
        public ?string $chave = null,
        public ?string $xml = null,
        public ?string $idDps = null,
        public array $alertas = [],
        public array $erros = [],
        public ?int $tipoAmbiente = null,
        public ?string $versaoAplicativo = null,
        public ?string $dataHoraProcessamento = null,
        public ?string $pdf = null,
        public array $pdfErrors = [],
    ) {}
}
```

Semântica dos estados:

| `sucesso` | `xml`   | `pdf`   | `pdfErrors` | Significado                                          |
| --------- | ------- | ------- | ----------- | ---------------------------------------------------- |
| `true`    | `!null` | `!null` | `[]`        | NFS-e emitida + PDF gerado                           |
| `true`    | `!null` | `null`  | não vazio   | NFS-e emitida OK, PDF falhou (cliente pode retentar) |
| `true`    | `!null` | `null`  | `[]`        | NFS-e emitida, auto-render desligado                 |
| `false`   | `null`  | `null`  | `[]`        | Emit rejeitado (decorator não chama renderer)        |

Nenhum helper método é adicionado; consumidores testam `$response->pdf !== null`.

## Refactor do acesso direto ao renderer

### Rename `danfe()` → `danfse()`

Hard rename. O método foi introduzido no commit anterior e ainda não foi publicado.

### Aceitar array payload

Novo padrão, alinhado com `emitir(DpsData|array)`:

```php
public function danfse(DanfseConfig|array|null $config = null): RendersDanfse
{
    $resolved = $config instanceof DanfseConfig
        ? $config
        : DanfseConfig::fromArray($config ?? []);

    return self::buildDanfseRenderer($resolved);
}
```

Uniformização: `null` e array passam pelo mesmo `fromArray()`, garantindo que o caminho default também exercita a validação. `DanfseConfig` já construído é preservado sem cópia.

Clientes deixam de instanciar `new DanfseConfig` manualmente:

```php
// Antes
$client->danfe(new DanfseConfig(logoPath: '...', municipality: new MunicipalityBranding(name: '...')));

// Depois
$client->danfse(['logo_path' => '...', 'municipality' => ['name' => '...']]);
```

## Tratamento de erros

### Runtime do render

Todas as falhas de render são encapsuladas dentro de `NfseDanfseRenderer::toPdf()`, que retorna `DanfseResponse(sucesso, pdf, erros)` — nunca lança. Consequência: os decoradores **não precisam de try/catch próprio**. O trait `AttachesDanfsePdf` apenas lê `DanfseResponse` e mapeia para `NfseResponse.pdf` + `NfseResponse.pdfErrors`.

### Validação de `DanfseConfig::fromArray()` e `MunicipalityBranding::fromArray()`

Validação **schema-like** em três camadas, com falha rápida via `InvalidArgumentException` na construção do cliente (antes de qualquer operação). Ajuda a capturar erros de configuração (typos em env, chaves trocadas) no boot, não em produção.

1. **Whitelist de chaves**: detecta typos.
2. **Tipos**: `logo_path: string|false|null`, `logo_data_uri: string|null`, `municipality: array|null`, etc.
3. **Regras de negócio**: quando o bloco `municipality` é fornecido, `name` é obrigatório e não pode ser string vazia.

Chaves permitidas:

- `DanfseConfig::fromArray()`: `enabled`, `logo_path`, `logo_data_uri`, `municipality`.
- `MunicipalityBranding::fromArray()`: `name`, `department`, `email`, `logo_path`, `logo_data_uri`.

`rejectUnknownKeys` vive em **trait** reutilizável, alinhado com a convenção de `src/Pipeline/Concerns/DispatchesEvents.php`:

```
src/Danfse/
├── DanfseConfig.php
├── MunicipalityBranding.php
├── LogoLoader.php
├── Formatter.php
├── Municipios.php
└── Concerns/
    └── ValidatesArrayShape.php   (novo)
```

```php
trait ValidatesArrayShape
{
    /**
     * @param array<string, mixed> $data
     * @param list<string> $allowed
     */
    private static function rejectUnknownKeys(array $data, array $allowed, string $context): void
    {
        $unknown = array_diff(array_keys($data), $allowed);
        if ($unknown !== []) {
            throw new InvalidArgumentException(
                sprintf('%s: chave(s) desconhecida(s): %s', $context, implode(', ', $unknown))
            );
        }
    }
}
```

Esboço do `DanfseConfig::fromArray()`:

```php
final readonly class DanfseConfig
{
    use ValidatesArrayShape;

    // 'enabled' é aceito na whitelist como no-op — é o gate de `NfsenClient::for()` (config Laravel)
    // e chega até aqui quando o array é repassado do config. Dentro de fromArray ele não tem efeito.
    private const ALLOWED_KEYS = ['enabled', 'logo_path', 'logo_data_uri', 'municipality'];

    public static function fromArray(array $data): self
    {
        self::rejectUnknownKeys($data, self::ALLOWED_KEYS, 'danfse');

        $logoPath = $data['logo_path'] ?? null;
        if ($logoPath !== null && $logoPath !== false && ! is_string($logoPath)) {
            throw new InvalidArgumentException('danfse.logo_path: esperado string|false|null');
        }

        $logoDataUri = $data['logo_data_uri'] ?? null;
        if ($logoDataUri !== null && ! is_string($logoDataUri)) {
            throw new InvalidArgumentException('danfse.logo_data_uri: esperado string|null');
        }

        $municipality = self::buildMunicipality($data['municipality'] ?? null);

        return new self(
            logoDataUri:  $logoDataUri,
            logoPath:     $logoPath,
            municipality: $municipality,
        );
    }

    private static function buildMunicipality(mixed $raw): ?MunicipalityBranding
    {
        if ($raw === null) {
            return null;
        }
        if (! is_array($raw)) {
            throw new InvalidArgumentException('danfse.municipality: esperado array');
        }
        // Defense in depth: name null/'' → trata como bloco ausente.
        $name = $raw['name'] ?? null;
        if ($name === null || $name === '') {
            return null;
        }
        return MunicipalityBranding::fromArray($raw);
    }
}
```

`MunicipalityBranding::fromArray()` aplica o mesmo padrão (trait `ValidatesArrayShape`, whitelist própria) e valida `name` rigorosamente (tipo string, não-vazio). Docblock explícito:

```php
/**
 * Constrói MunicipalityBranding a partir de array.
 *
 * Pré-condição: `name` é string não-vazia. O caso de bloco ausente ou `name` nulo/vazio
 * é filtrado upstream por `DanfseConfig::buildMunicipality()` (defesa em profundidade para
 * config Laravel parcial). Chamadas diretas a este método devem fornecer `name` válido,
 * caso contrário `InvalidArgumentException` é lançada.
 */
public static function fromArray(array $data): self
```

`LogoLoader::toDataUri()` continua tolerante a `logo_path` ilegível (resolve para `null`) — esse é comportamento de runtime (arquivo some), não de shape.

## Testes

### Unit — Decorators (`tests/Unit/Operations/Decorators/`)

- `EmitterWithDanfseTest`
  - emit sucesso com XML → renderer chamado, `pdf` anexado
  - emit sucesso mas `xml === null` → renderer não chamado, resposta pass-through
  - emit falha (`sucesso === false`) → renderer não chamado, pass-through
  - render falha (`DanfseResponse.sucesso === false`) → `pdf === null`, `pdfErrors` populado, `NfseResponse.sucesso` preservado
  - `emitirDecisaoJudicial` mesmo comportamento em todos os casos acima
- `SubstitutorWithDanfseTest`: mesmos casos para `substituir()`
- `ConsulterWithDanfseTest`:
  - `nfse($chave)` decorado (mesma matriz)
  - `dps`, `danfse`, `eventos`, `verificarDps` delegam ao inner sem chamar renderer

Todos usam fakes das ports (`EmitsNfse`, `SubstitutesNfse`, `ConsultsNfse`, `RendersDanfse`). Zero I/O, zero certificados nos decorators. Testes de `fromArray` podem ler `tests/fixtures/danfse/tiny-logo.png` (já existe) para validar resolução de `logo_path` (I/O controlado).

**Fakes com contador (spy)**: o fake `RendersDanfse` expõe `$toPdfCalls: int` para permitir assertions explícitas:

- `renderer NÃO chamado quando xml é null`: `expect($spy->toPdfCalls)->toBe(0)` — mata mutation `=== null` → `!== null` em `AttachesDanfsePdf::attachPdf()`
- `renderer NÃO chamado quando sucesso é false`: `expect($spy->toPdfCalls)->toBe(0)` — mata mutation `!$r->sucesso` → `$r->sucesso`
- `renderer chamado exatamente uma vez em substituir()`: `expect($spy->toPdfCalls)->toBe(1)` — garante invariante de wiring (emitter não-decorado dentro do substitutor)

### Unit — DTO `fromArray` (`tests/Unit/Danfse/`)

- `DanfseConfigFromArrayTest`
  - **Happy path**:
    - array completo produz `DanfseConfig` equivalente ao construtor direto
    - array vazio (`[]`) produz defaults equivalentes a `new DanfseConfig()`
    - array só com `logo_path` (string não-null) carrega logo custom e deixa `municipality === null`
    - `logo_path === false` suprime logo
    - `logo_data_uri` precedência sobre `logo_path`
    - chave `enabled` ignorada (gate Laravel)
    - ausência de `municipality` → `municipality === null`
    - `municipality: null` explícito → `municipality === null`
  - **Defesa em profundidade (`municipality` parcial vira ausente)**:
    - `municipality: ['name' => null, 'department' => '', ...]` → `municipality === null`
    - `municipality: ['name' => '', ...]` → `municipality === null`
  - **Validação de shape (`InvalidArgumentException`)**:
    - chave desconhecida (ex.: `logo_paht`) → erro com nome da chave
    - `logo_path: 123` (int) → erro de tipo
    - `logo_data_uri: 456` (int) → erro de tipo
    - `municipality: 'string'` → erro "esperado array"
- `MunicipalityBrandingFromArrayTest`
  - **Happy path**:
    - array completo produz instância equivalente
    - array só com `name` → `department` e `email` default para string vazia; `logoDataUri === null`
    - `logo_data_uri` precedência sobre `logo_path`
  - **Validação de shape (`InvalidArgumentException`)**:
    - chave desconhecida → erro
    - `name` ausente → erro "obrigatório"
    - `name: ''` (string vazia) → erro "não vazio"
    - `name: 123` → erro de tipo
    - `department: 123` / `email: 123` / `logo_path: 123` → erros de tipo

### Feature — composição em `NfsenClient` (`tests/Feature/NfsenClientAutoDanfseTest.php`)

Bootstrap Laravel: via **Orchestra Testbench** (já presente em `require-dev`). Os testes chamam `config([...])` diretamente, mesmo padrão de `tests/Feature/ServiceProviderTest.php`. Nenhuma infraestrutura nova é necessária.

Casos:

- `forStandalone(danfse: [...])` + emit em homologação (HTTP faked via `Http::fake` ou fixture) → resposta com `pdf !== null`. Validação do binário em duas camadas:
  1. **Sanity**: `str_starts_with($response->pdf, '%PDF-')` (prefixo ISO 32000).
  2. **Conteúdo**: `smalot/pdfparser` (dev-dep já presente) extrai texto e assertiona que o PDF contém (a) a `chaveAcesso` (string de 50 dígitos fixada pelo fixture, renderizada em `template.php:67`) e (b) o `numeroNfse` (`template.php:81`). Dois campos distintos reduzem o risco de assertion frágil por problemas de fonte/espaçamento do dompdf.
- `forStandalone()` sem `danfse` → emit retorna `pdf === null` e `pdfErrors === []` mesmo com `dompdf`/`bacon-qr-code` instalados (asserção observável via API pública, sem reflection).
- `NfsenClient::for()` com `config(['nfsen.danfse.enabled' => true, 'nfsen.danfse.logo_path' => ...])` e sem argumento explícito → auto-render ativa.
- `NfsenClient::for(danfse: [...])` sobrescreve config global (logo/município do tenant prevalecem). Assert: o texto extraído do PDF via `smalot/pdfparser` contém o `municipality.name` do array explícito, não o do config. (Não comparar bytes do PDF — dompdf insere `/CreationDate` e IDs de objeto variáveis.)
- `NfsenClient::for()` sem argumento com `config('nfsen.danfse.enabled') === false` (ou chave ausente) → sem auto-render.
- `NfsenClient::for(danfse: false)` com `config.enabled === true` → sentinel desliga auto-render nessa instância (emit retorna `pdf === null`).
- `NfsenClient::forStandalone(danfse: false)` → equivale a `null` (sem auto-render); teste documenta a paridade.
- `NfsenClient::for()` com `config.nfsen.danfse.enabled === true` mas **sem** `NFSE_DANFSE_MUN_NAME` setado (config/nfsen.php emite `municipality: null`) → client constrói sem exception; auto-render ativa sem cabeçalho municipal.
- Hipotético `config.nfsen.danfse.municipality = ['name' => null, ...]` (array parcial atingindo fromArray) → client constrói sem exception; `municipality === null` (defesa em profundidade).

### Rename em testes existentes

`tests/Feature/NfsenClientDanfeTest.php` → `NfsenClientDanfseTest.php`:

- Renomear arquivo e descrições (`danfe` → `danfse`)
- Atualizar chamadas ao método renomeado
- Adicionar caso: `$client->danfse([...])` (array) e `$client->danfse(new DanfseConfig(...))` produzem PDFs com **mesmo conteúdo textual** (comparação via `smalot/pdfparser` extraindo texto e assertando igualdade dos strings extraídos). Binário puro **não é comparável** porque dompdf injeta `/CreationDate` e IDs de objeto variáveis por execução.
- Adicionar caso: `$client->danfse()` sem argumento usa defaults

### Cobertura

- 100% line, 100% branch, 100% mutation, 100% type coverage (manter obrigatório do projeto)
- Todo ramo do trait `AttachesDanfsePdf` coberto: sucesso com xml, sucesso sem xml, falha, render falha
- `DanfseConfig::fromArray()` coberto em todas as chaves opcionais + todos os ramos de validação (whitelist, tipos, regras)
- `MunicipalityBranding::fromArray()` coberto em todas as chaves + validação de `name` (ausente, vazio, tipo inválido)
- Trait `ValidatesArrayShape::rejectUnknownKeys` coberto indiretamente pelos testes de `fromArray` (chave desconhecida em cada DTO) + teste direto unitário se mutation não fechar

## Documentação

- **README.md**: nova seção "Geração automática do DANFSE" descrevendo modos Laravel e standalone, shape do array, e fluxo de erros via `pdfErrors`. Seção do `danfse()` direto atualizada para array payload. Nota de breaking rename.
- **CHANGELOG.md**: entrada com duas sub-seções:
  - *Added*: auto-render em `emitir`/`emitirDecisaoJudicial`/`substituir`/`consultar()->nfse()`; `DanfseConfig::fromArray()`, `MunicipalityBranding::fromArray()`, parâmetro `danfse: array|false|null` em `for()`/`forStandalone()` (`false` é sentinel de disable pontual), campos `pdf` e `pdfErrors` em `NfseResponse`.
  - *Changed (BREAKING)*: `NfsenClient::danfe()` renomeado para `NfsenClient::danfse()`; aceita `DanfseConfig|array|null`.
- **`config/nfsen.php`**: bloco `danfse` comentado mostrando todas as envs disponíveis.

## Impacto e riscos

- **Breaking**: rename `danfe` → `danfse`. Mitigado por ainda não estar em release publicada.
- **Dependência latente**: `dompdf` e `bacon-qr-code` continuam em `require` do `composer.json` (necessários para `NfsenClient::danfse()` direto). Arquivos ficam em `vendor/` mas nunca são carregados pelo autoloader quando os decorators e `danfse()` não são usados — zero custo de runtime nesse caso.
- **Performance**: emit com auto-render adiciona o custo da renderização dompdf (valor varia com complexidade do template e hardware; benchmark recomendado antes de release). Clientes que precisam latência mínima desligam auto-render e geram sob demanda.

## Alternativas consideradas e descartadas

- **Injeção opcional de `?RendersDanfse` em `NfseEmitter`**: viola SRP, torna `NfseEmitter` condicional, acopla core a DANFSE. Descartado.
- **Método separado `emitirComDanfse()`**: duplica API pública; contradiz pedido do usuário ("no mesmo local que retorna o XML").
- **Helper `$response->withDanfse($renderer)` manual**: sem auto-geração; contradiz "o sistema já deveria gerar PDF na emissão".
- **Desligar override apenas via `forStandalone()` direto**: descartado — sentinel `danfse: false` foi adotado (ver seção Precedência); `array|false|null` é trivial em PHP 8.1+ e a ergonomia compensa o ramo extra.
