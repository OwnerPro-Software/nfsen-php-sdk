# Substituição Orquestrada — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactor `substituir` to orchestrate emission + event registration internally, returning a composite `SubstituicaoResponse`.

**Architecture:** `NfseSubstitutor` receives the DPS + chave original + motivo, injects `subst` into the DPS, calls `NfseEmitter::emitir`, then registers the e105102 event. Returns `SubstituicaoResponse` with both responses.

**Tech Stack:** PHP 8.2+, Pest, PHPStan, Psalm, Rector, Pint

---

### Task 1: Create `SubstituicaoResponse` DTO

**Files:**
- Create: `src/Responses/SubstituicaoResponse.php`
- Test: `tests/Unit/Responses/SubstituicaoResponseTest.php`

**Step 1: Write the failing test**

```php
<?php

covers(\Pulsar\NfseNacional\Responses\SubstituicaoResponse::class);

use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Responses\SubstituicaoResponse;

it('creates successful response when both emissao and evento succeed', function () {
    $emissao = new NfseResponse(sucesso: true, chave: 'CHAVE_SUB');
    $evento = new NfseResponse(sucesso: true, chave: 'CHAVE_ORIG');

    $response = new SubstituicaoResponse(sucesso: true, emissao: $emissao, evento: $evento);

    expect($response->sucesso)->toBeTrue();
    expect($response->emissao)->toBe($emissao);
    expect($response->evento)->toBe($evento);
});

it('creates failed response when emissao fails', function () {
    $emissao = new NfseResponse(sucesso: false);

    $response = new SubstituicaoResponse(sucesso: false, emissao: $emissao, evento: null);

    expect($response->sucesso)->toBeFalse();
    expect($response->emissao)->toBe($emissao);
    expect($response->evento)->toBeNull();
});

it('creates failed response when emissao succeeds but evento fails', function () {
    $emissao = new NfseResponse(sucesso: true, chave: 'CHAVE_SUB');
    $evento = new NfseResponse(sucesso: false);

    $response = new SubstituicaoResponse(sucesso: false, emissao: $emissao, evento: $evento);

    expect($response->sucesso)->toBeFalse();
    expect($response->emissao->sucesso)->toBeTrue();
    expect($response->evento->sucesso)->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Responses/SubstituicaoResponseTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Responses;

final readonly class SubstituicaoResponse
{
    public function __construct(
        public bool $sucesso,
        public NfseResponse $emissao,
        public ?NfseResponse $evento,
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Responses/SubstituicaoResponseTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Responses/SubstituicaoResponse.php tests/Unit/Responses/SubstituicaoResponseTest.php
git commit -m "feat: add SubstituicaoResponse DTO"
```

---

### Task 2: Update `SubstitutesNfse` contract

**Files:**
- Modify: `src/Contracts/Driving/SubstitutesNfse.php`

**Step 1: Update the interface**

Change the `substituir` signature to:

```php
<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Responses\SubstituicaoResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
interface SubstitutesNfse
{
    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): SubstituicaoResponse;
}
```

**Step 2: Run PHPStan to confirm the contract change is detected**

Run: `./vendor/bin/phpstan analyse src/Contracts/Driving/SubstitutesNfse.php`
Expected: PASS (interface itself is valid, implementations will fail until updated)

**Step 3: Commit**

```bash
git add src/Contracts/Driving/SubstitutesNfse.php
git commit -m "refactor!: update SubstitutesNfse contract for orchestrated flow"
```

---

### Task 3: Rewrite `NfseSubstitutor` to orchestrate emissão + evento

**Files:**
- Modify: `src/Operations/NfseSubstitutor.php`
- Modify: `tests/Unit/Operations/NfseSubstitutorTest.php`

**Step 1: Write the failing unit test**

Rewrite `tests/Unit/Operations/NfseSubstitutorTest.php`:

