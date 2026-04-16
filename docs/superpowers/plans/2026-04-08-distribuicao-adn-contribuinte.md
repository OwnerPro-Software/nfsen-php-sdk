# ADN Contribuinte Distribution API — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add distribution API support (`$client->distribuicao()->documentos/documento/eventos`) enabling bulk NFS-e retrieval via NSU from the ADN Contribuinte service.

**Architecture:** New `DistributesNfse` driving port implemented by `NfseDistributor`, which uses `SendsHttpRequests` (the HTTP adapter interface) directly — not `ExecutesNfseRequests` — because the distribution API has a different response format (PascalCase keys, string enums) and the spec requires no event dispatching. Parsed into new `DistribuicaoResponse`/`DocumentoFiscal` DTOs with three new enums.

**Tech Stack:** PHP 8.3, Pest 4, Laravel HTTP, PHPStan level 10, Psalm taint analysis

**Spec:** `docs/superpowers/specs/2026-04-08-distribuicao-adn-contribuinte-design.md`

---

### File Map

**New files:**
| File | Responsibility |
|------|---------------|
| `src/Enums/StatusDistribuicao.php` | 3-case string enum for distribution status |
| `src/Enums/TipoDocumentoFiscal.php` | 6-case string enum for document types |
| `src/Enums/TipoEventoDistribuicao.php` | 18-case string enum for distribution event types |
| `src/Responses/DistribuicaoResponse.php` | Response DTO with `fromApiResult()` factory |
| `src/Responses/DocumentoFiscal.php` | Lote item DTO with `fromArray()` factory |
| `src/Contracts/Driving/DistributesNfse.php` | Driving port interface |
| `src/Contracts/Driving/QueriesDistribuicao.php` | Accessor interface for NfsenClient |
| `src/Operations/NfseDistributor.php` | Operation class implementing `DistributesNfse` |
| `tests/Unit/Enums/StatusDistribuicaoTest.php` | Enum tests |
| `tests/Unit/Enums/TipoDocumentoFiscalTest.php` | Enum tests |
| `tests/Unit/Enums/TipoEventoDistribuicaoTest.php` | Enum tests |
| `tests/Unit/DTOs/DistribuicaoResponseTest.php` | Response DTO tests |
| `tests/Unit/DTOs/DocumentoFiscalTest.php` | Document DTO tests |
| `tests/Unit/Operations/NfseDistributorTest.php` | Distributor unit tests |
| `tests/Feature/NfsenClientDistribuicaoTest.php` | Integration tests |

**Modified files:**
| File | Change |
|------|--------|
| `src/Responses/ProcessingMessage.php` | Add `parametros` field |
| `src/Adapters/PrefeituraResolver.php` | Add 2 distribution operations |
| `src/NfsenClient.php` | Implement `QueriesDistribuicao`, add `$distributor` param |
| `src/NfsenServiceProvider.php` | No changes needed — already delegates to `forStandalone()` |
| `src/Facades/Nfsen.php` | Add `@method` annotation for `distribuicao()` |
| `tests/helpers.php` | Update `makeNfsenClient()` to wire distributor |
| `tests/Unit/DTOs/ProcessingMessageTest.php` | Test `parametros` field |
| `tests/Unit/Services/PrefeituraResolverTest.php` | Test new operations |
| `README.md` | Document distribution feature |
| `CHANGELOG.md` | Add changelog entry |

---

### Task 1: Enums

**Files:**
- Create: `src/Enums/StatusDistribuicao.php`
- Create: `src/Enums/TipoDocumentoFiscal.php`
- Create: `src/Enums/TipoEventoDistribuicao.php`
- Create: `tests/Unit/Enums/StatusDistribuicaoTest.php`
- Create: `tests/Unit/Enums/TipoDocumentoFiscalTest.php`
- Create: `tests/Unit/Enums/TipoEventoDistribuicaoTest.php`

- [ ] **Step 1: Write StatusDistribuicao test**

```php
<?php

use OwnerPro\Nfsen\Enums\StatusDistribuicao;

covers(StatusDistribuicao::class);

it('has 3 cases', function () {
    expect(StatusDistribuicao::cases())->toHaveCount(3);
});

it('maps correct string values', function (StatusDistribuicao $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [StatusDistribuicao::Rejeicao, 'REJEICAO'],
    [StatusDistribuicao::NenhumDocumentoLocalizado, 'NENHUM_DOCUMENTO_LOCALIZADO'],
    [StatusDistribuicao::DocumentosLocalizados, 'DOCUMENTOS_LOCALIZADOS'],
]);

it('creates from valid string', function () {
    expect(StatusDistribuicao::from('DOCUMENTOS_LOCALIZADOS'))
        ->toBe(StatusDistribuicao::DocumentosLocalizados);
});

it('throws ValueError for invalid string', function () {
    expect(fn () => StatusDistribuicao::from('INVALID'))
        ->toThrow(ValueError::class);
});

it('tryFrom returns null for invalid string', function () {
    expect(StatusDistribuicao::tryFrom('INVALID'))->toBeNull();
});
```

- [ ] **Step 2: Write TipoDocumentoFiscal test**

