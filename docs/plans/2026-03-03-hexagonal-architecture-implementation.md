# Hexagonal Architecture Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactor the library to follow Ports & Adapters (Hexagonal Architecture) so the core never depends on concrete infrastructure classes.

**Architecture:** Extract 4 driven port interfaces (`SignsXml`, `ExtractsAuthorIdentity`, `SendsHttpRequests`, `ResolvesPrefeituras`) under `Contracts/Ports/Driven/`. Move 5 existing driving port interfaces to `Contracts/Ports/Driving/`. Make existing infrastructure classes implement the driven ports. Refactor core classes to depend on ports instead of concrete types.

**Tech Stack:** PHP 8.2+, Pest, PHPStan, Psalm, Rector, Pint

**Design doc:** `docs/plans/2026-03-03-hexagonal-architecture-design.md`

---

### Task 1: Create driven port interfaces

**Files:**
- Create: `src/Contracts/Ports/Driven/SignsXml.php`
- Create: `src/Contracts/Ports/Driven/ExtractsAuthorIdentity.php`
- Create: `src/Contracts/Ports/Driven/SendsHttpRequests.php`
- Create: `src/Contracts/Ports/Driven/ResolvesPrefeituras.php`

**Step 1: Create `SignsXml` interface**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Ports\Driven;

interface SignsXml
{
    public function sign(string $xml, string $tagname, string $rootname): string;
}
```

**Step 2: Create `ExtractsAuthorIdentity` interface**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Ports\Driven;

interface ExtractsAuthorIdentity
{
    /** @return array{cnpj: ?string, cpf: ?string} */
    public function extract(): array;
}
```

**Step 3: Create `SendsHttpRequests` interface**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Ports\Driven;

interface SendsHttpRequests
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $url, array $payload): array;

    /** @return array<string, mixed> */
    public function get(string $url): array;

    public function head(string $url): int;
}
```

**Step 4: Create `ResolvesPrefeituras` interface**

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Ports\Driven;

use OwnerPro\Nfsen\Enums\NfseAmbiente;

interface ResolvesPrefeituras
{
    public function resolveSeFinUrl(string $codigoIbge, NfseAmbiente $ambiente): string;

    public function resolveAdnUrl(string $codigoIbge, NfseAmbiente $ambiente): string;

    /** @param array<string, int|string> $params */
    public function resolveOperation(string $codigoIbge, string $operacao, array $params = []): string;
}
```

**Step 5: Run tests to verify nothing broke**

Run: `./vendor/bin/pest`
Expected: All tests pass (new interfaces are unused so far).

**Step 6: Commit**

```bash
git add src/Contracts/Ports/Driven/
git commit -m "feat: create driven port interfaces for hexagonal architecture"
```

---

### Task 2: CertificateManager — implement ExtractsAuthorIdentity

**Files:**
- Modify: `src/Certificates/CertificateManager.php`
- Test: `tests/Unit/Certificates/CertificateManagerTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Certificates/CertificateManagerTest.php`:

```php
it('extracts CNPJ from certificate via ExtractsAuthorIdentity port', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');
    $manager = new CertificateManager($pfxContent, 'secret');

    $identity = $manager->extract();

    expect($identity)->toBeArray()
        ->toHaveKeys(['cnpj', 'cpf']);
});

it('implements ExtractsAuthorIdentity interface', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');
    $manager = new CertificateManager($pfxContent, 'secret');

    expect($manager)->toBeInstanceOf(\OwnerPro\Nfsen\Contracts\Ports\Driven\ExtractsAuthorIdentity::class);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Certificates/CertificateManagerTest.php`
Expected: FAIL — `extract()` method does not exist.

**Step 3: Implement extract() and add implements**

Modify `src/Certificates/CertificateManager.php`:
- Add `use OwnerPro\Nfsen\Contracts\Ports\Driven\ExtractsAuthorIdentity;`
- Change class declaration to `final readonly class CertificateManager implements ExtractsAuthorIdentity`
- Add the `extract()` method (logic from `NfseRequestPipeline::extractAuthorIdentity()`):

```php
/** @return array{cnpj: ?string, cpf: ?string} */
public function extract(): array
{
    $cnpj = $this->certificate->getCnpj() ?: null;
    $cpf = $this->certificate->getCpf() ?: null;

    return ['cnpj' => $cnpj, 'cpf' => $cpf];
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Certificates/CertificateManagerTest.php`
Expected: All PASS.

