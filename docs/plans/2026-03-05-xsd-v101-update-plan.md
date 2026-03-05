# XSD v1.01 Schema Update — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Update PHP enums, DTOs, builders, and API to match the updated XSD v1.01 schemas.

**Architecture:** Direct synchronization — each XSD change maps to a specific PHP file change. Breaking changes accepted. TDD where new behavior is added; direct removal for deleted features.

**Tech Stack:** PHP 8.2+, Pest, PHPStan, Psalm, Rector, Pint

---

### Task 1: Update TipoRetPisCofins enum (2 → 10 cases)

**Files:**
- Modify: `src/Dps/Enums/Valores/TipoRetPisCofins.php`

**Step 1: Replace the enum with all 10 cases**

```php
<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Valores;

enum TipoRetPisCofins: string
{
    case PisCofinsCsllNaoRetidos = '0';
    case PisCofinsRetidos = '1';
    case PisCofinsNaoRetidos = '2';
    case PisCofinsCsllRetidos = '3';
    case PisCofinsRetidosCsllNaoRetido = '4';
    case PisRetidoCofinsCSLLNaoRetido = '5';
    case CofinsRetidoPisCsllNaoRetido = '6';
    case PisNaoRetidoCofinsCSLLRetidos = '7';
    case PisCofinsNaoRetidosCsllRetido = '8';
    case CofinsNaoRetidoPisCsllRetidos = '9';
}
```

**Step 2: Run tests to verify nothing breaks**

Run: `./vendor/bin/pest --parallel`
Expected: PASS (no existing test hardcodes old case names for this enum)

**Step 3: Commit**

```
feat: expand TipoRetPisCofins enum from 2 to 10 cases per XSD v1.01
```

---

### Task 2: Update TipoCST enum (10 → 33 cases)

**Files:**
- Modify: `src/Dps/Enums/Valores/TipoCST.php`

**Step 1: Replace the enum with all 33 cases**

Case `'07'` renamed: `TributavelContribuicao` → `IsentaContribuicao`.

```php
<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Valores;

enum TipoCST: string
{
    case Nenhum = '00';
    case AliqBasica = '01';
    case AliqDiferenciada = '02';
    case AliqUnidadeMedida = '03';
    case MonofasicaRevendaAliqZero = '04';
    case SubstituicaoTributaria = '05';
    case AliqZero = '06';
    case IsentaContribuicao = '07';
    case SemIncidencia = '08';
    case Suspensao = '09';
    case OutrasOperacoesSaida = '49';
    case CreditoReceitaTributadaMercInt = '50';
    case CreditoReceitaNaoTributadaMercInt = '51';
    case CreditoReceitaExportacao = '52';
    case CreditoReceitasTribNaoTribMercInt = '53';
    case CreditoReceitasTribMercIntExport = '54';
    case CreditoReceitasNaoTribMercIntExport = '55';
    case CreditoReceitasTribNaoTribMercIntExport = '56';
    case CreditoPresumidoRecTribMercInt = '60';
    case CreditoPresumidoRecNaoTribMercInt = '61';
    case CreditoPresumidoRecExportacao = '62';
    case CreditoPresumidoRecTribNaoTribMercInt = '63';
    case CreditoPresumidoRecTribMercIntExport = '64';
    case CreditoPresumidoRecNaoTribMercIntExport = '65';
    case CreditoPresumidoRecTribNaoTribMercIntExport = '66';
    case CreditoPresumidoOutras = '67';
    case AquisicaoSemCredito = '70';
    case AquisicaoIsencao = '71';
    case AquisicaoSuspensao = '72';
    case AquisicaoAliqZero = '73';
    case AquisicaoSemIncidencia = '74';
    case AquisicaoSubstituicaoTributaria = '75';
    case OutrasOperacoesEntrada = '98';
    case OutrasOperacoes = '99';
}
```

**Step 2: Run tests**

Run: `./vendor/bin/pest --parallel`
Expected: PASS

**Step 3: Commit**

```
feat: expand TipoCST enum to 33 cases, rename case 07 per XSD v1.01
```

---

### Task 3: Update TipoDedRed enum (+3 cases)

**Files:**
- Modify: `src/Dps/Enums/Valores/TipoDedRed.php`

**Step 1: Add 3 new cases**

Insert between `Materiais` and `RepasseConsorciado`:

```php
enum TipoDedRed: string
{
    case AlimentacaoBebidas = '1';
    case Materiais = '2';
    case ProducaoExterna = '3';
    case ReembolsoDespesas = '4';
    case RepasseConsorciado = '5';
    case RepassePlanoSaude = '6';
    case Servicos = '7';
    case SubempreitadaMaoDeObra = '8';
    case ProfissionalParceiro = '9';
    case OutrasDeducoes = '99';
}
```

**Step 2: Run tests**

Run: `./vendor/bin/pest --parallel`
Expected: PASS

**Step 3: Commit**

```
feat: add TipoDedRed cases 3, 4, 9 per XSD v1.01
```

---

### Task 4: Update VinculoPrestacao enum (+1 case)

**Files:**
- Modify: `src/Dps/Enums/Servico/VinculoPrestacao.php`

**Step 1: Add case `Desconhecido = '9'`**

```php
enum VinculoPrestacao: string
{
    case SemVinculo = '0';
    case Controlada = '1';
    case Controladora = '2';
    case Coligada = '3';
    case Matriz = '4';
    case FilialSucursal = '5';
    case OutroVinculo = '6';
    case Desconhecido = '9';
}
```

**Step 2: Run tests**

Run: `./vendor/bin/pest --parallel`
Expected: PASS

**Step 3: Commit**

```
feat: add VinculoPrestacao::Desconhecido case per XSD v1.01
```

---

### Task 5: Remove ExploracaoRodoviaria and LocacaoSublocacao DTOs + their enums

**Files:**
- Delete: `src/Dps/DTO/Servico/ExploracaoRodoviaria.php`
- Delete: `src/Dps/DTO/Servico/LocacaoSublocacao.php`
- Delete: `src/Dps/Enums/Servico/CategoriaVeiculo.php`
- Delete: `src/Dps/Enums/Servico/TipoRodagem.php`
- Delete: `src/Dps/Enums/Servico/CategoriaServico.php`
- Delete: `src/Dps/Enums/Servico/ObjetoLocacao.php`
- Modify: `src/Dps/DTO/Servico/Servico.php`
- Modify: `src/Xml/Builders/ServicoBuilder.php`
- Modify: `tests/Unit/Xml/ServicoBuilderTest.php`
- Modify: `tests/Unit/Xml/DpsBuilderXsdTest.php`
- Modify: `tests/Unit/DTOs/Dps/Servico/ServicoFromArrayTest.php`

**Step 1: Delete the 6 files**

```bash
rm src/Dps/DTO/Servico/ExploracaoRodoviaria.php
rm src/Dps/DTO/Servico/LocacaoSublocacao.php
rm src/Dps/Enums/Servico/CategoriaVeiculo.php
rm src/Dps/Enums/Servico/TipoRodagem.php
rm src/Dps/Enums/Servico/CategoriaServico.php
rm src/Dps/Enums/Servico/ObjetoLocacao.php
```

**Step 2: Update `Servico.php`**

Remove:
- PHPStan imports for `LocacaoSublocacaoArray` and `ExploracaoRodoviariaArray`
- `lsadppu?` and `explRod?` from `@phpstan-type ServicoArray`
- Constructor params `?LocacaoSublocacao $lsadppu = null` and `?ExploracaoRodoviaria $explRod = null`
- `fromArray()` lines for `lsadppu` and `explRod`

The updated `@phpstan-type` should be:
```
@phpstan-type ServicoArray array{cServ: CodigoServicoArray, cLocPrestacao?: string, cPaisPrestacao?: string, comExt?: ComercioExteriorArray, obra?: ObraArray, atvEvento?: AtividadeEventoArray, infoCompl?: InfoComplementarArray}
```

The constructor becomes:
```php
public function __construct(
    public CodigoServico $cServ,
    public ?string $cLocPrestacao = null,
    public ?string $cPaisPrestacao = null,
    public ?ComercioExterior $comExt = null,
    public ?Obra $obra = null,
    public ?AtividadeEvento $atvEvento = null,
    public ?InfoComplementar $infoCompl = null,
)
```