```php
<?php

use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;

covers(TipoDocumentoFiscal::class);

it('has 6 cases', function () {
    expect(TipoDocumentoFiscal::cases())->toHaveCount(6);
});

it('maps correct string values', function (TipoDocumentoFiscal $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [TipoDocumentoFiscal::Nenhum, 'NENHUM'],
    [TipoDocumentoFiscal::Dps, 'DPS'],
    [TipoDocumentoFiscal::PedidoRegistroEvento, 'PEDIDO_REGISTRO_EVENTO'],
    [TipoDocumentoFiscal::Nfse, 'NFSE'],
    [TipoDocumentoFiscal::Evento, 'EVENTO'],
    [TipoDocumentoFiscal::Cnc, 'CNC'],
]);

it('creates from valid string', function () {
    expect(TipoDocumentoFiscal::from('NFSE'))
        ->toBe(TipoDocumentoFiscal::Nfse);
});

it('throws ValueError for invalid string', function () {
    expect(fn () => TipoDocumentoFiscal::from('INVALID'))
        ->toThrow(ValueError::class);
});

it('tryFrom returns null for invalid string', function () {
    expect(TipoDocumentoFiscal::tryFrom('INVALID'))->toBeNull();
});
```

- [ ] **Step 3: Write TipoEventoDistribuicao test**

```php
<?php

use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;

covers(TipoEventoDistribuicao::class);

it('has 18 cases', function () {
    expect(TipoEventoDistribuicao::cases())->toHaveCount(18);
});

it('maps correct string values', function (TipoEventoDistribuicao $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [TipoEventoDistribuicao::Cancelamento, 'CANCELAMENTO'],
    [TipoEventoDistribuicao::SolicitacaoCancelamentoAnaliseFiscal, 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL'],
    [TipoEventoDistribuicao::CancelamentoPorSubstituicao, 'CANCELAMENTO_POR_SUBSTITUICAO'],
    [TipoEventoDistribuicao::CancelamentoDeferidoAnaliseFiscal, 'CANCELAMENTO_DEFERIDO_ANALISE_FISCAL'],
    [TipoEventoDistribuicao::CancelamentoIndeferidoAnaliseFiscal, 'CANCELAMENTO_INDEFERIDO_ANALISE_FISCAL'],
    [TipoEventoDistribuicao::ConfirmacaoPrestador, 'CONFIRMACAO_PRESTADOR'],
    [TipoEventoDistribuicao::RejeicaoPrestador, 'REJEICAO_PRESTADOR'],
    [TipoEventoDistribuicao::ConfirmacaoTomador, 'CONFIRMACAO_TOMADOR'],
    [TipoEventoDistribuicao::RejeicaoTomador, 'REJEICAO_TOMADOR'],
    [TipoEventoDistribuicao::ConfirmacaoIntermediario, 'CONFIRMACAO_INTERMEDIARIO'],
    [TipoEventoDistribuicao::RejeicaoIntermediario, 'REJEICAO_INTERMEDIARIO'],
    [TipoEventoDistribuicao::ConfirmacaoTacita, 'CONFIRMACAO_TACITA'],
    [TipoEventoDistribuicao::AnulacaoRejeicao, 'ANULACAO_REJEICAO'],
    [TipoEventoDistribuicao::CancelamentoPorOficio, 'CANCELAMENTO_POR_OFICIO'],
    [TipoEventoDistribuicao::BloqueioPorOficio, 'BLOQUEIO_POR_OFICIO'],
    [TipoEventoDistribuicao::DesbloqueioPorOficio, 'DESBLOQUEIO_POR_OFICIO'],
    [TipoEventoDistribuicao::InclusaoNfseDan, 'INCLUSAO_NFSE_DAN'],
    [TipoEventoDistribuicao::TributosNfseRecolhidos, 'TRIBUTOS_NFSE_RECOLHIDOS'],
]);

it('creates from valid string', function () {
    expect(TipoEventoDistribuicao::from('CANCELAMENTO'))
        ->toBe(TipoEventoDistribuicao::Cancelamento);
});

it('throws ValueError for invalid string', function () {
    expect(fn () => TipoEventoDistribuicao::from('INVALID'))
        ->toThrow(ValueError::class);
});

it('tryFrom returns null for invalid string', function () {
    expect(TipoEventoDistribuicao::tryFrom('INVALID'))->toBeNull();
});
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Enums/StatusDistribuicaoTest.php tests/Unit/Enums/TipoDocumentoFiscalTest.php tests/Unit/Enums/TipoEventoDistribuicaoTest.php --parallel`
Expected: FAIL — enums not found

- [ ] **Step 5: Implement StatusDistribuicao**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

enum StatusDistribuicao: string
{
    case Rejeicao = 'REJEICAO';
    case NenhumDocumentoLocalizado = 'NENHUM_DOCUMENTO_LOCALIZADO';
    case DocumentosLocalizados = 'DOCUMENTOS_LOCALIZADOS';
}
```

- [ ] **Step 6: Implement TipoDocumentoFiscal**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

enum TipoDocumentoFiscal: string
{
    case Nenhum = 'NENHUM';
    case Dps = 'DPS';
    case PedidoRegistroEvento = 'PEDIDO_REGISTRO_EVENTO';
    case Nfse = 'NFSE';
    case Evento = 'EVENTO';
    case Cnc = 'CNC';
}
```