```php
<?php

covers(\Pulsar\NfseNacional\Operations\NfseSubstitutor::class);

use Pulsar\NfseNacional\Contracts\Driving\EmitsNfse;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\Subst;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Operations\NfseSubstitutor;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder;

function makeNfseSubstitutor(
    ?EmitsNfse $emitter = null,
    ?NfseRequestPipeline $pipeline = null,
    ?SubstitutionBuilder $substitutionBuilder = null,
): NfseSubstitutor {
    return new NfseSubstitutor(
        emitter: $emitter ?? Mockery::mock(EmitsNfse::class),
        pipeline: $pipeline ?? makeDefaultPipeline(),
        substitutionBuilder: $substitutionBuilder ?? new SubstitutionBuilder(makeXsdValidator()),
        ambiente: NfseAmbiente::HOMOLOGACAO,
    );
}

it('injects subst into DPS and emits then registers event on success', function () {
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSubstituta = '98765432109876543210987654321098765432109876543210';

    $emissaoResponse = new NfseResponse(sucesso: true, chave: $chaveSubstituta);

    $emitter = Mockery::mock(EmitsNfse::class);
    $emitter->shouldReceive('emitir')
        ->once()
        ->withArgs(function (DpsData $dps) use ($chave): bool {
            return $dps->subst instanceof Subst
                && $dps->subst->chSubstda === $chave
                && $dps->subst->cMotivo === CodigoJustificativaSubstituicao::Outros;
        })
        ->andReturn($emissaoResponse);

    // ... rest depends on how the event pipeline is set up
    // This test verifies subst injection and emitter call
});
```

Note: The exact test shape depends on how we mock the event registration pipeline. The full rewrite of this test file will happen alongside the implementation to keep the tests aligned with the new constructor signature.

**Step 2: Rewrite `NfseSubstitutor`**

```php
<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Operations;

use Pulsar\NfseNacional\Contracts\Driving\EmitsNfse;
use Pulsar\NfseNacional\Contracts\Driving\SubstitutesNfse;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\Subst;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Events\NfseSubstituted;
use Pulsar\NfseNacional\Pipeline\Concerns\DispatchesEvents;
use Pulsar\NfseNacional\Pipeline\Concerns\ParsesEventResponse;
use Pulsar\NfseNacional\Pipeline\Concerns\ValidatesChaveAcesso;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Responses\ProcessingMessage;
use Pulsar\NfseNacional\Responses\SubstituicaoResponse;
use Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 * @phpstan-import-type MessageData from ProcessingMessage
 */
final readonly class NfseSubstitutor implements SubstitutesNfse
{
    use DispatchesEvents;
    use ParsesEventResponse;
    use ValidatesChaveAcesso;

    public function __construct(
        private EmitsNfse $emitter,
        private NfseRequestPipeline $pipeline,
        private SubstitutionBuilder $substitutionBuilder,
        private NfseAmbiente $ambiente,
    ) {}

    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): SubstituicaoResponse
    {
        $this->validateChaveAcesso($chave);

        if (is_string($codigoMotivo)) {
            $codigoMotivo = CodigoJustificativaSubstituicao::from($codigoMotivo);
        }

        if (is_array($dps)) {
            $dps = DpsData::fromArray($dps);
        }

        // Inject subst into DPS (overwrite if present)
        $dps = new DpsData(
            infDPS: $dps->infDPS,
            prest: $dps->prest,
            serv: $dps->serv,
            valores: $dps->valores,
            subst: new Subst(
                chSubstda: $chave,
                cMotivo: $codigoMotivo,
                xMotivo: $descricao !== '' ? $descricao : null,
            ),
            toma: $dps->toma,
            interm: $dps->interm,
            IBSCBS: $dps->IBSCBS,
        );

        // Step 1: Emit the substitute NFS-e
        $emissaoResponse = $this->emitter->emitir($dps);

        if (! $emissaoResponse->sucesso) {
            return new SubstituicaoResponse(
                sucesso: false,
                emissao: $emissaoResponse,
                evento: null,
            );
        }

        $chaveSubstituta = $emissaoResponse->chave;

        // Step 2: Register the substitution event
        $operacao = 'substituir';
        $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

        $eventoResponse = $this->withFailureEvent($operacao, function () use ($chave, $chaveSubstituta, $codigoMotivo, $descricao, $operacao): NfseResponse {
            $identity = $this->pipeline->extractAuthorIdentity('substituir');

            $xml = $this->substitutionBuilder->buildAndValidate(
                tpAmb: $this->ambiente->value,
                verAplic: '1.0',
                dhEvento: date('c'),
                cnpjAutor: $identity['cnpj'],
                cpfAutor: $identity['cpf'],
                chNFSe: $chave,
                codigoMotivo: $codigoMotivo,
                chSubstituta: $chaveSubstituta,
                descricao: $descricao,
            );

            /** @var array{erros?: list<MessageData>, erro?: MessageData, eventoXmlGZipB64?: string, tipoAmbiente?: int, versaoAplicativo?: string, dataHoraProcessamento?: string} $result */
            $result = $this->pipeline->signCompressSend(
                $xml, 'infPedReg', 'pedRegEvento', 'pedidoRegistroEventoXmlGZipB64', 'substitute_nfse', ['chave' => $chave]
            );

            return $this->parseEventResponse($result, $chave, $operacao, new NfseSubstituted($chave, $chaveSubstituta));
        });

        return new SubstituicaoResponse(
            sucesso: $eventoResponse->sucesso,
            emissao: $emissaoResponse,
            evento: $eventoResponse,
        );
    }
}
```

