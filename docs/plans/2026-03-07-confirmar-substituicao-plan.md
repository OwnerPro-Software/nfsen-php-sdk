# confirmarSubstituicao Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add `confirmarSubstituicao` method that executes only the event registration step (e105102), for when the user already emitted the substitute NFS-e or needs to retry a failed event.

**Architecture:** Extract the event registration logic from `NfseSubstitutor::substituir` into a private method. Both `substituir` and `confirmarSubstituicao` call it. Add the new method to the `SubstitutesNfse` interface, `NfseClient`, and facade.

**Tech Stack:** PHP 8.2+, Pest, PHPStan, Psalm, Rector, Pint

---

### Task 1: Add `confirmarSubstituicao` to `SubstitutesNfse` interface

**Files:**
- Modify: `src/Contracts/Driving/SubstitutesNfse.php`

**Step 1: Add the method to the interface**

```php
<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Responses\SubstituicaoResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
interface SubstitutesNfse
{
    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): SubstituicaoResponse;

    public function confirmarSubstituicao(string $chaveSubstituida, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): NfseResponse;
}
```

**Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Contracts/Driving/SubstitutesNfse.php`
Expected: PASS

---

### Task 2: Extract event logic and implement `confirmarSubstituicao` in `NfseSubstitutor`

**Files:**
- Modify: `src/Operations/NfseSubstitutor.php`
- Modify: `tests/Unit/Operations/NfseSubstitutorTest.php`

**Step 1: Write failing test**

Add to `tests/Unit/Operations/NfseSubstitutorTest.php`:

```php
it('confirmarSubstituicao registers event without emitting', function () {
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $substitutor = makeNfseSubstitutor();
    $response = $substitutor->confirmarSubstituicao(
        $chave,
        $chaveSub,
        CodigoJustificativaSubstituicao::Outros,
        'Outro motivo para substituicao',
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe($chave);
    expect($response->xml)->not->toBeNull();

    // Only ONE HTTP call (event), not two (no emission)
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $req) => str_contains($req->url(), $chave.'/eventos') &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseSubstitutorTest.php`
Expected: FAIL — method not found

**Step 3: Refactor `NfseSubstitutor`**

Extract lines 82-115 (event registration) into a private method `registrarEvento` and add `confirmarSubstituicao`:

```php
public function confirmarSubstituicao(string $chaveSubstituida, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): NfseResponse
{
    $this->validateChaveAcesso($chaveSubstituida);
    $this->validateChaveAcesso($chaveSubstituta);

    if (is_string($codigoMotivo)) {
        $codigoMotivo = CodigoJustificativaSubstituicao::from($codigoMotivo);
    }

    return $this->registrarEvento($chaveSubstituida, $chaveSubstituta, $codigoMotivo, $descricao);
}

private function registrarEvento(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao $codigoMotivo, string $descricao): NfseResponse
{
    $operacao = 'substituir';
    $this->dispatchEvent(new NfseRequested($operacao, ['chave' => $chave]));

    return $this->withFailureEvent($operacao, function () use ($chave, $chaveSubstituta, $codigoMotivo, $descricao, $operacao): NfseResponse {
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
}
```

Update `substituir` to call `$this->registrarEvento(...)` instead of inlining the event logic.

**Step 4: Run tests**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseSubstitutorTest.php`
Expected: PASS

---

### Task 3: Add `confirmarSubstituicao` to `NfseClient`

**Files:**
- Modify: `src/NfseClient.php`

**Step 1: Add the method**

After the `substituir` method, add:

```php
public function confirmarSubstituicao(string $chaveSubstituida, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): NfseResponse
{
    return $this->substitutor->confirmarSubstituicao($chaveSubstituida, $chaveSubstituta, $codigoMotivo, $descricao);
}
```

**Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/NfseClient.php`
Expected: PASS

---

### Task 4: Add `confirmarSubstituicao` to facade

**Files:**
- Modify: `src/Facades/NfseNacional.php`

**Step 1: Add `@method` PHPDoc**

Add after the `substituir` line:

```php
 * @method static NfseResponse confirmarSubstituicao(string $chaveSubstituida, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = '')
```

**Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Facades/NfseNacional.php`
Expected: PASS

---

### Task 5: Add feature tests for `confirmarSubstituicao`

**Files:**
- Create: `tests/Feature/NfseClientConfirmarSubstituicaoTest.php`

**Step 1: Write tests**

Test scenarios:
1. Success — returns `NfseResponse` with `sucesso: true`
2. Rejection — API returns error
3. String codigoMotivo coercion
4. Invalid string codigoMotivo → `ValueError`
5. Invalid chaveSubstituida → `InvalidArgumentException`
6. Invalid chaveSubstituta → `InvalidArgumentException`
7. Cert without CNPJ/CPF → `NfseException`
8. Server error → `HttpException`
9. Empty descricao → no `xMotivo`
10. Americana custom URL

These tests use single `Http::fake()` (one HTTP call only — no emission).

```php
<?php

covers(\Pulsar\NfseNacional\NfseClient::class, \Pulsar\NfseNacional\Operations\NfseSubstitutor::class);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\NfseClient;
use Pulsar\NfseNacional\Support\GzipCompressor;

it('confirmarSubstituicao returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $response = $client->confirmarSubstituicao(
        $chave,
        $chaveSub,
        CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
        'Desenquadramento do Simples Nacional',
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe($chave);
    expect($response->xml)->not->toBeNull();

    Http::assertSent(fn (Request $req) => str_contains($req->url(), $chave.'/eventos') &&
        $req->method() === 'POST' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

// ... remaining tests follow the same pattern as the old NfseClientSubstituirTest
// (single HTTP fake, same assertions on NfseResponse)
```

**Step 2: Run tests**

Run: `./vendor/bin/pest tests/Feature/NfseClientConfirmarSubstituicaoTest.php`
Expected: PASS

---

### Task 6: Add event dispatch tests for `confirmarSubstituicao`

**Files:**
- Modify: `tests/Feature/EventsDispatchTest.php`

**Step 1: Add two tests**

```php
it('dispatches NfseSubstituted on successful confirmarSubstituicao', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo');

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'substituir');
    Event::assertDispatched(NfseSubstituted::class, fn (NfseSubstituted $e) => $e->chave === $chave && $e->chaveSubstituta === $chaveSub);
    Event::assertNotDispatched(NfseEmitted::class);
});

it('dispatches NfseRejected on confirmarSubstituicao rejection', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'NFSe não encontrada', 'codigo' => 'E404']], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'E404');
    Event::assertNotDispatched(NfseSubstituted::class);
});
```

**Step 2: Run tests**

Run: `./vendor/bin/pest tests/Feature/EventsDispatchTest.php`
Expected: PASS

---

### Task 7: Update README

**Files:**
- Modify: `README.md`

**Step 1: Add `confirmarSubstituicao` section after the `substituir` section**

Add after the `Codigos de substituição:` line and before `### Consultas`:

```markdown
#### Confirmar substituição (apenas etapa 2)

Se você já emitiu a nota substituta por conta própria, ou se a etapa 2 do `substituir` falhou e precisa ser refeita, use `confirmarSubstituicao` para registrar apenas o evento de cancelamento por substituição:

\```php
$response = $client->confirmarSubstituicao(
    chaveSubstituida: '00000000000000000000000000000000000000000000000000',
    chaveSubstituta: '11111111111111111111111111111111111111111111111111',
    codigoMotivo: CodigoJustificativaSubstituicao::Outros,
    descricao: 'Substituicao por correcao de dados',
); // NfseResponse
\```
```

**Step 2: Verify README renders correctly**

---

### Task 8: Run full quality suite

**Step 1: Run all checks**

```bash
./vendor/bin/pest --coverage --min=100 --parallel
./vendor/bin/pest --mutate --min=100 --parallel
./vendor/bin/pest --type-coverage --min=100
./vendor/bin/rector --dry-run
./vendor/bin/phpstan analyse
./vendor/bin/psalm --taint-analysis
./vendor/bin/pint -p
```

**Step 2: Fix any issues, re-run if needed**