- [ ] **Step 7: Implement TipoEventoDistribuicao**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

enum TipoEventoDistribuicao: string
{
    case Cancelamento = 'CANCELAMENTO';
    case SolicitacaoCancelamentoAnaliseFiscal = 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL';
    case CancelamentoPorSubstituicao = 'CANCELAMENTO_POR_SUBSTITUICAO';
    case CancelamentoDeferidoAnaliseFiscal = 'CANCELAMENTO_DEFERIDO_ANALISE_FISCAL';
    case CancelamentoIndeferidoAnaliseFiscal = 'CANCELAMENTO_INDEFERIDO_ANALISE_FISCAL';
    case ConfirmacaoPrestador = 'CONFIRMACAO_PRESTADOR';
    case RejeicaoPrestador = 'REJEICAO_PRESTADOR';
    case ConfirmacaoTomador = 'CONFIRMACAO_TOMADOR';
    case RejeicaoTomador = 'REJEICAO_TOMADOR';
    case ConfirmacaoIntermediario = 'CONFIRMACAO_INTERMEDIARIO';
    case RejeicaoIntermediario = 'REJEICAO_INTERMEDIARIO';
    case ConfirmacaoTacita = 'CONFIRMACAO_TACITA';
    case AnulacaoRejeicao = 'ANULACAO_REJEICAO';
    case CancelamentoPorOficio = 'CANCELAMENTO_POR_OFICIO';
    case BloqueioPorOficio = 'BLOQUEIO_POR_OFICIO';
    case DesbloqueioPorOficio = 'DESBLOQUEIO_POR_OFICIO';
    case InclusaoNfseDan = 'INCLUSAO_NFSE_DAN';
    case TributosNfseRecolhidos = 'TRIBUTOS_NFSE_RECOLHIDOS';
}
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Enums/StatusDistribuicaoTest.php tests/Unit/Enums/TipoDocumentoFiscalTest.php tests/Unit/Enums/TipoEventoDistribuicaoTest.php --parallel`
Expected: PASS — all green

---

### Task 2: ProcessingMessage — add `parametros`

**Files:**
- Modify: `src/Responses/ProcessingMessage.php`
- Modify: `tests/Unit/DTOs/ProcessingMessageTest.php`

- [ ] **Step 1: Add tests for `parametros` field**

Append to `tests/Unit/DTOs/ProcessingMessageTest.php`:

```php
it('constructs with parametros', function () {
    $msg = new ProcessingMessage(
        mensagem: 'Msg',
        codigo: 'E001',
        parametros: ['param1', 'param2'],
    );

    expect($msg->parametros)->toBe(['param1', 'param2']);
});

it('defaults parametros to empty array', function () {
    $msg = new ProcessingMessage;

    expect($msg->parametros)->toBe([]);
});

it('creates from array with Parametros key', function () {
    $msg = ProcessingMessage::fromArray([
        'Codigo' => 'E001',
        'Parametros' => ['param1', 'param2'],
    ]);

    expect($msg->parametros)->toBe(['param1', 'param2']);
    expect($msg->codigo)->toBe('E001');
});

it('creates from array with lowercase parametros key', function () {
    $msg = ProcessingMessage::fromArray([
        'parametros' => ['p1'],
    ]);

    expect($msg->parametros)->toBe(['p1']);
});