**Step 3: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Operations/NfseSubstitutor.php`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Operations/NfseSubstitutor.php tests/Unit/Operations/NfseSubstitutorTest.php
git commit -m "refactor!: rewrite NfseSubstitutor for orchestrated emission + event"
```

---

### Task 4: Update `NfseClient` — change `substituir` and constructor wiring

**Files:**
- Modify: `src/NfseClient.php`

**Step 1: Update `NfseClient::substituir` signature and `forStandalone` wiring**

Key changes:
- `substituir` now accepts `DpsData|array $dps` instead of `string $chaveSubstituta`
- Return type changes to `SubstituicaoResponse`
- `forStandalone` passes `$emitter` to `NfseSubstitutor`

```php
// In NfseClient::substituir
/** @phpstan-param DpsData|DpsDataArray $dps */
public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): SubstituicaoResponse
{
    return $this->substitutor->substituir($chave, $dps, $codigoMotivo, $descricao);
}
```

In `forStandalone`, create `NfseEmitter` first, then pass it to both `NfseClient` and `NfseSubstitutor`:

```php
$emitter = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));

return new self(
    emitter: $emitter,
    canceller: new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente),
    substitutor: new NfseSubstitutor($emitter, $pipeline, new SubstitutionBuilder($xsdValidator), $ambiente),
    consulter: new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura),
);
```

**Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/NfseClient.php`
Expected: PASS

**Step 3: Commit**

```bash
git add src/NfseClient.php
git commit -m "refactor!: update NfseClient for orchestrated substituir"
```

---

### Task 5: Update `NfseNacional` facade

**Files:**
- Modify: `src/Facades/NfseNacional.php`

**Step 1: Update the `@method` PHPDoc for `substituir`**

Change:
```php
 * @method static NfseResponse substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = '')
```
To:
```php
 * @method static SubstituicaoResponse substituir(string $chave, DpsData|DpsDataArray $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = '')
```

Add the missing imports for `SubstituicaoResponse`.

**Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Facades/NfseNacional.php`
Expected: PASS

**Step 3: Commit**

```bash
git add src/Facades/NfseNacional.php
git commit -m "refactor!: update NfseNacional facade for orchestrated substituir"
```

---

### Task 6: Update `tests/helpers.php` — `makeNfseClient` wiring

**Files:**
- Modify: `tests/helpers.php` (lines 129-162)

**Step 1: Update `makeNfseClient` to pass emitter to substitutor**