The `fromArray()` becomes:
```php
return new self(
    cServ: CodigoServico::fromArray($data['cServ']),
    cLocPrestacao: $data['cLocPrestacao'] ?? null,
    cPaisPrestacao: $data['cPaisPrestacao'] ?? null,
    comExt: isset($data['comExt']) ? ComercioExterior::fromArray($data['comExt']) : null,
    obra: isset($data['obra']) ? Obra::fromArray($data['obra']) : null,
    atvEvento: isset($data['atvEvento']) ? AtividadeEvento::fromArray($data['atvEvento']) : null,
    infoCompl: isset($data['infoCompl']) ? InfoComplementar::fromArray($data['infoCompl']) : null,
);
```

**Step 3: Update `ServicoBuilder.php`**

- Remove imports: `ExploracaoRodoviaria`, `LocacaoSublocacao`
- Remove block lines 75-83 (`// lsadppu (optional)` section)
- Remove block lines 154-165 (`// explRod (optional)` section)

**Step 4: Update tests**

In `ServicoBuilderTest.php`:
- Remove imports: `ExploracaoRodoviaria`, `LocacaoSublocacao`, `CategoriaServico`, `CategoriaVeiculo`, `ObjetoLocacao`, `TipoRodagem`
- Remove test `'builds lsadppu element'` (lines 322-345)
- Remove test `'builds explRod element'` (lines 443-472)
- In test `'builds serv element with locPrest and cServ'`, remove assertions:
  - `expect($xml)->not->toContain('<lsadppu>');`
  - `expect($xml)->not->toContain('<explRod>');`

In `DpsBuilderXsdTest.php`:
- Remove imports: `ExploracaoRodoviaria`, `LocacaoSublocacao`, `CategoriaServico`, `CategoriaVeiculo`, `ObjetoLocacao`, `TipoRodagem`
- Remove test `'validates DPS with lsadppu against XSD'` (lines 53-74)
- Remove test `'validates DPS with explRod against XSD'` (lines 159-183)

In `ServicoFromArrayTest.php`:
- Remove from `covers()`: `LocacaoSublocacao::class`, `ExploracaoRodoviaria::class`
- Remove imports: `ExploracaoRodoviaria`, `LocacaoSublocacao`
- Remove test `'LocacaoSublocacao::fromArray creates instance from array'` (lines 172-181)
- Remove test `'ExploracaoRodoviaria::fromArray creates instance from array'` (lines 194-206)

**Step 5: Run tests**

Run: `./vendor/bin/pest --parallel`
Expected: PASS

**Step 6: Commit**

```
refactor!: remove ExploracaoRodoviaria and LocacaoSublocacao per XSD v1.01

BREAKING CHANGE: TCExploracaoRodoviaria and TCLocacaoSublocacao removed from schema.
Fields `explRod` and `lsadppu` no longer accepted in Servico DTO.
```

---

### Task 6: Make CodigoServico.cNBS optional

**Files:**
- Modify: `src/Dps/DTO/Servico/CodigoServico.php`
- Modify: `src/Xml/Builders/ServicoBuilder.php`

**Step 1: Update `CodigoServico.php`**

Change `$cNBS` from required to optional. Reorder params so optional ones are at the end:

```php
/**
 * @phpstan-type CodigoServicoArray array{cTribNac: string, xDescServ: string, cNBS?: string, cTribMun?: string, cIntContrib?: string}
 */
final readonly class CodigoServico
{
    public function __construct(
        public string $cTribNac,
        public string $xDescServ,
        public ?string $cNBS = null,
        public ?string $cTribMun = null,
        public ?string $cIntContrib = null,
    ) {}

    /** @phpstan-param CodigoServicoArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
```

**Step 2: Update `ServicoBuilder.php` line 46**

Change from:
```php
$cServ->appendChild($this->text($doc, 'cNBS', $serv->cServ->cNBS));
```
To:
```php
if ($serv->cServ->cNBS !== null) {
    $cServ->appendChild($this->text($doc, 'cNBS', $serv->cServ->cNBS));
}
```

**Step 3: Run tests**

Run: `./vendor/bin/pest --parallel`
Expected: PASS (existing tests pass `cNBS` so they still work)

**Step 4: Commit**

```
feat: make CodigoServico.cNBS optional per XSD v1.01
```

---

### Task 7: Remove nPedRegEvento from CancellationBuilder

**Files:**
- Modify: `src/Xml/Builders/CancellationBuilder.php`
- Modify: `tests/Unit/Xml/CancellationBuilderTest.php`

**Step 1: Update `CancellationBuilder.php`**