it('defaults parametros to empty array when key absent in fromArray', function () {
    $msg = ProcessingMessage::fromArray(['codigo' => 'E001']);

    expect($msg->parametros)->toBe([]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/DTOs/ProcessingMessageTest.php`
Expected: FAIL — `parametros` property not found

- [ ] **Step 3: Update ProcessingMessage class**

In `src/Responses/ProcessingMessage.php`, add `parametros` to the constructor and update `MessageData` type, `fromArray()`:

The constructor becomes:
```php
public function __construct(
    public ?string $mensagem = null,
    public ?string $codigo = null,
    public ?string $descricao = null,
    public ?string $complemento = null,
    /** @var list<string> */
    public array $parametros = [],
) {}
```

The `MessageData` type alias adds `parametros` and `Parametros`:
```php
/**
 * @phpstan-type MessageData array{
 *     mensagem?: string, Mensagem?: string,
 *     codigo?: string, Codigo?: string,
 *     descricao?: string, Descricao?: string,
 *     complemento?: string, Complemento?: string,
 *     parametros?: list<string>, Parametros?: list<string>,
 * }
 */
```

The `fromArray()` adds:
```php
parametros: $data['parametros'] ?? $data['Parametros'] ?? [],
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/DTOs/ProcessingMessageTest.php`
Expected: PASS — all green

---

### Task 3: Response DTOs

**Files:**
- Create: `src/Responses/DocumentoFiscal.php`
- Create: `src/Responses/DistribuicaoResponse.php`
- Create: `tests/Unit/DTOs/DocumentoFiscalTest.php`
- Create: `tests/Unit/DTOs/DistribuicaoResponseTest.php`

- [ ] **Step 1: Write DocumentoFiscal test**

```php
<?php

use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;
use OwnerPro\Nfsen\Responses\DocumentoFiscal;

covers(DocumentoFiscal::class);

it('constructs with all fields', function () {
    $doc = new DocumentoFiscal(
        nsu: 42,
        chaveAcesso: makeChaveAcesso(),
        tipoDocumento: TipoDocumentoFiscal::Nfse,
        tipoEvento: TipoEventoDistribuicao::Cancelamento,
        arquivoXml: '<NFSe/>',
        dataHoraGeracao: '2026-04-08T14:30:00',
    );

    expect($doc)
        ->nsu->toBe(42)
        ->chaveAcesso->toBe(makeChaveAcesso())
        ->tipoDocumento->toBe(TipoDocumentoFiscal::Nfse)
        ->tipoEvento->toBe(TipoEventoDistribuicao::Cancelamento)
        ->arquivoXml->toBe('<NFSe/>')
        ->dataHoraGeracao->toBe('2026-04-08T14:30:00');
});

it('constructs with nullable fields as null', function () {
    $doc = new DocumentoFiscal(
        nsu: null,
        chaveAcesso: null,
        tipoDocumento: TipoDocumentoFiscal::Nenhum,
        tipoEvento: null,
        arquivoXml: null,
        dataHoraGeracao: null,
    );

    expect($doc)
        ->nsu->toBeNull()
        ->chaveAcesso->toBeNull()
        ->tipoEvento->toBeNull()
        ->arquivoXml->toBeNull()
        ->dataHoraGeracao->toBeNull();
});

it('creates from API array with PascalCase keys', function () {
    $xml = '<NFSe/>';
    $gzipB64 = base64_encode((string) gzencode($xml));

    $doc = DocumentoFiscal::fromArray([
        'NSU' => 1,
        'ChaveAcesso' => makeChaveAcesso(),
        'TipoDocumento' => 'NFSE',
        'TipoEvento' => 'CANCELAMENTO',
        'ArquivoXml' => $gzipB64,
        'DataHoraGeracao' => '2026-04-08T14:30:00',
    ]);

    expect($doc)
        ->nsu->toBe(1)
        ->chaveAcesso->toBe(makeChaveAcesso())
        ->tipoDocumento->toBe(TipoDocumentoFiscal::Nfse)
        ->tipoEvento->toBe(TipoEventoDistribuicao::Cancelamento)
        ->arquivoXml->toBe($xml)
        ->dataHoraGeracao->toBe('2026-04-08T14:30:00');
});

it('creates from API array without optional fields', function () {
    $doc = DocumentoFiscal::fromArray([
        'TipoDocumento' => 'DPS',
    ]);

    expect($doc)
        ->nsu->toBeNull()
        ->chaveAcesso->toBeNull()
        ->tipoDocumento->toBe(TipoDocumentoFiscal::Dps)
        ->tipoEvento->toBeNull()
        ->arquivoXml->toBeNull()
        ->dataHoraGeracao->toBeNull();
});

it('decompresses ArquivoXml from gzip base64', function () {
    $xml = '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse"><infDPS/></DPS>';
    $gzipB64 = base64_encode((string) gzencode($xml));

    $doc = DocumentoFiscal::fromArray([
        'TipoDocumento' => 'DPS',
        'ArquivoXml' => $gzipB64,
    ]);

    expect($doc->arquivoXml)->toBe($xml);
});

it('handles null ArquivoXml', function () {
    $doc = DocumentoFiscal::fromArray([
        'TipoDocumento' => 'NFSE',
        'ArquivoXml' => null,
    ]);

    expect($doc->arquivoXml)->toBeNull();
});
```

- [ ] **Step 2: Write DistribuicaoResponse test**

```php
<?php

use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Responses\DistribuicaoResponse;
use OwnerPro\Nfsen\Responses\DocumentoFiscal;
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
    expect($response->erros[0]->codigo)->toBe('INVALID_RESPONSE');
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/DTOs/DocumentoFiscalTest.php tests/Unit/DTOs/DistribuicaoResponseTest.php --parallel`
Expected: FAIL — classes not found

- [ ] **Step 4: Implement DocumentoFiscal**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Enums\TipoEventoDistribuicao;
use OwnerPro\Nfsen\Support\GzipCompressor;

final readonly class DocumentoFiscal
{
    public function __construct(
        public ?int $nsu,
        public ?string $chaveAcesso,
        public TipoDocumentoFiscal $tipoDocumento,
        public ?TipoEventoDistribuicao $tipoEvento,
        public ?string $arquivoXml,
        public ?string $dataHoraGeracao,
    ) {}

    /**
     * @param array{
     *     NSU?: int|null,
     *     ChaveAcesso?: string|null,
     *     TipoDocumento: string,
     *     TipoEvento?: string|null,
     *     ArquivoXml?: string|null,
     *     DataHoraGeracao?: string|null,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nsu: $data['NSU'] ?? null,
            chaveAcesso: $data['ChaveAcesso'] ?? null,
            tipoDocumento: TipoDocumentoFiscal::from($data['TipoDocumento']),
            tipoEvento: isset($data['TipoEvento']) ? TipoEventoDistribuicao::from($data['TipoEvento']) : null,
            arquivoXml: GzipCompressor::decompressB64($data['ArquivoXml'] ?? null),
            dataHoraGeracao: $data['DataHoraGeracao'] ?? null,
        );
    }
}
```

- [ ] **Step 5: Implement DistribuicaoResponse**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

use OwnerPro\Nfsen\Enums\StatusDistribuicao;

final readonly class DistribuicaoResponse
{
    /**
     * @param list<DocumentoFiscal> $lote
     * @param list<ProcessingMessage> $alertas
     * @param list<ProcessingMessage> $erros
     */
    public function __construct(
        public bool $sucesso,
        public StatusDistribuicao $statusProcessamento,
        public array $lote,
        public array $alertas,
        public array $erros,
        public ?int $tipoAmbiente,
        public ?string $versaoAplicativo,
        public ?string $dataHoraProcessamento,
    ) {}

    /** @param array<string, mixed> $result */
    public static function fromApiResult(array $result): self
    {
        $status = StatusDistribuicao::tryFrom($result['StatusProcessamento'] ?? '');

        if ($status === null) {
            return new self(
                sucesso: false,
                statusProcessamento: StatusDistribuicao::Rejeicao,
                lote: [],
                alertas: [],
                erros: [new ProcessingMessage(
                    mensagem: 'Resposta inválida da API',
                    codigo: 'INVALID_RESPONSE',
                    descricao: 'Campo StatusProcessamento ausente ou inválido.',
                )],
                tipoAmbiente: null,
                versaoAplicativo: null,
                dataHoraProcessamento: null,
            );
        }

        /** @var list<array{NSU?: int|null, ChaveAcesso?: string|null, TipoDocumento: string, TipoEvento?: string|null, ArquivoXml?: string|null, DataHoraGeracao?: string|null}> $loteDFe */
        $loteDFe = $result['LoteDFe'] ?? [];

        /** @var list<array{mensagem?: string, Mensagem?: string, codigo?: string, Codigo?: string, descricao?: string, Descricao?: string, complemento?: string, Complemento?: string, parametros?: list<string>, Parametros?: list<string>}> $alertas */
        $alertas = $result['Alertas'] ?? [];

        /** @var list<array{mensagem?: string, Mensagem?: string, codigo?: string, Codigo?: string, descricao?: string, Descricao?: string, complemento?: string, Complemento?: string, parametros?: list<string>, Parametros?: list<string>}> $erros */
        $erros = $result['Erros'] ?? [];

        return new self(
            sucesso: $status === StatusDistribuicao::DocumentosLocalizados,
            statusProcessamento: $status,
            lote: array_map(DocumentoFiscal::fromArray(...), $loteDFe),
            alertas: ProcessingMessage::fromArrayList($alertas),
            erros: ProcessingMessage::fromArrayList($erros),
            tipoAmbiente: self::mapTipoAmbiente($result['TipoAmbiente'] ?? null),
            versaoAplicativo: $result['VersaoAplicativo'] ?? null,
            dataHoraProcessamento: $result['DataHoraProcessamento'] ?? null,
        );
    }

    private static function mapTipoAmbiente(mixed $value): ?int
    {
        return match ($value) {
            'PRODUCAO' => 1,
            'HOMOLOGACAO' => 2,
            default => null,
        };
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/DTOs/DocumentoFiscalTest.php tests/Unit/DTOs/DistribuicaoResponseTest.php --parallel`
Expected: PASS

---

### Task 4: Interfaces

**Files:**
- Create: `src/Contracts/Driving/DistributesNfse.php`
- Create: `src/Contracts/Driving/QueriesDistribuicao.php`

- [ ] **Step 1: Create DistributesNfse interface**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Responses\DistribuicaoResponse;

interface DistributesNfse
{
    public function documentos(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse;

    public function documento(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse;

    public function eventos(string $chave): DistribuicaoResponse;
}
```

- [ ] **Step 2: Create QueriesDistribuicao interface**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

interface QueriesDistribuicao
{
    public function distribuicao(): DistributesNfse;
}
```

- [ ] **Step 3: Verify compilation**

Run: `./vendor/bin/phpstan analyse src/Contracts/Driving/DistributesNfse.php src/Contracts/Driving/QueriesDistribuicao.php`
Expected: PASS — no errors

---

### Task 5: PrefeituraResolver — add operations

**Files:**
- Modify: `src/Adapters/PrefeituraResolver.php`
- Modify: `tests/Unit/Services/PrefeituraResolverTest.php`

- [ ] **Step 1: Add tests for new operations**

Append to `tests/Unit/Services/PrefeituraResolverTest.php`:

```php
it('resolves distribute_documents operation', function () {
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');

    $path = $resolver->resolveOperation('9999999', 'distribute_documents', ['NSU' => 42]);

    expect($path)->toBe('contribuintes/DFe/42');
});

it('resolves distribute_events operation', function () {
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $chave = makeChaveAcesso();

    $path = $resolver->resolveOperation('9999999', 'distribute_events', ['ChaveAcesso' => $chave]);

    expect($path)->toBe('contribuintes/NFSe/'.$chave.'/Eventos');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Services/PrefeituraResolverTest.php --filter="distribute"`
Expected: FAIL — "Operação desconhecida: 'distribute_documents'"

- [ ] **Step 3: Add operations to PrefeituraResolver**

In `src/Adapters/PrefeituraResolver.php`, add to `DEFAULT_OPERATIONS`:

```php
'distribute_documents' => 'contribuintes/DFe/{NSU}',
'distribute_events' => 'contribuintes/NFSe/{ChaveAcesso}/Eventos',
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Services/PrefeituraResolverTest.php --parallel`
Expected: PASS

---

### Task 6: NfseDistributor

**Files:**
- Create: `src/Operations/NfseDistributor.php`
- Create: `tests/Unit/Operations/NfseDistributorTest.php`

- [ ] **Step 1: Write NfseDistributor test**

```php
<?php

use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Operations\NfseDistributor;

covers(NfseDistributor::class);

function makeFakeDistribuicaoResponse(string $status = 'DOCUMENTOS_LOCALIZADOS', ?array $lote = null): array
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

function makeFakeHttpClient(array $response): SendsHttpRequests
{
    return new class($response) implements SendsHttpRequests
    {
        /** @var list<string> */
        public array $urls = [];

        public function __construct(private readonly array $response) {}

        public function post(string $url, array $payload): array
        {
            return [];
        }

        public function get(string $url): array
        {
            $this->urls[] = $url;

            return $this->response;
        }

        public function getBytes(string $url): string
        {
            return '';
        }

        public function head(string $url): int
        {
            return 200;
        }
    };
}

function makeNfseDistributor(SendsHttpRequests $httpClient): NfseDistributor
{
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');

    return new NfseDistributor($httpClient, $resolver, '9999999', 'https://adn.base', '12345678000195');
}

it('documentos sends GET with lote=true and default cnpjConsulta', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeTrue();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::DocumentosLocalizados);
    expect($response->lote)->toHaveCount(1);
    expect($response->lote[0]->tipoDocumento)->toBe(TipoDocumentoFiscal::Nfse);
    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/0?cnpjConsulta=12345678000195&lote=true');
});

it('documentos uses provided cnpjConsulta over default', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    $distributor->documentos(0, '99999999000100');

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/0?cnpjConsulta=99999999000100&lote=true');
});

it('documento sends GET with lote=false', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    $distributor->documento(42);

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/42?cnpjConsulta=12345678000195&lote=false');
});

it('documento uses provided cnpjConsulta', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    $distributor->documento(42, '99999999000100');

    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/DFe/42?cnpjConsulta=99999999000100&lote=false');
});

it('eventos sends GET with chave in URL', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);
    $chave = makeChaveAcesso();

    $response = $distributor->eventos($chave);

    expect($response->sucesso)->toBeTrue();
    expect($httpClient->urls[0])->toBe('https://adn.base/contribuintes/NFSe/'.$chave.'/Eventos');
});