```php
function makeNfseClient(
    ?GzipCompressor $gzipCompressor = null,
    ?string $pfxContent = null,
    string $prefeitura = '9999999',
): NfseClient {
    $pfxContent ??= makePfxContent();
    $certManager = new CertificateManager($pfxContent, 'secret');
    $ambiente = NfseAmbiente::HOMOLOGACAO;
    $prefeituraResolver = new PrefeituraResolver(__DIR__.'/../storage/prefeituras.json');
    $xsdValidator = makeXsdValidator();
    $httpClient = new NfseHttpClient($certManager->getCertificate(), 30, 10, true);
    $signer = new XmlSigner($certManager->getCertificate(), 'sha1');

    $pipeline = new NfseRequestPipeline(
        ambiente: $ambiente,
        prefeituraResolver: $prefeituraResolver,
        gzipCompressor: $gzipCompressor ?? new GzipCompressor,
        signer: $signer,
        authorIdentity: $certManager,
        prefeitura: $prefeitura,
        httpClient: $httpClient,
    );

    $queryExecutor = new NfseResponsePipeline($httpClient);
    $seFinUrl = $prefeituraResolver->resolveSeFinUrl($prefeitura, $ambiente);
    $adnUrl = $prefeituraResolver->resolveAdnUrl($prefeitura, $ambiente);

    $emitter = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));

    return new NfseClient(
        emitter: $emitter,
        canceller: new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente),
        substitutor: new NfseSubstitutor($emitter, $pipeline, new SubstitutionBuilder($xsdValidator), $ambiente),
        consulter: new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura),
    );
}
```

**Step 2: Run existing emitir tests to make sure nothing broke**

Run: `./vendor/bin/pest tests/Feature/NfseClientEmitirTest.php`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/helpers.php
git commit -m "test: update makeNfseClient to pass emitter to NfseSubstitutor"
```

---

### Task 7: Rewrite feature tests — `NfseClientSubstituirTest.php`

**Files:**
- Modify: `tests/Feature/NfseClientSubstituirTest.php`

**Step 1: Rewrite all tests for the new signature**

All tests now need to:
- Pass a DPS array instead of `$chaveSub`
- Fake **two** HTTP calls: emissão (returns `chaveAcesso`) then evento (returns `eventoXmlGZipB64`)
- Assert on `SubstituicaoResponse` instead of `NfseResponse`

The HTTP faking needs to use `Http::fakeSequence()` or `Http::fake()` with URL-specific patterns so the emissão call returns `chaveAcesso` and the evento call returns `eventoXmlGZipB64`.

Key test scenarios to cover:
1. Both emissão and evento succeed → `sucesso: true`
2. Emissão fails → `sucesso: false`, `evento: null`
3. Evento fails (rejection) → `sucesso: false`, both responses present
4. String codigoMotivo coercion
5. Invalid chave validation
6. Invalid string codigoMotivo → ValueError
7. Cert without CNPJ/CPF → NfseException (on evento step, emissão uses different cert path)
8. Server error on evento → HttpException
9. Empty descricao → no xMotivo in evento XML
10. Americana custom URL

**Step 2: Run tests**

Run: `./vendor/bin/pest tests/Feature/NfseClientSubstituirTest.php`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Feature/NfseClientSubstituirTest.php
git commit -m "test: rewrite substituir feature tests for orchestrated flow"
```

---

### Task 8: Update events dispatch tests

**Files:**
- Modify: `tests/Feature/EventsDispatchTest.php`

**Step 1: Update substituir event tests**

The test `'dispatches NfseSubstituted on successful substituir'` needs to:
- Fake two HTTP calls (emissão + evento)
- Pass DPS data instead of `$chaveSub`
- Assert both `NfseEmitted` and `NfseSubstituted` are dispatched (natural events from both operations)

The test `'dispatches NfseRejected on substituir rejection'` needs similar updates.

**Step 2: Run tests**