**Step 5: Commit**

```bash
git add src/Certificates/CertificateManager.php tests/Unit/Certificates/CertificateManagerTest.php
git commit -m "feat: CertificateManager implements ExtractsAuthorIdentity port"
```

---

### Task 3: Adapters implement driven ports

Three adapters gain `implements` with zero logic changes.

**Files:**
- Modify: `src/Http/NfseHttpClient.php`
- Modify: `src/Signing/XmlSigner.php`
- Modify: `src/Services/PrefeituraResolver.php`

**Step 1: NfseHttpClient implements SendsHttpRequests**

In `src/Http/NfseHttpClient.php`:
- Add `use OwnerPro\Nfsen\Contracts\Ports\Driven\SendsHttpRequests;`
- Change to `final readonly class NfseHttpClient implements SendsHttpRequests`

**Step 2: XmlSigner implements SignsXml**

In `src/Signing/XmlSigner.php`:
- Add `use OwnerPro\Nfsen\Contracts\Ports\Driven\SignsXml;`
- Change to `final class XmlSigner implements SignsXml`

**Step 3: PrefeituraResolver implements ResolvesPrefeituras**

In `src/Services/PrefeituraResolver.php`:
- Add `use OwnerPro\Nfsen\Contracts\Ports\Driven\ResolvesPrefeituras;`
- Change to `final class PrefeituraResolver implements ResolvesPrefeituras`

**Step 4: Run tests to verify nothing broke**

Run: `./vendor/bin/pest`
Expected: All tests pass. Adding `implements` to classes with matching signatures is safe.

**Step 5: Commit**

```bash
git add src/Http/NfseHttpClient.php src/Signing/XmlSigner.php src/Services/PrefeituraResolver.php
git commit -m "feat: adapters implement driven port interfaces"
```

---

### Task 4: Refactor NfseRequestPipeline to use ports

This is the biggest change. The pipeline stops depending on concrete infrastructure.

**Files:**
- Modify: `src/Handlers/NfseRequestPipeline.php`
- Modify: `tests/helpers.php` (update `makeNfseClient()`)

**Step 1: Refactor NfseRequestPipeline constructor and methods**

In `src/Handlers/NfseRequestPipeline.php`:

Replace the full class with:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Handlers;

use OwnerPro\Nfsen\Contracts\Ports\Driven\ExtractsAuthorIdentity;
use OwnerPro\Nfsen\Contracts\Ports\Driven\ResolvesPrefeituras;
use OwnerPro\Nfsen\Contracts\Ports\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Contracts\Ports\Driven\SignsXml;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Support\GzipCompressor;

final readonly class NfseRequestPipeline
{
    public function __construct(
        private NfseAmbiente $ambiente,
        private ResolvesPrefeituras $prefeituraResolver,
        private GzipCompressor $gzipCompressor,
        private SignsXml $signer,
        private ExtractsAuthorIdentity $authorIdentity,
        private string $prefeitura,
        private SendsHttpRequests $httpClient,
    ) {}

    /**
     * @param  array<string, string>  $operationParams
     * @return array<string, mixed>
     */
    public function signCompressSend(string $xml, string $signTagName, string $signRootName, string $payloadKey, string $operationKey, array $operationParams = []): array
    {
        $signed = '<?xml version="1.0" encoding="UTF-8"?>'.$this->signer->sign($xml, $signTagName, $signRootName);
        $compressed = ($this->gzipCompressor)($signed);
        if ($compressed === false) {
            throw new NfseException('Falha ao comprimir XML.');
        }

        $payload = [$payloadKey => base64_encode($compressed)];

        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
        $opPath = $this->prefeituraResolver->resolveOperation($this->prefeitura, $operationKey, $operationParams);
        $url = $opPath !== '' ? rtrim($seFinUrl, '/').'/'.ltrim($opPath, '/') : $seFinUrl;

        /** @var array<string, mixed> */
        return $this->httpClient->post($url, $payload);
    }

    /**
     * @return array{cnpj: ?string, cpf: ?string}
     */
    public function extractAuthorIdentity(string $operacao): array
    {
        $identity = $this->authorIdentity->extract();

        if ($identity['cnpj'] === null && $identity['cpf'] === null) {
            throw new NfseException(sprintf('Certificado não contém CNPJ nem CPF. É necessário ao menos um para %s a NFS-e.', $operacao));
        }

        return $identity;
    }
}
```

**Step 2: Update `makeNfseClient()` in `tests/helpers.php`**

The `makeNfseClient()` function must wire the pipeline with the new constructor signature.

Replace the `makeNfseClient` function:

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
    $signer = new \OwnerPro\Nfsen\Signing\XmlSigner($certManager->getCertificate(), 'sha1');

    $pipeline = new NfseRequestPipeline(
        ambiente: $ambiente,
        prefeituraResolver: $prefeituraResolver,
        gzipCompressor: $gzipCompressor ?? new GzipCompressor,
        signer: $signer,
        authorIdentity: $certManager,
        prefeitura: $prefeitura,
        httpClient: $httpClient,
    );

    return new NfseClient(
        emitter: new NfseEmitter($pipeline, new DpsBuilder($xsdValidator)),
        canceller: new NfseCanceller($pipeline, new CancelamentoBuilder($xsdValidator), $ambiente),
        substitutor: new NfseSubstitutor($pipeline, new SubstituicaoBuilder($xsdValidator), $ambiente),
        queryExecutor: new NfseQueryExecutor($httpClient),
        prefeituraResolver: $prefeituraResolver,
        ambiente: $ambiente,
        prefeitura: $prefeitura,
    );
}
```

