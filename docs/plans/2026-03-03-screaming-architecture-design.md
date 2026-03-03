# Screaming Architecture — Directory Reorganization

**Date:** 2026-03-03
**Status:** Approved

## Problem

The current `src/` top-level structure screams "PHP package patterns" (DTOs, Enums, Events, Handlers, Http, Signing...) instead of communicating what the system actually does. A newcomer opening the project sees technical concerns, not domain purpose.

## Goal

Apply Uncle Bob's Screaming Architecture principle: the top-level structure should scream "NFS-e Nacional system" — making it immediately clear that this is a system for processing DPS documents through operations (emit, cancel, substitute) and querying NFS-e.

## Final Structure

```
src/
  Dps/                              # The DPS document model
    DTO/                            #   (was DTOs/Dps/*)
      Concerns/
      IBSCBS/
      InfDPS/
      Prestador/
      Servico/
      Shared/
      Tomador/
      Valores/
      DpsData.php
    Enums/                          #   (was Enums/Dps/*)
      IBSCBS/
      InfDPS/
      Prestador/
      Servico/
      Shared/
      Valores/

  Operations/                       # NFS-e write operations
    NfseEmitter.php
    NfseCanceller.php
    NfseSubstitutor.php

  Builders/                         # All builder patterns
    Consulta/                       #   Query DSL
      ConsultaBuilder.php
      NfseQueryExecutor.php
    Xml/                            #   XML construction
      DpsBuilder.php
      Parts/                        #   Sub-builders
        ValoresBuilder.php
        TomadorBuilder.php
        ServicoBuilder.php
        PrestadorBuilder.php
        IBSCBSBuilder.php
        SubstituicaoBuilder.php
        CancelamentoBuilder.php
        CreatesTextElements.php

  Pipeline/                         # Shared request orchestration
    NfseRequestPipeline.php
    Concerns/
      DispatchesEvents.php
      ValidatesChaveAcesso.php
      ParsesEventoResponse.php

  Contracts/                        # Port interfaces (unchanged)
    Ports/
      Driven/
      Driving/

  Adapters/                         # All infrastructure adapters
    NfseHttpClient.php              #   (was Http/)
    XmlSigner.php                   #   (was Signing/)
    CertificateManager.php          #   (was Certificates/)
    PrefeituraResolver.php          #   (was Services/)

  Responses/                        # Response DTOs (non-DPS)
    NfseResponse.php
    DanfseResponse.php
    EventosResponse.php
    MensagemProcessamento.php

  Events/                           # Domain events (unchanged)
  Exceptions/                       # (unchanged)
  Support/                          # Pure utilities (unchanged)
  Facades/                          # Laravel facade (unchanged)
  NfseClient.php
  NfseNacionalServiceProvider.php
```

## Namespace Mapping

| Old Namespace | New Namespace |
|---|---|
| `Pulsar\NfseNacional\DTOs\Dps\*` | `Pulsar\NfseNacional\Dps\DTO\*` |
| `Pulsar\NfseNacional\Enums\Dps\*` | `Pulsar\NfseNacional\Dps\Enums\*` |
| `Pulsar\NfseNacional\Xml\DpsBuilder` | `Pulsar\NfseNacional\Builders\Xml\DpsBuilder` |
| `Pulsar\NfseNacional\Xml\Builders\*` | `Pulsar\NfseNacional\Builders\Xml\Parts\*` |
| `Pulsar\NfseNacional\Consulta\*` | `Pulsar\NfseNacional\Builders\Consulta\*` |
| `Pulsar\NfseNacional\Handlers\NfseEmitter` | `Pulsar\NfseNacional\Operations\NfseEmitter` |
| `Pulsar\NfseNacional\Handlers\NfseCanceller` | `Pulsar\NfseNacional\Operations\NfseCanceller` |
| `Pulsar\NfseNacional\Handlers\NfseSubstitutor` | `Pulsar\NfseNacional\Operations\NfseSubstitutor` |
| `Pulsar\NfseNacional\Handlers\NfseRequestPipeline` | `Pulsar\NfseNacional\Pipeline\NfseRequestPipeline` |
| `Pulsar\NfseNacional\Handlers\Concerns\*` | `Pulsar\NfseNacional\Pipeline\Concerns\*` |
| `Pulsar\NfseNacional\Http\NfseHttpClient` | `Pulsar\NfseNacional\Adapters\NfseHttpClient` |
| `Pulsar\NfseNacional\Signing\XmlSigner` | `Pulsar\NfseNacional\Adapters\XmlSigner` |
| `Pulsar\NfseNacional\Certificates\CertificateManager` | `Pulsar\NfseNacional\Adapters\CertificateManager` |
| `Pulsar\NfseNacional\Services\PrefeituraResolver` | `Pulsar\NfseNacional\Adapters\PrefeituraResolver` |
| `Pulsar\NfseNacional\DTOs\NfseResponse` | `Pulsar\NfseNacional\Responses\NfseResponse` |
| `Pulsar\NfseNacional\DTOs\DanfseResponse` | `Pulsar\NfseNacional\Responses\DanfseResponse` |
| `Pulsar\NfseNacional\DTOs\EventosResponse` | `Pulsar\NfseNacional\Responses\EventosResponse` |
| `Pulsar\NfseNacional\DTOs\MensagemProcessamento` | `Pulsar\NfseNacional\Responses\MensagemProcessamento` |

## Folders Removed (Empty After Move)

- `DTOs/` (contents split to `Dps/DTO/` and `Responses/`)
- `Enums/` (moved to `Dps/Enums/`)
- `Xml/` (moved to `Builders/Xml/`)
- `Handlers/` (split to `Operations/` and `Pipeline/`)
- `Http/` (moved to `Adapters/`)
- `Signing/` (moved to `Adapters/`)
- `Certificates/` (moved to `Adapters/`)
- `Services/` (moved to `Adapters/`)
- `Consulta/` (moved to `Builders/Consulta/`)

## Folders Unchanged

- `Contracts/` (port interfaces — already domain-clean)
- `Events/` (domain events)
- `Exceptions/`
- `Support/` (pure utilities)
- `Facades/`

## Design Decisions

1. **`Dps/DTO/`** — The DPS is a single XML document model used by multiple operations. Keeping its DTOs and Enums together is cohesive.
2. **`Operations/`** — Groups the 3 thin handler classes (Emitter, Canceller, Substitutor) that each have a single file. Avoids 3 single-file folders.
3. **`Builders/`** — Groups both ConsultaBuilder (query DSL) and XML builders under one roof. `Parts/` avoids nested `Builders/Builders/`.
4. **`Pipeline/`** — Kept separate from Operations because it's shared orchestration infrastructure, not a use case itself.
5. **`Adapters/`** — All infrastructure implementations in one flat folder. They were spread across 4 separate folders (Http, Signing, Certificates, Services).
6. **`Responses/`** — Response DTOs that aren't part of the DPS model. More domain-oriented than `DTOs/`.
7. **English names throughout** — Consistency over mixing Portuguese domain terms with English technical terms.

## Impact

- All source files under `src/` need namespace updates
- All test files need import updates
- `composer.json` autoload PSR-4 mapping stays the same (root `Pulsar\\NfseNacional\\` → `src/`)
- Architecture tests in `ArchTest.php` need rule updates for new namespaces
- Service provider bindings need updated class references