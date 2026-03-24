# Screaming Architecture Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Reorganize `src/` from technical-concern folders to domain-screaming folders, so the top-level communicates "NFS-e Nacional system" instead of "PHP package patterns."

**Architecture:** Pure namespace refactoring — no logic changes. Move files with `git mv`, update namespace declarations and imports, verify tests pass after each batch. PSR-4 autoload root (`OwnerPro\Nfsen\` → `src/`) stays unchanged.

**Tech Stack:** PHP 8.2, Laravel, Pest, PHPStan, Psalm, Rector, Pint

**Design doc:** `docs/plans/2026-03-03-screaming-architecture-design.md`

---

## Important Notes

- **Never skip tests between tasks.** Each task must end with `./vendor/bin/pest` GREEN.
- **Use `git mv`** for all moves to preserve git history.
- **sed order matters:** Always replace more-specific namespace prefixes before less-specific ones.
- **Top-level enums stay:** `Enums/NfseAmbiente.php`, `Enums/TipoEvento.php`, `Enums/CodigoJustificativaCancelamento.php`, `Enums/CodigoJustificativaSubstituicao.php` remain in `src/Enums/` (they're cross-cutting, not DPS-specific).
- **composer.json PSR-4 unchanged:** The root mapping `OwnerPro\\Nfsen\\` → `src/` covers all subdirectories automatically.

---

### Task 1: Move Infrastructure Adapters

Move `Http/`, `Signing/`, `Certificates/`, `Services/` into a single `Adapters/` folder.

**Files:**
- Move: `src/Http/NfseHttpClient.php` → `src/Adapters/NfseHttpClient.php`
- Move: `src/Signing/XmlSigner.php` → `src/Adapters/XmlSigner.php`
- Move: `src/Certificates/CertificateManager.php` → `src/Adapters/CertificateManager.php`
- Move: `src/Services/PrefeituraResolver.php` → `src/Adapters/PrefeituraResolver.php`

**Step 1: Create directory and move files**

```bash
mkdir -p src/Adapters
git mv src/Http/NfseHttpClient.php src/Adapters/NfseHttpClient.php
git mv src/Signing/XmlSigner.php src/Adapters/XmlSigner.php
git mv src/Certificates/CertificateManager.php src/Adapters/CertificateManager.php
git mv src/Services/PrefeituraResolver.php src/Adapters/PrefeituraResolver.php
rmdir src/Http src/Signing src/Certificates src/Services
```

**Step 2: Update namespace declarations in moved files**

```bash
sed -i 's/namespace OwnerPro\\Nfsen\\Http;/namespace OwnerPro\\Nfsen\\Adapters;/' src/Adapters/NfseHttpClient.php
sed -i 's/namespace OwnerPro\\Nfsen\\Signing;/namespace OwnerPro\\Nfsen\\Adapters;/' src/Adapters/XmlSigner.php
sed -i 's/namespace OwnerPro\\Nfsen\\Certificates;/namespace OwnerPro\\Nfsen\\Adapters;/' src/Adapters/CertificateManager.php
sed -i 's/namespace OwnerPro\\Nfsen\\Services;/namespace OwnerPro\\Nfsen\\Adapters;/' src/Adapters/PrefeituraResolver.php
```

**Step 3: Update imports across entire codebase**

```bash
find src tests -name '*.php' -exec sed -i \
  -e 's/OwnerPro\\Nfsen\\Http\\NfseHttpClient/OwnerPro\\Nfsen\\Adapters\\NfseHttpClient/g' \
  -e 's/OwnerPro\\Nfsen\\Signing\\XmlSigner/OwnerPro\\Nfsen\\Adapters\\XmlSigner/g' \
  -e 's/OwnerPro\\Nfsen\\Certificates\\CertificateManager/OwnerPro\\Nfsen\\Adapters\\CertificateManager/g' \
  -e 's/OwnerPro\\Nfsen\\Services\\PrefeituraResolver/OwnerPro\\Nfsen\\Adapters\\PrefeituraResolver/g' \
  {} +
```

**Step 4: Update architecture test namespace references**

In `tests/Unit/ArchTest.php`, the hexagonal boundary rules reference old namespaces:
```php
// OLD:
'OwnerPro\Nfsen\Http',
'OwnerPro\Nfsen\Signing',
'OwnerPro\Nfsen\Services',
'OwnerPro\Nfsen\Certificates',
// NEW (all become):
'OwnerPro\Nfsen\Adapters',
```

```bash
find tests -name 'ArchTest.php' -exec sed -i \
  -e "s/'OwnerPro\\\\Nfsen\\\\Http'/'OwnerPro\\\\Nfsen\\\\Adapters'/g" \
  -e "s/'OwnerPro\\\\Nfsen\\\\Signing'/'OwnerPro\\\\Nfsen\\\\Adapters'/g" \
  -e "s/'OwnerPro\\\\Nfsen\\\\Services'/'OwnerPro\\\\Nfsen\\\\Adapters'/g" \
  -e "s/'OwnerPro\\\\Nfsen\\\\Certificates'/'OwnerPro\\\\Nfsen\\\\Adapters'/g" \
  {} +
```

Note: After deduplication, both arch rules will have `'OwnerPro\Nfsen\Adapters'` once instead of 4 separate namespaces. Manually deduplicate the arrays.

**Step 5: Run tests**

```bash
./vendor/bin/pest
```

Expected: All tests pass.

**Step 6: Commit**

```bash
git add -A && git commit -m "refactor: move infrastructure adapters to Adapters/ namespace"
```

---

### Task 2: Move Pipeline

Move `NfseRequestPipeline` and its `Concerns/` traits from `Handlers/` to `Pipeline/`.

**Files:**
- Move: `src/Handlers/NfseRequestPipeline.php` → `src/Pipeline/NfseRequestPipeline.php`
- Move: `src/Handlers/Concerns/DispatchesEvents.php` → `src/Pipeline/Concerns/DispatchesEvents.php`
- Move: `src/Handlers/Concerns/ValidatesChaveAcesso.php` → `src/Pipeline/Concerns/ValidatesChaveAcesso.php`
- Move: `src/Handlers/Concerns/ParsesEventoResponse.php` → `src/Pipeline/Concerns/ParsesEventoResponse.php`

**Step 1: Create directories and move files**

```bash
mkdir -p src/Pipeline/Concerns
git mv src/Handlers/NfseRequestPipeline.php src/Pipeline/NfseRequestPipeline.php
git mv src/Handlers/Concerns/DispatchesEvents.php src/Pipeline/Concerns/DispatchesEvents.php
git mv src/Handlers/Concerns/ValidatesChaveAcesso.php src/Pipeline/Concerns/ValidatesChaveAcesso.php
git mv src/Handlers/Concerns/ParsesEventoResponse.php src/Pipeline/Concerns/ParsesEventoResponse.php
rmdir src/Handlers/Concerns
```

**Step 2: Update namespace declarations**

```bash
sed -i 's/namespace OwnerPro\\Nfsen\\Handlers;/namespace OwnerPro\\Nfsen\\Pipeline;/' src/Pipeline/NfseRequestPipeline.php
sed -i 's/namespace OwnerPro\\Nfsen\\Handlers\\Concerns;/namespace OwnerPro\\Nfsen\\Pipeline\\Concerns;/' src/Pipeline/Concerns/*.php
```

**Step 3: Update imports across codebase**

```bash
find src tests -name '*.php' -exec sed -i \
  -e 's/OwnerPro\\Nfsen\\Handlers\\Concerns\\DispatchesEvents/OwnerPro\\Nfsen\\Pipeline\\Concerns\\DispatchesEvents/g' \
  -e 's/OwnerPro\\Nfsen\\Handlers\\Concerns\\ValidatesChaveAcesso/OwnerPro\\Nfsen\\Pipeline\\Concerns\\ValidatesChaveAcesso/g' \
  -e 's/OwnerPro\\Nfsen\\Handlers\\Concerns\\ParsesEventoResponse/OwnerPro\\Nfsen\\Pipeline\\Concerns\\ParsesEventoResponse/g' \
  -e 's/OwnerPro\\Nfsen\\Handlers\\NfseRequestPipeline/OwnerPro\\Nfsen\\Pipeline\\NfseRequestPipeline/g' \
  {} +
```

**Step 4: Run tests**

```bash
./vendor/bin/pest
```

Expected: All tests pass.

**Step 5: Commit**

```bash
git add -A && git commit -m "refactor: move request pipeline and concerns to Pipeline/ namespace"
```

---

### Task 3: Move Operations

Move the 3 handler classes from `Handlers/` to `Operations/`.

**Files:**
- Move: `src/Handlers/NfseEmitter.php` → `src/Operations/NfseEmitter.php`
- Move: `src/Handlers/NfseCanceller.php` → `src/Operations/NfseCanceller.php`
- Move: `src/Handlers/NfseSubstitutor.php` → `src/Operations/NfseSubstitutor.php`

**Step 1: Create directory and move files**

```bash
mkdir -p src/Operations
git mv src/Handlers/NfseEmitter.php src/Operations/NfseEmitter.php
git mv src/Handlers/NfseCanceller.php src/Operations/NfseCanceller.php
git mv src/Handlers/NfseSubstitutor.php src/Operations/NfseSubstitutor.php
```

**Step 2: Update namespace declarations**

```bash
sed -i 's/namespace OwnerPro\\Nfsen\\Handlers;/namespace OwnerPro\\Nfsen\\Operations;/' src/Operations/*.php
```

**Step 3: Update imports across codebase**

```bash
find src tests -name '*.php' -exec sed -i \
  -e 's/OwnerPro\\Nfsen\\Handlers\\NfseEmitter/OwnerPro\\Nfsen\\Operations\\NfseEmitter/g' \
  -e 's/OwnerPro\\Nfsen\\Handlers\\NfseCanceller/OwnerPro\\Nfsen\\Operations\\NfseCanceller/g' \
  -e 's/OwnerPro\\Nfsen\\Handlers\\NfseSubstitutor/OwnerPro\\Nfsen\\Operations\\NfseSubstitutor/g' \
  {} +
```

**Step 4: Run tests**

```bash
./vendor/bin/pest
```

Expected: All tests pass.

**Step 5: Commit**

```bash
git add -A && git commit -m "refactor: move operation handlers to Operations/ namespace"
```

---

### Task 4: Move Builders (Xml + Consulta + QueryExecutor)

Move XML builders to `Builders/Xml/` (with sub-builders in `Parts/`), move ConsultaBuilder and NfseQueryExecutor to `Builders/Consulta/`.

**Files:**
- Move: `src/Xml/DpsBuilder.php` → `src/Builders/Xml/DpsBuilder.php`
- Move: `src/Xml/Builders/*.php` → `src/Builders/Xml/Parts/*.php`
- Move: `src/Consulta/ConsultaBuilder.php` → `src/Builders/Consulta/ConsultaBuilder.php`
- Move: `src/Handlers/NfseQueryExecutor.php` → `src/Builders/Consulta/NfseQueryExecutor.php`

**Step 1: Create directories and move files**

```bash
mkdir -p src/Builders/Xml/Parts src/Builders/Consulta

# XML builders
git mv src/Xml/DpsBuilder.php src/Builders/Xml/DpsBuilder.php
git mv src/Xml/Builders/CancelamentoBuilder.php src/Builders/Xml/Parts/CancelamentoBuilder.php
git mv src/Xml/Builders/CreatesTextElements.php src/Builders/Xml/Parts/CreatesTextElements.php
git mv src/Xml/Builders/IBSCBSBuilder.php src/Builders/Xml/Parts/IBSCBSBuilder.php
git mv src/Xml/Builders/PrestadorBuilder.php src/Builders/Xml/Parts/PrestadorBuilder.php
git mv src/Xml/Builders/ServicoBuilder.php src/Builders/Xml/Parts/ServicoBuilder.php
git mv src/Xml/Builders/SubstituicaoBuilder.php src/Builders/Xml/Parts/SubstituicaoBuilder.php
git mv src/Xml/Builders/TomadorBuilder.php src/Builders/Xml/Parts/TomadorBuilder.php
git mv src/Xml/Builders/ValoresBuilder.php src/Builders/Xml/Parts/ValoresBuilder.php
rmdir src/Xml/Builders src/Xml

# Consulta builders
git mv src/Consulta/ConsultaBuilder.php src/Builders/Consulta/ConsultaBuilder.php
git mv src/Handlers/NfseQueryExecutor.php src/Builders/Consulta/NfseQueryExecutor.php
rmdir src/Consulta
rmdir src/Handlers 2>/dev/null || true
```

**Step 2: Update namespace declarations**

Important: Update the more-specific `Xml\Builders` namespace BEFORE the less-specific `Xml` namespace.

```bash
# Sub-builders: Xml\Builders → Builders\Xml\Parts
sed -i 's/namespace OwnerPro\\Nfsen\\Xml\\Builders;/namespace OwnerPro\\Nfsen\\Builders\\Xml\\Parts;/' src/Builders/Xml/Parts/*.php

# DpsBuilder: Xml → Builders\Xml
sed -i 's/namespace OwnerPro\\Nfsen\\Xml;/namespace OwnerPro\\Nfsen\\Builders\\Xml;/' src/Builders/Xml/DpsBuilder.php

# ConsultaBuilder: Consulta → Builders\Consulta
sed -i 's/namespace OwnerPro\\Nfsen\\Consulta;/namespace OwnerPro\\Nfsen\\Builders\\Consulta;/' src/Builders/Consulta/ConsultaBuilder.php

# NfseQueryExecutor: Handlers → Builders\Consulta
sed -i 's/namespace OwnerPro\\Nfsen\\Handlers;/namespace OwnerPro\\Nfsen\\Builders\\Consulta;/' src/Builders/Consulta/NfseQueryExecutor.php
```

**Step 3: Update imports across codebase**

Important: Replace more-specific patterns first.

```bash
# First: Xml\Builders\* → Builders\Xml\Parts\* (more specific)
find src tests -name '*.php' -exec sed -i \
  's/OwnerPro\\Nfsen\\Xml\\Builders/OwnerPro\\Nfsen\\Builders\\Xml\\Parts/g' \
  {} +

# Then: Xml\DpsBuilder → Builders\Xml\DpsBuilder
find src tests -name '*.php' -exec sed -i \
  's/OwnerPro\\Nfsen\\Xml\\DpsBuilder/OwnerPro\\Nfsen\\Builders\\Xml\\DpsBuilder/g' \
  {} +

# Consulta\ConsultaBuilder → Builders\Consulta\ConsultaBuilder
find src tests -name '*.php' -exec sed -i \
  's/OwnerPro\\Nfsen\\Consulta\\ConsultaBuilder/OwnerPro\\Nfsen\\Builders\\Consulta\\ConsultaBuilder/g' \
  {} +

# NfseQueryExecutor (already moved from Handlers in Task 3's sed, but may have been missed if it wasn't caught by the Handlers patterns)
find src tests -name '*.php' -exec sed -i \
  's/OwnerPro\\Nfsen\\Handlers\\NfseQueryExecutor/OwnerPro\\Nfsen\\Builders\\Consulta\\NfseQueryExecutor/g' \
  {} +
```

**Step 4: Update architecture test**

In `tests/Unit/ArchTest.php`, update the namespace references:
- `OwnerPro\Nfsen\Handlers` → `OwnerPro\Nfsen\Operations` + `OwnerPro\Nfsen\Pipeline` + `OwnerPro\Nfsen\Builders\Consulta`
- `OwnerPro\Nfsen\Consulta` → `OwnerPro\Nfsen\Builders\Consulta`

The arch rules should now enforce that Operations, Pipeline, and Builders\Consulta do NOT depend on Adapters. Rewrite the rules:

```php
arch('operations do not depend on infrastructure adapters')
    ->expect('OwnerPro\Nfsen\Operations')
    ->not->toUse([
        'OwnerPro\Nfsen\Adapters',
    ]);

arch('pipeline does not depend on infrastructure adapters')
    ->expect('OwnerPro\Nfsen\Pipeline')
    ->not->toUse([
        'OwnerPro\Nfsen\Adapters',
    ]);

arch('consulta builders do not depend on infrastructure adapters')
    ->expect('OwnerPro\Nfsen\Builders\Consulta')
    ->not->toUse([
        'OwnerPro\Nfsen\Adapters',
    ]);
```

**Step 5: Run tests**

```bash
./vendor/bin/pest
```

Expected: All tests pass.

**Step 6: Commit**

```bash
git add -A && git commit -m "refactor: move XML and Consulta builders to Builders/ namespace"
```

---

### Task 5: Move DPS DTOs

Move `DTOs/Dps/` to `Dps/DTO/`. This is the largest batch (~25 files across 8 subdirectories).

**Files:**
- Move: `src/DTOs/Dps/*` → `src/Dps/DTO/*` (entire subtree)

**Step 1: Create directories and move files**

```bash
mkdir -p src/Dps/DTO

# Move all subdirectories
git mv src/DTOs/Dps/Concerns src/Dps/DTO/Concerns
git mv src/DTOs/Dps/IBSCBS src/Dps/DTO/IBSCBS
git mv src/DTOs/Dps/InfDPS src/Dps/DTO/InfDPS
git mv src/DTOs/Dps/Prestador src/Dps/DTO/Prestador
git mv src/DTOs/Dps/Servico src/Dps/DTO/Servico
git mv src/DTOs/Dps/Shared src/Dps/DTO/Shared
git mv src/DTOs/Dps/Tomador src/Dps/DTO/Tomador
git mv src/DTOs/Dps/Valores src/Dps/DTO/Valores
git mv src/DTOs/Dps/DpsData.php src/Dps/DTO/DpsData.php
rmdir src/DTOs/Dps
```

**Step 2: Update namespace declarations in all moved files**

```bash
find src/Dps/DTO -name '*.php' -exec sed -i \
  's/namespace OwnerPro\\Nfsen\\DTOs\\Dps/namespace OwnerPro\\Nfsen\\Dps\\DTO/g' \
  {} +
```

**Step 3: Update imports across entire codebase**

```bash
find src tests -name '*.php' -exec sed -i \
  's/OwnerPro\\Nfsen\\DTOs\\Dps/OwnerPro\\Nfsen\\Dps\\DTO/g' \
  {} +
```

**Step 4: Run tests**

```bash
./vendor/bin/pest
```

Expected: All tests pass.

**Step 5: Commit**

```bash
git add -A && git commit -m "refactor: move DPS DTOs to Dps/DTO/ namespace"
```

---

### Task 6: Move DPS Enums

Move `Enums/Dps/` to `Dps/Enums/`. The 4 top-level enums (`NfseAmbiente`, `TipoEvento`, `CodigoJustificativaCancelamento`, `CodigoJustificativaSubstituicao`) stay in `Enums/`.

**Files:**
- Move: `src/Enums/Dps/*` → `src/Dps/Enums/*` (entire subtree)

**Step 1: Create directories and move files**

```bash
mkdir -p src/Dps/Enums

git mv src/Enums/Dps/IBSCBS src/Dps/Enums/IBSCBS
git mv src/Enums/Dps/InfDPS src/Dps/Enums/InfDPS
git mv src/Enums/Dps/Prestador src/Dps/Enums/Prestador
git mv src/Enums/Dps/Servico src/Dps/Enums/Servico
git mv src/Enums/Dps/Shared src/Dps/Enums/Shared
git mv src/Enums/Dps/Valores src/Dps/Enums/Valores
rmdir src/Enums/Dps
```

**Step 2: Update namespace declarations**

```bash
find src/Dps/Enums -name '*.php' -exec sed -i \
  's/namespace OwnerPro\\Nfsen\\Enums\\Dps/namespace OwnerPro\\Nfsen\\Dps\\Enums/g' \
  {} +
```

**Step 3: Update imports across codebase**

```bash
find src tests -name '*.php' -exec sed -i \
  's/OwnerPro\\Nfsen\\Enums\\Dps/OwnerPro\\Nfsen\\Dps\\Enums/g' \
  {} +
```

**Step 4: Run tests**

```bash
./vendor/bin/pest
```

Expected: All tests pass.

**Step 5: Commit**

```bash
git add -A && git commit -m "refactor: move DPS enums to Dps/Enums/ namespace"
```

---

### Task 7: Move Response DTOs

Move the 4 non-DPS response DTOs from `DTOs/` to `Responses/`.

**Files:**
- Move: `src/DTOs/NfseResponse.php` → `src/Responses/NfseResponse.php`
- Move: `src/DTOs/DanfseResponse.php` → `src/Responses/DanfseResponse.php`
- Move: `src/DTOs/EventosResponse.php` → `src/Responses/EventosResponse.php`
- Move: `src/DTOs/MensagemProcessamento.php` → `src/Responses/MensagemProcessamento.php`

**Step 1: Create directory and move files**

```bash
mkdir -p src/Responses
git mv src/DTOs/NfseResponse.php src/Responses/NfseResponse.php
git mv src/DTOs/DanfseResponse.php src/Responses/DanfseResponse.php
git mv src/DTOs/EventosResponse.php src/Responses/EventosResponse.php
git mv src/DTOs/MensagemProcessamento.php src/Responses/MensagemProcessamento.php
rmdir src/DTOs
```

**Step 2: Update namespace declarations**

```bash
sed -i 's/namespace OwnerPro\\Nfsen\\DTOs;/namespace OwnerPro\\Nfsen\\Responses;/' src/Responses/*.php
```

**Step 3: Update imports across codebase**

```bash
find src tests -name '*.php' -exec sed -i \
  -e 's/OwnerPro\\Nfsen\\DTOs\\NfseResponse/OwnerPro\\Nfsen\\Responses\\NfseResponse/g' \
  -e 's/OwnerPro\\Nfsen\\DTOs\\DanfseResponse/OwnerPro\\Nfsen\\Responses\\DanfseResponse/g' \
  -e 's/OwnerPro\\Nfsen\\DTOs\\EventosResponse/OwnerPro\\Nfsen\\Responses\\EventosResponse/g' \
  -e 's/OwnerPro\\Nfsen\\DTOs\\MensagemProcessamento/OwnerPro\\Nfsen\\Responses\\MensagemProcessamento/g' \
  {} +
```

**Step 4: Run tests**

```bash
./vendor/bin/pest
```

Expected: All tests pass.

**Step 5: Commit**

```bash
git add -A && git commit -m "refactor: move response DTOs to Responses/ namespace"
```

---

### Task 8: Final Quality Gates

Run the full quality suite as defined in CLAUDE.md. Fix any issues found.

**Step 1: Run full test suite with coverage**

```bash
./vendor/bin/pest --coverage --min=100
```

**Step 2: Run type coverage**

```bash
./vendor/bin/pest --type-coverage --min=100
```

**Step 3: Run Rector**

```bash
./vendor/bin/rector --dry-run
```

If Rector suggests changes, apply them and re-run tests.

**Step 4: Run PHPStan**

```bash
./vendor/bin/phpstan analyse
```

**Step 5: Run Psalm taint analysis**

```bash
./vendor/bin/psalm --taint-analysis
```

**Step 6: Run Pint**

```bash
./vendor/bin/pint -p
```

If Pint changes any files (likely import ordering), re-run the full test suite:

```bash
./vendor/bin/pest --coverage --min=100
```

**Step 7: Commit formatting fixes if any**

```bash
git add -A && git commit -m "style: fix import ordering after namespace migration"
```

---

## Final Result

After all tasks, `src/` should contain:

```
src/
  Adapters/          # Infrastructure (Http, Signing, Certificates, PrefeituraResolver)
  Builders/
    Consulta/        # Query DSL (ConsultaBuilder, NfseQueryExecutor)
    Xml/             # XML construction (DpsBuilder, Parts/)
  Contracts/         # Port interfaces (unchanged)
    Ports/
      Driven/
      Driving/
  Dps/               # DPS document model
    DTO/             # Data objects
    Enums/           # DPS-specific enums
  Enums/             # Cross-cutting enums (NfseAmbiente, TipoEvento, etc.)
  Events/            # Domain events (unchanged)
  Exceptions/        # (unchanged)
  Facades/           # (unchanged)
  Operations/        # NFS-e write operations (Emitter, Canceller, Substitutor)
  Pipeline/          # Request orchestration (NfseRequestPipeline, Concerns/)
  Responses/         # Response DTOs (NfseResponse, DanfseResponse, etc.)
  Support/           # Pure utilities (unchanged)
  NfseClient.php
  NfseNacionalServiceProvider.php
```