Also add the import for `XmlSigner` if not already present — or use the FQCN as shown above.

**Step 3: Run tests to verify everything passes**

Run: `./vendor/bin/pest`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add src/Handlers/NfseRequestPipeline.php tests/helpers.php
git commit -m "refactor: NfseRequestPipeline depends on driven ports instead of concrete classes"
```

---

### Task 5: Refactor NfseQueryExecutor to use SendsHttpRequests

**Files:**
- Modify: `src/Handlers/NfseQueryExecutor.php`

**Step 1: Change the constructor type hint**

In `src/Handlers/NfseQueryExecutor.php`:
- Add `use OwnerPro\Nfsen\Contracts\Ports\Driven\SendsHttpRequests;`
- Remove `use OwnerPro\Nfsen\Http\NfseHttpClient;`
- Change constructor: `private NfseHttpClient $httpClient` → `private SendsHttpRequests $httpClient`

**Step 2: Run tests**

Run: `./vendor/bin/pest`
Expected: All tests pass.

**Step 3: Commit**

```bash
git add src/Handlers/NfseQueryExecutor.php
git commit -m "refactor: NfseQueryExecutor depends on SendsHttpRequests port"
```

---

### Task 6: Refactor ConsultaBuilder to use ResolvesPrefeituras

**Files:**
- Modify: `src/Consulta/ConsultaBuilder.php`
- Modify: `tests/Unit/Consulta/ConsultaBuilderTest.php`

**Step 1: Change the constructor type hint**

In `src/Consulta/ConsultaBuilder.php`:
- Add `use OwnerPro\Nfsen\Contracts\Ports\Driven\ResolvesPrefeituras;`
- Remove `use OwnerPro\Nfsen\Services\PrefeituraResolver;`
- Change constructor: `private PrefeituraResolver $resolver` → `private ResolvesPrefeituras $resolver`

**Step 2: Update ConsultaBuilderTest if it references `PrefeituraResolver` directly**

In `tests/Unit/Consulta/ConsultaBuilderTest.php`:
- The test creates `PrefeituraResolver` instances directly — this is fine, since `PrefeituraResolver` now implements `ResolvesPrefeituras`. No test changes needed unless the constructor type check is tested.

**Step 3: Run tests**

Run: `./vendor/bin/pest`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add src/Consulta/ConsultaBuilder.php
git commit -m "refactor: ConsultaBuilder depends on ResolvesPrefeituras port"
```

---

### Task 7: Refactor NfseClient to use ResolvesPrefeituras

**Files:**
- Modify: `src/NfseClient.php`

**Step 1: Change the constructor type hint and update forStandalone()**

In `src/NfseClient.php`:
- Add `use OwnerPro\Nfsen\Contracts\Ports\Driven\ResolvesPrefeituras;`
- Remove `use OwnerPro\Nfsen\Services\PrefeituraResolver;` (if no longer needed — check if `forStandalone` still instantiates it)
- Actually, `forStandalone()` creates `new PrefeituraResolver(...)`, so the import stays. Only the constructor property type changes.
- Change constructor: `private PrefeituraResolver $prefeituraResolver` → `private ResolvesPrefeituras $prefeituraResolver`