Run: `./vendor/bin/pest tests/Feature/EventsDispatchTest.php`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Feature/EventsDispatchTest.php
git commit -m "test: update substituir event dispatch tests"
```

---

### Task 9: Update `NfseSubstitutorTest` unit test

**Files:**
- Modify: `tests/Unit/Operations/NfseSubstitutorTest.php`

**Step 1: Rewrite unit test for new constructor and behavior**

The unit test should verify:
- `subst` is injected into the DPS passed to the emitter
- Emitter is called with correct DPS
- Event registration pipeline is called with the chave from emissão response
- Returns `SubstituicaoResponse` with correct structure

**Step 2: Run tests**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseSubstitutorTest.php`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Unit/Operations/NfseSubstitutorTest.php
git commit -m "test: rewrite NfseSubstitutor unit test for orchestrated flow"
```

---

### Task 10: Update example and README

**Files:**
- Modify: `examples/SubstituirNfse.php`
- Modify: `README.md`

**Step 1: Update example**

```php
<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\NfseClient;

$pfxContent = file_get_contents(__DIR__.'/certificado.pfx');
$senha = 'senha_certificado';
$prefeitura = 'PREFEITURA';

$client = NfseClient::forStandalone(
    pfxContent: $pfxContent,
    senha: $senha,
    prefeitura: $prefeitura,
    ambiente: NfseAmbiente::HOMOLOGACAO,
);

$chaveOriginal = '00000000000000000000000000000000000000000000000000';

// DPS da nota substituta (dados corrigidos)
$dpsSubstituta = [
    'infDPS' => [
        'tpAmb' => '2',
        'dhEmi' => date('Y-m-d\TH:i:sP'),
        'verAplic' => 'MeuSistema_v1.0',
        'serie' => '1',
        'nDPS' => '2',
        'dCompet' => date('Y-m-d'),
        'tpEmit' => '1',
        'cLocEmi' => '3550308',
    ],
    'prest' => [
        'CNPJ' => '00000000000000',
        'regTrib' => [
            'opSimpNac' => '2',
            'regEspTrib' => '0',
        ],
    ],
    'serv' => [
        'cLocPrestacao' => '3550308',
        'cServ' => [
            'cTribNac' => '010101',
            'xDescServ' => 'Desenvolvimento de software sob encomenda',
            'cNBS' => '116030000',
        ],
    ],
    'valores' => [
        'vServPrest' => ['vServ' => '1000.00'],
        'trib' => [
            'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
            'indTotTrib' => '0',
        ],
    ],
];

$response = $client->substituir(
    chave: $chaveOriginal,
    dps: $dpsSubstituta,
    codigoMotivo: CodigoJustificativaSubstituicao::Outros,
    descricao: 'Substituicao por correcao de dados',
);

if ($response->sucesso) {
    echo "NFSe substituida com sucesso!\n";
    echo "Chave substituta: {$response->emissao->chave}\n";
} elseif (! $response->emissao->sucesso) {
    echo "Falha na emissao da nota substituta:\n";
    foreach ($response->emissao->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} - {$erro->descricao}\n";
    }
} else {
    echo "Nota substituta emitida (chave: {$response->emissao->chave}), mas registro do evento falhou:\n";
    foreach ($response->evento->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} - {$erro->descricao}\n";
    }
}
```

**Step 2: Update README**

Update the `### Substituir NFSe` section with:
- New API showing DPS + chave + motivo
- Explanation that the lib handles emission + event registration internally
- Document the `SubstituicaoResponse` structure
- Document event sequence: `NfseRequested(emitir)` → `NfseEmitted` → `NfseRequested(substituir)` → `NfseSubstituted`

Update the events table to show `substituir` dispatches both emissão and evento events.

**Step 3: Commit**

```bash
git add examples/SubstituirNfse.php README.md
git commit -m "docs: update README and example for orchestrated substituir"
```

---

### Task 11: Run full quality suite

**Step 1: Run all quality checks**

```bash
./vendor/bin/pest --coverage --min=100 --parallel
./vendor/bin/pest --mutate --min=100 --parallel
./vendor/bin/pest --type-coverage --min=100
./vendor/bin/rector --dry-run
./vendor/bin/phpstan analyse
./vendor/bin/psalm --taint-analysis
./vendor/bin/pint -p
```

**Step 2: Fix any issues found**

If Pint/Rector changed files, re-run the full suite.

**Step 3: Final commit if needed**

```bash
git add -A
git commit -m "fix: quality check fixes for orchestrated substituir"
```