1. Remove `int $nPedRegEvento = 1` param from both `buildAndValidate()` and `build()`
2. Remove `$nPedRegEvento` from the forwarded call in `buildAndValidate()`
3. Remove line 86: `$infPedReg->appendChild($this->text($doc, 'nPedRegEvento', (string) $nPedRegEvento));`
4. Change `generateId` to not take `nPedRegEvento`:
   ```php
   private function generateId(string $chNFSe): string
   {
       return 'PRE'.$chNFSe.'101101';
   }
   ```
5. Update the call to `generateId` on line 67:
   ```php
   $infPedReg->setAttribute('Id', $this->generateId($chNFSe));
   ```

**Step 2: Update `CancellationBuilderTest.php`**

1. In test `'builds valid cancelamento xml with CNPJ author'`:
   - Change ID assertion from `'PRE'.$chave.'101101001'` to `'PRE'.$chave.'101101'`
   - Remove assertion `->and($xpath->evaluate('string(//n:nPedRegEvento)'))->toBe('1')`

2. Remove test `'generates correct Id with padded nPedRegEvento'` entirely (lines 77-99)

3. In test `'validates against pedRegEvento XSD with default nPedRegEvento'`:
   - Rename to `'validates against pedRegEvento XSD'`
   - Remove assertion `->and($xpath->evaluate('string(//n:nPedRegEvento)'))->toBe('1')`
   - Change ID assertion from `'PRE'.$chave.'101101001'` to `'PRE'.$chave.'101101'`

**Step 3: Run tests**

Run: `./vendor/bin/pest --parallel`
Expected: PASS

**Step 4: Commit**

```
refactor!: remove nPedRegEvento from CancellationBuilder per XSD v1.01

BREAKING CHANGE: nPedRegEvento element removed from pedRegEvento schema.
ID pattern shortened from PRE[0-9]{59} to PRE[0-9]{56}.
```

---

### Task 8: Remove nPedRegEvento from SubstitutionBuilder + fix xDesc

**Files:**
- Modify: `src/Xml/Builders/SubstitutionBuilder.php`
- Modify: `tests/Unit/Xml/SubstitutionBuilderTest.php`

**Step 1: Update `SubstitutionBuilder.php`**

1. Remove `int $nPedRegEvento = 1` param from both `buildAndValidate()` and `build()`
2. Remove `$nPedRegEvento` from the forwarded call in `buildAndValidate()`
3. Remove line 90: `$infPedReg->appendChild($this->text($doc, 'nPedRegEvento', (string) $nPedRegEvento));`
4. Fix `xDesc` on line 93 — change:
   ```php
   $evento->appendChild($this->text($doc, 'xDesc', 'Cancelamento de NFS-e por Substituicao'));
   ```
   To:
   ```php
   $evento->appendChild($this->text($doc, 'xDesc', 'Cancelamento de NFS-e por Substituição'));
   ```
5. Change `generateId`:
   ```php
   private function generateId(string $chNFSe): string
   {
       return 'PRE'.$chNFSe.'105102';
   }
   ```
6. Update the call on line 71:
   ```php
   $infPedReg->setAttribute('Id', $this->generateId($chNFSe));
   ```

**Step 2: Update `SubstitutionBuilderTest.php`**

1. In test `'builds valid substituicao xml with chSubstituta'`:
   - Change ID assertion from `'PRE'.$chave.'105102001'` to `'PRE'.$chave.'105102'`
   - Remove assertion `->and($xpath->evaluate('string(//n:nPedRegEvento)'))->toBe('1')`
   - Change xDesc assertion from `'Cancelamento de NFS-e por Substituicao'` to `'Cancelamento de NFS-e por Substituição'`

2. Remove test `'generates correct Id with tipo 105102 and padded nPedRegEvento'` entirely (lines 73-98)

3. In test `'validates against pedRegEvento XSD with default nPedRegEvento and empty descricao'`:
   - Rename to `'validates against pedRegEvento XSD with empty descricao'`
   - Remove assertion `->and($xpath->evaluate('string(//n:nPedRegEvento)'))->toBe('1')`
   - Change ID assertion from `'PRE'.$chave.'105102001'` to `'PRE'.$chave.'105102'`

**Step 3: Run tests**

Run: `./vendor/bin/pest --parallel`
Expected: PASS

**Step 4: Commit**