it('eventos throws InvalidArgumentException for invalid chave', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $distributor = makeNfseDistributor($httpClient);

    expect(fn () => $distributor->eventos('INVALID'))
        ->toThrow(InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('handles NENHUM_DOCUMENTO_LOCALIZADO status', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse('NENHUM_DOCUMENTO_LOCALIZADO', []));
    $distributor = makeNfseDistributor($httpClient);

    $response = $distributor->documentos(999);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::NenhumDocumentoLocalizado);
    expect($response->lote)->toBeEmpty();
});

it('handles REJEICAO status with errors', function () {
    $httpClient = makeFakeHttpClient([
        'StatusProcessamento' => 'REJEICAO',
        'LoteDFe' => null,
        'Alertas' => null,
        'Erros' => [['Codigo' => 'E001', 'Descricao' => 'CNPJ inválido']],
        'TipoAmbiente' => 'HOMOLOGACAO',
        'VersaoAplicativo' => '1.0',
        'DataHoraProcessamento' => '2026-04-08T15:00:00',
    ]);
    $distributor = makeNfseDistributor($httpClient);

    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('CNPJ inválido');
});

it('handles HttpException with structured body', function () {
    $httpClient = new class implements SendsHttpRequests
    {
        public function post(string $url, array $payload): array { return []; }

        public function get(string $url): array
        {
            throw HttpException::fromResponse(500, json_encode([
                'StatusProcessamento' => 'REJEICAO',
                'Erros' => [['Codigo' => 'E500', 'Descricao' => 'Erro interno']],
                'TipoAmbiente' => 'HOMOLOGACAO',
                'DataHoraProcessamento' => '2026-04-08T15:00:00',
            ]));
        }

        public function getBytes(string $url): string { return ''; }

        public function head(string $url): int { return 200; }
    };

    $distributor = makeNfseDistributor($httpClient);
    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->descricao)->toBe('Erro interno');
});

