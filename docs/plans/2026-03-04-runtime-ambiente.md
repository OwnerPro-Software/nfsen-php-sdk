# Runtime Environment Override — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow `NfseClient::for()` and `Nfsen::for()` to accept an optional `ambiente` parameter that overrides the config value.

**Architecture:** Add `?NfseAmbiente $ambiente = null` as 4th parameter to both `for()` methods. When null, read from config (current behavior). When provided, use it directly. No other files change.

**Tech Stack:** PHP 8.2, Laravel 11/12, Pest

---

### Task 1: Write failing test — `for()` with ambiente override uses production URL

**Files:**
- Test: `tests/Feature/NfseClientEmitirTest.php`

**Step 1: Write the failing test**

Add this test at the end of the file, after the last `it(...)`:

```php
it('for() uses ambiente override instead of config value', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_OVERRIDE'], 201)]);

    // Config is HOMOLOGACAO by default, but we override to PRODUCAO
    $client = NfseClient::for(makePfxContent(), 'secret', '9999999', NfseAmbiente::PRODUCAO);
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.nfse.gov.br/SefinNacional/nfse' &&
        $req->method() === 'POST'
    );
})->with('dpsData');
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest --filter="for\(\) uses ambiente override"`
Expected: FAIL — `for()` does not accept 4th argument

---

### Task 2: Implement `NfseClient::for()` ambiente parameter

**Files:**
- Modify: `src/NfseClient.php:45-64`

**Step 1: Update the `for()` method signature and body**

Change line 45 from:
```php
public static function for(string $pfxContent, string $senha, string $prefeitura): self
```
to:
```php
public static function for(string $pfxContent, string $senha, string $prefeitura, ?NfseAmbiente $ambiente = null): self
```

Change line 55 from:
```php
ambiente: NfseAmbiente::fromConfig($config['ambiente']),
```
to:
```php
ambiente: $ambiente ?? NfseAmbiente::fromConfig($config['ambiente']),
```

Change line 63 from:
```php
return self::forStandalone($pfxContent, $senha, $prefeitura);
```
to:
```php
return self::forStandalone($pfxContent, $senha, $prefeitura, $ambiente ?? NfseAmbiente::HOMOLOGACAO);
```

**Step 2: Run the failing test to verify it passes**

Run: `./vendor/bin/pest --filter="for\(\) uses ambiente override"`
Expected: PASS

**Step 3: Run full suite to verify no regressions**

Run: `./vendor/bin/pest --parallel`
Expected: All tests pass

---

### Task 3: Write test — `for()` with explicit HOMOLOGACAO override when config is PRODUCAO

**Files:**
- Test: `tests/Feature/NfseClientEmitirTest.php`

**Step 1: Write the test**

```php
it('for() ambiente override takes precedence over config', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_HOMO'], 201)]);

    config()->set('nfsen.ambiente', NfseAmbiente::PRODUCAO->value);

    // Config says PRODUCAO, but we override to HOMOLOGACAO
    $client = NfseClient::for(makePfxContent(), 'secret', '9999999', NfseAmbiente::HOMOLOGACAO);
    $client->emitir($data);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse');
})->with('dpsData');
```

**Step 2: Run test**

Run: `./vendor/bin/pest --filter="for\(\) ambiente override takes precedence"`
Expected: PASS (implementation from Task 2 already handles this)

---

### Task 4: Update Facade

**Files:**
- Modify: `src/Facades/Nfsen.php:31-34`

**Step 1: Update Facade `for()` method**

Change:
```php
public static function for(string $pfxContent, string $senha, string $prefeitura): NfseClient
{
    return NfseClient::for($pfxContent, $senha, $prefeitura);
}
```
to:
```php
public static function for(string $pfxContent, string $senha, string $prefeitura, ?NfseAmbiente $ambiente = null): NfseClient
{
    return NfseClient::for($pfxContent, $senha, $prefeitura, $ambiente);
}
```

Add the missing import if not present:
```php
use OwnerPro\Nfsen\Enums\NfseAmbiente;
```

**Step 2: Run full suite**

Run: `./vendor/bin/pest --parallel`
Expected: All tests pass

---

### Task 5: Update README

**Files:**
- Modify: `README.md`

**Step 1: Update the Laravel Facade section**

In the "Laravel Facade" section (~line 193-210), change:
```markdown
// Usar certificado diferente por requisicao
$client = Nfsen::for($pfxContent, $senha, '3550308');
$response = $client->emitir($dps);
```
to:
```markdown
// Usar certificado diferente por requisicao
$client = Nfsen::for($pfxContent, $senha, '3550308');
$response = $client->emitir($dps);

// Sobrescrever ambiente (ignorar config)
use OwnerPro\Nfsen\Enums\NfseAmbiente;

$client = Nfsen::for($pfxContent, $senha, '3550308', NfseAmbiente::PRODUCAO);
$response = $client->emitir($dps);
```

---

### Task 6: Run quality checks

**Step 1: Full test suite with coverage**

Run: `./vendor/bin/pest --coverage --min=100 --parallel`
Expected: PASS, 100% coverage

**Step 2: Mutation testing**

Run: `./vendor/bin/pest --mutate --min=100 --parallel`
Expected: PASS, 100% MSI

**Step 3: Type coverage**

Run: `./vendor/bin/pest --type-coverage --min=100`
Expected: PASS, 100%

**Step 4: Static analysis and formatting**

Run sequentially:
```bash
./vendor/bin/rector --dry-run
./vendor/bin/phpstan analyse
./vendor/bin/psalm --taint-analysis
./vendor/bin/pint -p
```
Expected: All pass, no changes

**Step 5: If any tool changed files, re-run full suite**

Run: `./vendor/bin/pest --coverage --min=100 --parallel`

---

### Task 7: Commit

**Step 1: Commit all changes**

```bash
git add src/NfseClient.php src/Facades/Nfsen.php tests/Feature/NfseClientEmitirTest.php README.md
git commit -m "feat: allow runtime environment override in for() method"
```