```
refactor!: remove nPedRegEvento from SubstitutionBuilder, fix xDesc accent per XSD v1.01
```

---

### Task 9: Remove nPedRegEvento from API public surface

**Files:**
- Modify: `src/Contracts/Driving/CancelsNfse.php`
- Modify: `src/Contracts/Driving/SubstitutesNfse.php`
- Modify: `src/Operations/NfseCanceller.php`
- Modify: `src/Operations/NfseSubstitutor.php`
- Modify: `src/NfseClient.php`
- Modify: `src/Facades/NfseNacional.php`
- Modify: `tests/Unit/Operations/NfseCancellerTest.php`
- Modify: `tests/Unit/Operations/NfseSubstitutorTest.php`
- Modify: `tests/Feature/NfseClientSubstituirTest.php`

**Step 1: Update `CancelsNfse.php`**

```php
public function cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao): NfseResponse;
```

**Step 2: Update `SubstitutesNfse.php`**

```php
public function substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): NfseResponse;
```

**Step 3: Update `NfseCanceller.php`**

1. Remove `int $nPedRegEvento = 1` from `cancelar()` signature
2. Remove `$nPedRegEvento` from `use` clause in the closure (line 42)
3. Remove `nPedRegEvento: $nPedRegEvento,` from `buildAndValidate()` call (line 54)

**Step 4: Update `NfseSubstitutor.php`**

1. Remove `int $nPedRegEvento = 1` from `substituir()` signature
2. Remove `$nPedRegEvento` from `use` clause in the closure (line 43)
3. Remove `nPedRegEvento: $nPedRegEvento,` from `buildAndValidate()` call (line 56)

**Step 5: Update `NfseClient.php`**

1. `cancelar()` line 123: Remove `int $nPedRegEvento = 1` param and `$nPedRegEvento` from forwarded call
2. `substituir()` line 128: Remove `int $nPedRegEvento = 1` param and `$nPedRegEvento` from forwarded call

**Step 6: Update `NfseNacional.php` facade docblock**

```php
@method static NfseResponse cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao)
@method static NfseResponse substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = '')
```

**Step 7: Update `NfseCancellerTest.php`**

1. Rename test from `'cancelar uses default nPedRegEvento = 1'` to `'cancelar sends signed XML via pipeline'`
2. Remove `Http::assertSent` block checking for `nPedRegEvento` (lines 51-55)

**Step 8: Update `NfseSubstitutorTest.php`**

1. Rename test from `'substituir uses default nPedRegEvento and descricao'` to `'substituir sends signed XML via pipeline without xMotivo'`
2. In the `Http::assertSent` callback, change:
   ```php
   return str_contains($xml, '<nPedRegEvento>1</nPedRegEvento>') &&
       ! str_contains($xml, '<xMotivo>');
   ```
   To:
   ```php
   return ! str_contains($xml, '<nPedRegEvento>') &&
       ! str_contains($xml, '<xMotivo>');
   ```

**Step 9: Update `NfseClientSubstituirTest.php`**

1. In test `'substituir uses default nPedRegEvento and descricao'`:
   - Rename to `'substituir sends XML without xMotivo when descricao empty'`
   - Change `Http::assertSent` callback:
     ```php
     return ! str_contains($xml, '<nPedRegEvento>') &&
         ! str_contains($xml, '<xMotivo>');
     ```

**Step 10: Run tests**

Run: `./vendor/bin/pest --parallel`
Expected: PASS

**Step 11: Commit**

```
refactor!: remove nPedRegEvento from public API per XSD v1.01

BREAKING CHANGE: cancelar() and substituir() no longer accept nPedRegEvento parameter.
```

---

### Task 10: Update README.md for breaking changes

**Files:**
- Modify: `README.md`

**Step 1: Update documentation**

Update the `cancelar()` and `substituir()` method signatures in README to remove `nPedRegEvento`. Remove references to `explRod`, `lsadppu`, and their related enums. Update `cNBS` to show it's now optional. Document the new enum values if they appear in usage examples.

**Step 2: Commit**

```
docs: update README for XSD v1.01 breaking changes
```

---

### Task 11: Run full quality gate suite

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

If Pint or Rector changed files, re-run the full test suite:

```bash
./vendor/bin/pest --coverage --min=100 --parallel
```

**Step 3: Final commit if needed**

```
chore: fix quality gate issues from XSD v1.01 update
```