it('handles HttpException with non-JSON body', function () {
    $httpClient = new class implements SendsHttpRequests
    {
        public function post(string $url, array $payload): array { return []; }

        public function get(string $url): array
        {
            throw HttpException::fromResponse(500, 'Server Error');
        }

        public function getBytes(string $url): string { return ''; }

        public function head(string $url): int { return 200; }
    };

    $distributor = makeNfseDistributor($httpClient);
    $response = $distributor->documentos(0);

    expect($response->sucesso)->toBeFalse();
    expect($response->statusProcessamento)->toBe(StatusDistribuicao::Rejeicao);
    expect($response->erros[0]->mensagem)->toBe('HTTP error: 500');
    expect($response->erros[0]->codigo)->toBe('500');
    expect($response->erros[0]->descricao)->toBe('Server Error');
});

it('buildUrl trims trailing slash from baseUrl', function () {
    $httpClient = makeFakeHttpClient(makeFakeDistribuicaoResponse());
    $resolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $distributor = new NfseDistributor($httpClient, $resolver, '9999999', 'https://adn.base/', '12345678000195');

    $distributor->documentos(0);

    expect($httpClient->urls[0])->toStartWith('https://adn.base/contribuintes/');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseDistributorTest.php`
Expected: FAIL — NfseDistributor not found

- [ ] **Step 3: Implement NfseDistributor**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driven\ResolvesOperations;
use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Pipeline\Concerns\ValidatesChaveAcesso;
use OwnerPro\Nfsen\Responses\DistribuicaoResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;

final readonly class NfseDistributor implements DistributesNfse
{
    use ValidatesChaveAcesso;

    public function __construct(
        private SendsHttpRequests $httpClient,
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
        try {
            /** @var array<string, mixed> $result */
            $result = $this->httpClient->get($url);

            return DistribuicaoResponse::fromApiResult($result);
        } catch (HttpException $e) {
            return $this->handleHttpError($e);
        }
    }

    private function handleHttpError(HttpException $e): DistribuicaoResponse
    {
        $body = $e->getResponseBody();

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);

        if (is_array($decoded) && isset($decoded['StatusProcessamento'])) {
            return DistribuicaoResponse::fromApiResult($decoded);
        }

        return new DistribuicaoResponse(
            sucesso: false,
            statusProcessamento: StatusDistribuicao::Rejeicao,
            lote: [],
            alertas: [],
            erros: [new ProcessingMessage(
                mensagem: 'HTTP error: '.$e->getCode(),
                codigo: (string) $e->getCode(),
                descricao: $e->getResponseBody(),
            )],
            tipoAmbiente: null,
            versaoAplicativo: null,
            dataHoraProcessamento: null,
        );
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

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseDistributorTest.php`
Expected: PASS

---

### Task 7: Wire NfsenClient, ServiceProvider, Facade

**Files:**
- Modify: `src/NfsenClient.php`
- Modify: `src/NfsenServiceProvider.php`
- Modify: `src/Facades/Nfsen.php`
- Modify: `tests/helpers.php`

- [ ] **Step 1: Update NfsenClient**

In `src/NfsenClient.php`:

Add imports:
```php
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Contracts\Driving\QueriesDistribuicao;
use OwnerPro\Nfsen\Operations\NfseDistributor;
```

Update class declaration:
```php
final readonly class NfsenClient implements CancelsNfse, EmitsNfse, QueriesDistribuicao, QueriesNfse, SubstitutesNfse
```

Update constructor:
```php
public function __construct(
    private EmitsNfse $emitter,
    private CancelsNfse $canceller,
    private SubstitutesNfse $substitutor,
    private ConsultsNfse $consulter,
    private DistributesNfse $distributor,
) {}
```

Add method:
```php
public function distribuicao(): DistributesNfse
{
    return $this->distributor;
}
```

Update `forStandalone()` — after `$adnUrl = ...` line, add:
```php
$identity = $certManager->extract();
$cnpjAutor = $identity['cnpj'] ?? '';
```

Update the `return new self(...)`:
```php
return new self(
    emitter: $emitter,
    canceller: new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente),
    substitutor: new NfseSubstitutor($emitter),
    consulter: new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura),
    distributor: new NfseDistributor($httpClient, $prefeituraResolver, $prefeitura, $adnUrl, $cnpjAutor),
);
```

- [ ] **Step 2: Update Facade**

In `src/Facades/Nfsen.php`, add to the docblock:
```php
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
```

Add annotation:
```php
 * @method static DistributesNfse distribuicao()
```

- [ ] **Step 3: Update tests/helpers.php `makeNfsenClient()`**

In `tests/helpers.php`, add import:
```php
use OwnerPro\Nfsen\Operations\NfseDistributor;
```

Update the `return new NfsenClient(...)` call to include distributor:
```php
return new NfsenClient(
    emitter: $emitter,
    canceller: new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente),
    substitutor: new NfseSubstitutor($emitter),
    consulter: new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura),
    distributor: new NfseDistributor($httpClient, $prefeituraResolver, $prefeitura, $adnUrl, $certManager->extract()['cnpj'] ?? ''),
);
```

- [ ] **Step 4: Run full test suite to verify no regressions**

Run: `./vendor/bin/pest --parallel`
Expected: PASS — all existing tests still green

---

### Task 8: Feature tests

**Files:**
- Create: `tests/Feature/NfsenClientDistribuicaoTest.php`

- [ ] **Step 1: Write feature tests**

```php
<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Contracts\Driving\DistributesNfse;
use OwnerPro\Nfsen\Enums\StatusDistribuicao;
use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;
use OwnerPro\Nfsen\Exceptions\HttpException;
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
```

- [ ] **Step 2: Run feature tests**

Run: `./vendor/bin/pest tests/Feature/NfsenClientDistribuicaoTest.php`
Expected: PASS

- [ ] **Step 3: Run full suite**

Run: `./vendor/bin/pest --parallel`
Expected: PASS — all tests green

---

### Task 9: Documentation

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Update README.md**

In `README.md`, after the "Funcionalidades" list item `- Consulta por chave de acesso...`, add:
```
- Distribuição de documentos fiscais via ADN — consulta em lote por NSU (`distribuicao`)
```

In the "Requisitos" section, update the PHP version and Laravel support if needed.

After the "### Consultas" section, add a new section:

```markdown
### Distribuição (ADN Contribuinte)

Consulta em lote de documentos fiscais via NSU (Número Sequencial Único) através do ADN (Ambiente de Dados Nacional). Útil para importação em massa de NFS-e.

```php
// Buscar lote de documentos a partir do NSU 0
$response = $client->distribuicao()->documentos(0);

if ($response->sucesso) {
    foreach ($response->lote as $doc) {
        echo "NSU: {$doc->nsu} | Tipo: {$doc->tipoDocumento->value} | Chave: {$doc->chaveAcesso}\n";
        // $doc->arquivoXml contém o XML já descomprimido
    }
}

// Buscar documento unitário pelo NSU
$response = $client->distribuicao()->documento(42);

// Buscar todos os eventos de uma NFS-e
$response = $client->distribuicao()->eventos($chave);

// Usar CNPJ diferente do certificado (procurador/filiais)
$response = $client->distribuicao()->documentos(0, '99999999000100');
```

O fluxo típico de importação:

1. Comece com NSU `0`
2. Chame `documentos($nsu)` — receba um lote
3. Guarde o maior NSU do lote
4. Repita com o próximo NSU até `statusProcessamento` ser `NenhumDocumentoLocalizado`
```

After the "### `EventsResponse`" table, add:

```markdown
### `DistribuicaoResponse`

Retornado por `distribuicao()->documentos()`, `distribuicao()->documento()` e `distribuicao()->eventos()`.

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `sucesso` | `bool` | `true` quando `statusProcessamento` é `DocumentosLocalizados` |
| `statusProcessamento` | `StatusDistribuicao` | Status: `Rejeicao`, `NenhumDocumentoLocalizado`, `DocumentosLocalizados` |
| `lote` | `list<DocumentoFiscal>` | Documentos fiscais retornados |
| `alertas` | `list<ProcessingMessage>` | Alertas não-bloqueantes |
| `erros` | `list<ProcessingMessage>` | Erros de processamento |
| `tipoAmbiente` | `?int` | 1 = Produção, 2 = Homologação |
| `versaoAplicativo` | `?string` | Versão do aplicativo |
| `dataHoraProcessamento` | `?string` | Data/hora do processamento |

### `DocumentoFiscal`

Cada item do lote na `DistribuicaoResponse`.

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `nsu` | `?int` | Número Sequencial Único |
| `chaveAcesso` | `?string` | Chave de acesso da NFS-e |
| `tipoDocumento` | `TipoDocumentoFiscal` | Tipo: `Nfse`, `Dps`, `Evento`, `Cnc`, `PedidoRegistroEvento`, `Nenhum` |
| `tipoEvento` | `?TipoEventoDistribuicao` | Tipo do evento (quando `tipoDocumento` é `Evento`) |
| `arquivoXml` | `?string` | XML do documento (já descomprimido) |
| `dataHoraGeracao` | `?string` | Data/hora de geração |
```

- [ ] **Step 2: Update CHANGELOG.md**

Add at the top, after the `# Changelog` header:

```markdown
## [Unreleased]

### Added
- Distribuição de documentos fiscais via ADN Contribuinte (`$client->distribuicao()`)
  - `documentos(int $nsu)` — consulta em lote por NSU
  - `documento(int $nsu)` — consulta unitária por NSU
  - `eventos(string $chave)` — consulta todos os eventos de uma NFS-e
- Novos DTOs: `DistribuicaoResponse`, `DocumentoFiscal`
- Novos enums: `StatusDistribuicao`, `TipoDocumentoFiscal`, `TipoEventoDistribuicao`
- Campo `parametros` adicionado ao `ProcessingMessage`
```

---

### Task 10: Quality checks

- [ ] **Step 1: Run full test suite with coverage**

Run: `./vendor/bin/pest --coverage --min=100 --parallel`
Expected: PASS — 100% coverage

- [ ] **Step 2: Run mutation tests**

Run: `./vendor/bin/pest --mutate --min=100 --parallel`
Expected: PASS — 100% mutation score

- [ ] **Step 3: Run type coverage**

Run: `./vendor/bin/pest --type-coverage --min=100`
Expected: PASS — 100% type coverage

- [ ] **Step 4: Run Rector**

Run: `./vendor/bin/rector --dry-run`
Expected: PASS — no changes suggested

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse`
Expected: PASS — no errors

- [ ] **Step 6: Run Psalm taint analysis**

Run: `./vendor/bin/psalm --taint-analysis`
Expected: PASS — no issues

- [ ] **Step 7: Run Pint**

Run: `./vendor/bin/pint -p`
Expected: PASS or auto-fix formatting

- [ ] **Step 8: If Pint changed files, re-run test suite**

Run: `./vendor/bin/pest --coverage --min=100 --parallel`
Expected: PASS