Update `forStandalone()` to wire the new pipeline constructor (add `XmlSigner` creation):

```php
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
    int $connectTimeout = 10,
): self {
    $jsonPath = $prefeiturasJsonPath ?? __DIR__.'/../storage/prefeituras.json';
    $schemasPath = $schemesPath ?? __DIR__.'/../storage/schemes';

    $prefeituraResolver = new PrefeituraResolver($jsonPath);
    $prefeituraResolver->resolveSeFinUrl($prefeitura, $ambiente);

    $xsdValidator = new XsdValidator($schemasPath);
    $certManager = new CertificateManager($pfxContent, $senha);
    $effectiveSslVerify = $ambiente === NfseAmbiente::PRODUCAO || $sslVerify;
    $httpClient = new NfseHttpClient($certManager->getCertificate(), $timeout, $connectTimeout, $effectiveSslVerify);
    $signer = new \OwnerPro\Nfsen\Signing\XmlSigner($certManager->getCertificate(), $signingAlgorithm);

    $pipeline = new NfseRequestPipeline(
        ambiente: $ambiente,
        prefeituraResolver: $prefeituraResolver,
        gzipCompressor: new GzipCompressor,
        signer: $signer,
        authorIdentity: $certManager,
        prefeitura: $prefeitura,
        httpClient: $httpClient,
    );

    return new self(
        emitter: new NfseEmitter($pipeline, new DpsBuilder($xsdValidator)),
        canceller: new NfseCanceller($pipeline, new CancelamentoBuilder($xsdValidator), $ambiente),
        substitutor: new NfseSubstitutor($pipeline, new SubstituicaoBuilder($xsdValidator), $ambiente),
        queryExecutor: new NfseQueryExecutor($httpClient),
        prefeituraResolver: $prefeituraResolver,
        ambiente: $ambiente,
        prefeitura: $prefeitura,
    );
}
```

Add imports that may be needed:
- `use OwnerPro\Nfsen\Signing\XmlSigner;`

**Step 2: Run tests**

Run: `./vendor/bin/pest`
Expected: All tests pass.

**Step 3: Commit**

```bash
git add src/NfseClient.php
git commit -m "refactor: NfseClient depends on ResolvesPrefeituras port"
```

---

### Task 8: Move driving ports to `Contracts/Ports/Driving/`

Move the 5 existing interfaces and update all imports across the codebase.

**Files:**
- Move: `src/Contracts/EmitsNfse.php` → `src/Contracts/Ports/Driving/EmitsNfse.php`
- Move: `src/Contracts/CancelsNfse.php` → `src/Contracts/Ports/Driving/CancelsNfse.php`
- Move: `src/Contracts/SubstitutesNfse.php` → `src/Contracts/Ports/Driving/SubstitutesNfse.php`
- Move: `src/Contracts/QueriesNfse.php` → `src/Contracts/Ports/Driving/QueriesNfse.php`
- Move: `src/Contracts/ExecutesNfseRequests.php` → `src/Contracts/Ports/Driving/ExecutesNfseRequests.php`
- Delete: old files in `src/Contracts/` (after move)
- Update imports in all files that reference the old namespace

**Step 1: Create the files in new location with updated namespace**

Each file changes only its `namespace` line from `OwnerPro\Nfsen\Contracts` to `OwnerPro\Nfsen\Contracts\Ports\Driving`. Content stays identical otherwise.

**Step 2: Update all imports in source files**

Files that import from `OwnerPro\Nfsen\Contracts\*`:

| File | Old import | New import |
|------|-----------|------------|
| `src/NfseClient.php` | `Contracts\CancelsNfse` | `Contracts\Ports\Driving\CancelsNfse` |
| `src/NfseClient.php` | `Contracts\EmitsNfse` | `Contracts\Ports\Driving\EmitsNfse` |
| `src/NfseClient.php` | `Contracts\QueriesNfse` | `Contracts\Ports\Driving\QueriesNfse` |
| `src/NfseClient.php` | `Contracts\SubstitutesNfse` | `Contracts\Ports\Driving\SubstitutesNfse` |
| `src/Consulta/ConsultaBuilder.php` | `Contracts\ExecutesNfseRequests` | `Contracts\Ports\Driving\ExecutesNfseRequests` |
| `src/Handlers/NfseEmitter.php` | `Contracts\EmitsNfse` | `Contracts\Ports\Driving\EmitsNfse` |
| `src/Handlers/NfseCanceller.php` | `Contracts\CancelsNfse` | `Contracts\Ports\Driving\CancelsNfse` |
| `src/Handlers/NfseSubstitutor.php` | `Contracts\SubstitutesNfse` | `Contracts\Ports\Driving\SubstitutesNfse` |
| `src/Handlers/NfseQueryExecutor.php` | `Contracts\ExecutesNfseRequests` | `Contracts\Ports\Driving\ExecutesNfseRequests` |

**Step 3: Update test imports**

| File | Old import | New import |
|------|-----------|------------|
| `tests/Unit/Consulta/ConsultaBuilderTest.php` | `Contracts\ExecutesNfseRequests` | `Contracts\Ports\Driving\ExecutesNfseRequests` |

**Step 4: Delete old files**

```bash
rm src/Contracts/EmitsNfse.php
rm src/Contracts/CancelsNfse.php
rm src/Contracts/SubstitutesNfse.php
rm src/Contracts/QueriesNfse.php
rm src/Contracts/ExecutesNfseRequests.php
```

Verify `src/Contracts/` only contains `Ports/` directory now. If empty of direct files, good.

**Step 5: Run tests**

Run: `./vendor/bin/pest`
Expected: All tests pass.

**Step 6: Commit**

```bash
git add -A
git commit -m "refactor: move driving ports to Contracts/Ports/Driving namespace"
```

---

### Task 9: Update ArchTest and run full quality suite

**Files:**
- Modify: `tests/Unit/ArchTest.php` (if needed)

**Step 1: Run the full quality suite**

```bash
./vendor/bin/pest --coverage --min=100
./vendor/bin/pest --type-coverage --min=100
./vendor/bin/rector --dry-run
./vendor/bin/phpstan analyse
./vendor/bin/psalm --taint-analysis
./vendor/bin/pint -p
```

**Step 2: Fix any issues**

Common issues to expect:
- PHPStan may flag new type mismatches if port signatures don't match exactly
- Rector may suggest changes to the new interfaces
- Pint may format the new files
- ArchTest may need updates if it has namespace-specific assertions

If `pint` or `rector` made changes, re-run:
```bash
./vendor/bin/pest --coverage --min=100
```

**Step 3: Commit fixes**

```bash
git add -A
git commit -m "chore: fix quality gate issues after hexagonal refactoring"
```

---

### Task 10: Final verification

**Step 1: Run the complete quality suite one last time**

```bash
./vendor/bin/pest --coverage --min=100
./vendor/bin/pest --type-coverage --min=100
./vendor/bin/rector --dry-run
./vendor/bin/phpstan analyse
./vendor/bin/psalm --taint-analysis
./vendor/bin/pint -p
```

All must pass with zero changes and zero errors.

**Step 2: Verify directory structure**

```bash
find src/Contracts -type f | sort
```

Expected:
```
src/Contracts/Ports/Driven/ExtractsAuthorIdentity.php
src/Contracts/Ports/Driven/ResolvesPrefeituras.php
src/Contracts/Ports/Driven/SendsHttpRequests.php
src/Contracts/Ports/Driven/SignsXml.php
src/Contracts/Ports/Driving/CancelsNfse.php
src/Contracts/Ports/Driving/EmitsNfse.php
src/Contracts/Ports/Driving/ExecutesNfseRequests.php
src/Contracts/Ports/Driving/QueriesNfse.php
src/Contracts/Ports/Driving/SubstitutesNfse.php
```

**Step 3: Verify no concrete infrastructure imports in core**

```bash
grep -r "use OwnerPro\\Nfsen\\Http\\NfseHttpClient" src/Handlers/ src/Consulta/
grep -r "use OwnerPro\\Nfsen\\Signing\\XmlSigner" src/Handlers/
grep -r "use OwnerPro\\Nfsen\\Services\\PrefeituraResolver" src/Handlers/ src/Consulta/
grep -r "use OwnerPro\\Nfsen\\Certificates\\CertificateManager" src/Handlers/
```

Expected: All return empty (no matches). Only `src/NfseClient.php` (composition root) should import concrete classes.