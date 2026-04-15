# DANFSE Local Rendering — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Renderizar o PDF do DANFSE localmente a partir do XML da NFS-e autorizada, via `$client->danfe($config)->toPdf($xml)` / `->toHtml($xml)`, substituindo a dependência do endpoint ADN que está fora do ar.

**Architecture:** Fork interno de `andrevabo/danfse-nacional` (MIT), adaptado ao padrão hexagonal do repo. Entry point único `NfsenClient::danfe()` constrói um `NfseDanfseRenderer` com 4 driven adapters (`DanfseDataBuilder` → `DanfseHtmlRenderer` → `DompdfHtmlToPdfConverter` + `BaconQrCodeGenerator`). `NfseData` DTO achatado, sem `cuyz/valinor`.

**Tech Stack:** PHP 8.3+, Pest 4, dompdf/dompdf ^3.0, bacon/bacon-qr-code ^3.0, SimpleXMLElement. Dev dep adicional: smalot/pdfparser para integração.

**Reference:** Spec em `docs/plans/2026-04-14-danfse-local-rendering-design.md`. Lib de referência clonada em `/tmp/danfse-nacional-ref-*/` (fork interno — usa como fonte para template, Formatter, Municipios, e XML de exemplo).

---

## Pré-requisitos

- Lib de referência clonada em `/tmp/danfse-nacional-ref-*/`. Se não existir, rodar:
  ```bash
  cd /tmp && git clone --depth 1 https://github.com/andrevabo/danfse-nacional.git danfse-nacional-ref-$(date +%s)
  ```
- `REF` ao longo do plano refere-se a esse diretório. Resolva com:
  ```bash
  REF=$(ls -d /tmp/danfse-nacional-ref-* | head -1)
  ```

---

## Task 1: Adicionar dependências

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Adicionar runtime deps**

Adicionar em `"require"`:

```json
"dompdf/dompdf": "^3.0",
"bacon/bacon-qr-code": "^3.0"
```

- [ ] **Step 2: Adicionar dev dep para testes de integração**

Adicionar em `"require-dev"`:

```json
"smalot/pdfparser": "^2.10"
```

- [ ] **Step 3: Instalar**

Run: `composer update dompdf/dompdf bacon/bacon-qr-code smalot/pdfparser`
Expected: 3 pacotes instalados sem conflitos.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add dompdf, bacon-qr-code and smalot/pdfparser for DANFSE"
```

**Note:** `composer.lock` é gitignored (ver CLAUDE.md). Se o `git add composer.lock` não adicionar nada, seguir com só `composer.json`.

---

## Task 2: Estender enum `OpSimpNac` com `label()` e `labelOf()`

**Files:**
- Modify: `src/Dps/Enums/Prest/OpSimpNac.php`
- Test: `tests/Unit/Dps/Enums/Prest/OpSimpNacLabelTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Dps/Enums/Prest/OpSimpNacLabelTest.php`:

```php
<?php

use OwnerPro\Nfsen\Dps\Enums\Prest\OpSimpNac;

covers(OpSimpNac::class);

it('returns label for each case', function () {
    expect(OpSimpNac::NaoOptante->label())->toBe('Não Optante');
    expect(OpSimpNac::OptanteMEI->label())->toBe('Optante - Microempreendedor Individual (MEI)');
    expect(OpSimpNac::OptanteMEEPP->label())->toBe('Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)');
});

it('labelOf returns label for valid string value', function () {
    expect(OpSimpNac::labelOf('1'))->toBe('Não Optante');
    expect(OpSimpNac::labelOf('2'))->toBe('Optante - Microempreendedor Individual (MEI)');
    expect(OpSimpNac::labelOf('3'))->toBe('Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)');
});

it('labelOf returns dash for null', function () {
    expect(OpSimpNac::labelOf(null))->toBe('-');
});

it('labelOf returns dash for unknown value', function () {
    expect(OpSimpNac::labelOf('99'))->toBe('-');
    expect(OpSimpNac::labelOf(''))->toBe('-');
});
```

- [ ] **Step 2: Rodar o teste para verificar falha**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Prest/OpSimpNacLabelTest.php`
Expected: FAIL (`label` e `labelOf` não existem).

- [ ] **Step 3: Implementar**

Editar `src/Dps/Enums/Prest/OpSimpNac.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Prest;

enum OpSimpNac: string
{
    case NaoOptante = '1';
    case OptanteMEI = '2';
    case OptanteMEEPP = '3';

    public function label(): string
    {
        return match ($this) {
            self::NaoOptante => 'Não Optante',
            self::OptanteMEI => 'Optante - Microempreendedor Individual (MEI)',
            self::OptanteMEEPP => 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)',
        };
    }

    public static function labelOf(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? '-';
    }
}
```

- [ ] **Step 4: Rodar o teste para verificar sucesso**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Prest/OpSimpNacLabelTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Dps/Enums/Prest/OpSimpNac.php tests/Unit/Dps/Enums/Prest/OpSimpNacLabelTest.php
git commit -m "feat(danfse): add label() and labelOf() to OpSimpNac"
```

---

## Task 3: Estender enum `RegApTribSN` com `label()` e `labelOf()`

**Files:**
- Modify: `src/Dps/Enums/Prest/RegApTribSN.php`
- Test: `tests/Unit/Dps/Enums/Prest/RegApTribSNLabelTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Dps/Enums/Prest/RegApTribSNLabelTest.php`:

```php
<?php

use OwnerPro\Nfsen\Dps\Enums\Prest\RegApTribSN;

covers(RegApTribSN::class);

it('returns label for each case', function () {
    foreach (RegApTribSN::cases() as $case) {
        expect($case->label())->not->toBe('');
    }
});

it('labelOf SN Federal e Municipal', function () {
    expect(RegApTribSN::labelOf('1'))->toBe(
        'Regime de apuração dos tributos federais e municipal pelo Simples Nacional'
    );
});

it('labelOf SN Federal, ISSQN pela NFSe', function () {
    expect(RegApTribSN::labelOf('2'))->toBe(
        'Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo'
    );
});

it('labelOf NFSe federal e municipal', function () {
    expect(RegApTribSN::labelOf('3'))->toBe(
        'Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo'
    );
});

it('labelOf returns dash for null/unknown', function () {
    expect(RegApTribSN::labelOf(null))->toBe('-');
    expect(RegApTribSN::labelOf('99'))->toBe('-');
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Prest/RegApTribSNLabelTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

Cases atuais do enum (verificados em `src/Dps/Enums/Prest/RegApTribSN.php:7-12`): `ApuracaoSN = '1'`, `ApuracaoSNIssqnFora = '2'`, `ApuracaoForaSN = '3'`.

Adicionar ao final do enum (dentro da chave, preservando os 3 cases existentes):

```php
public function label(): string
{
    return match ($this) {
        self::ApuracaoSN => 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
        self::ApuracaoSNIssqnFora => 'Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo',
        self::ApuracaoForaSN => 'Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo',
    };
}

public static function labelOf(?string $value): string
{
    return self::tryFrom((string) $value)?->label() ?? '-';
}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Prest/RegApTribSNLabelTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Dps/Enums/Prest/RegApTribSN.php tests/Unit/Dps/Enums/Prest/RegApTribSNLabelTest.php
git commit -m "feat(danfse): add label() and labelOf() to RegApTribSN"
```

---

## Task 4: Estender enum `RegEspTrib` com `label()` e `labelOf()`

**Files:**
- Modify: `src/Dps/Enums/Prest/RegEspTrib.php`
- Test: `tests/Unit/Dps/Enums/Prest/RegEspTribLabelTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Dps/Enums/Prest/RegEspTribLabelTest.php`:

```php
<?php

use OwnerPro\Nfsen\Dps\Enums\Prest\RegEspTrib;

covers(RegEspTrib::class);

it('labelOf returns expected labels', function () {
    expect(RegEspTrib::labelOf('0'))->toBe('Nenhum');
    expect(RegEspTrib::labelOf('1'))->toBe('Ato Cooperado (Cooperativa)');
    expect(RegEspTrib::labelOf('2'))->toBe('Estimativa');
    expect(RegEspTrib::labelOf('3'))->toBe('Microempresa Municipal');
    expect(RegEspTrib::labelOf('4'))->toBe('Notário ou Registrador');
    expect(RegEspTrib::labelOf('5'))->toBe('Profissional Autônomo');
    expect(RegEspTrib::labelOf('6'))->toBe('Sociedade de Profissionais');
    expect(RegEspTrib::labelOf('9'))->toBe('Outros');
});

it('labelOf returns dash for null/unknown', function () {
    expect(RegEspTrib::labelOf(null))->toBe('-');
    expect(RegEspTrib::labelOf('99'))->toBe('-');
});

it('each case has a non-empty label', function () {
    foreach (RegEspTrib::cases() as $case) {
        expect($case->label())->not->toBe('');
    }
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Prest/RegEspTribLabelTest.php`
Expected: FAIL.

- [ ] **Step 3: Adicionar métodos**

Cases atuais (verificados em `src/Dps/Enums/Prest/RegEspTrib.php:9-16`): `Nenhum='0'`, `AtoCooperado='1'`, `Estimativa='2'`, `MicroempresaMunicipal='3'`, `NotarioRegistrador='4'`, `ProfissionalAutonomo='5'`, `SociedadeProfissionais='6'`, `Outros='9'` (8 cases — `'9'` não está na lib original mas existe neste SDK).

Adicionar ao final do enum (dentro da chave, preservando os cases):

```php
public function label(): string
{
    return match ($this) {
        self::Nenhum => 'Nenhum',
        self::AtoCooperado => 'Ato Cooperado (Cooperativa)',
        self::Estimativa => 'Estimativa',
        self::MicroempresaMunicipal => 'Microempresa Municipal',
        self::NotarioRegistrador => 'Notário ou Registrador',
        self::ProfissionalAutonomo => 'Profissional Autônomo',
        self::SociedadeProfissionais => 'Sociedade de Profissionais',
        self::Outros => 'Outros',
    };
}

public static function labelOf(?string $value): string
{
    return self::tryFrom((string) $value)?->label() ?? '-';
}
```

**Uso de `match ($this)`** (em vez de `$this->value`) — exaustividade verificada em compile-time; compiler erra se cases forem adicionados sem atualizar o match.

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Prest/RegEspTribLabelTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Dps/Enums/Prest/RegEspTrib.php tests/Unit/Dps/Enums/Prest/RegEspTribLabelTest.php
git commit -m "feat(danfse): add label() and labelOf() to RegEspTrib"
```

---

## Task 5: Estender enum `TpRetISSQN` com `label()` e `labelOf()`

**Files:**
- Modify: `src/Dps/Enums/Valores/TpRetISSQN.php`
- Test: `tests/Unit/Dps/Enums/Valores/TpRetISSQNLabelTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Dps/Enums/Valores/TpRetISSQNLabelTest.php`:

```php
<?php

use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;

covers(TpRetISSQN::class);

it('labelOf returns expected labels', function () {
    expect(TpRetISSQN::labelOf('1'))->toBe('Não Retido');
    expect(TpRetISSQN::labelOf('2'))->toBe('Retido pelo Tomador');
    expect(TpRetISSQN::labelOf('3'))->toBe('Retido pelo Intermediário');
});

it('labelOf returns dash for null/unknown', function () {
    expect(TpRetISSQN::labelOf(null))->toBe('-');
    expect(TpRetISSQN::labelOf('99'))->toBe('-');
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Valores/TpRetISSQNLabelTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

Cases atuais (verificados em `src/Dps/Enums/Valores/TpRetISSQN.php:9-11`): `NaoRetido='1'`, `RetidoPeloTomador='2'`, `RetidoPeloIntermediario='3'`.

Adicionar em `src/Dps/Enums/Valores/TpRetISSQN.php` (dentro do enum):

```php
public function label(): string
{
    return match ($this) {
        self::NaoRetido => 'Não Retido',
        self::RetidoPeloTomador => 'Retido pelo Tomador',
        self::RetidoPeloIntermediario => 'Retido pelo Intermediário',
    };
}

public static function labelOf(?string $value): string
{
    return self::tryFrom((string) $value)?->label() ?? '-';
}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Valores/TpRetISSQNLabelTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Dps/Enums/Valores/TpRetISSQN.php tests/Unit/Dps/Enums/Valores/TpRetISSQNLabelTest.php
git commit -m "feat(danfse): add label() and labelOf() to TpRetISSQN"
```

---

## Task 6: Estender enum `TribISSQN` com `label()` e `labelOf()`

**Files:**
- Modify: `src/Dps/Enums/Valores/TribISSQN.php`
- Test: `tests/Unit/Dps/Enums/Valores/TribISSQNLabelTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Dps/Enums/Valores/TribISSQNLabelTest.php`:

```php
<?php

use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;

covers(TribISSQN::class);

it('labelOf returns expected labels', function () {
    expect(TribISSQN::labelOf('1'))->toBe('Operação Tributável');
    expect(TribISSQN::labelOf('2'))->toBe('Imunidade');
    expect(TribISSQN::labelOf('3'))->toBe('Exportação de Serviço');
    expect(TribISSQN::labelOf('4'))->toBe('Não Incidência');
});

it('labelOf returns dash for null/unknown', function () {
    expect(TribISSQN::labelOf(null))->toBe('-');
    expect(TribISSQN::labelOf('99'))->toBe('-');
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Valores/TribISSQNLabelTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

Cases atuais (verificados em `src/Dps/Enums/Valores/TribISSQN.php:9-12`): `Tributavel='1'`, `Imunidade='2'`, `ExportacaoServico='3'`, `NaoIncidencia='4'`.

Adicionar ao enum:

```php
public function label(): string
{
    return match ($this) {
        self::Tributavel => 'Operação Tributável',
        self::Imunidade => 'Imunidade',
        self::ExportacaoServico => 'Exportação de Serviço',
        self::NaoIncidencia => 'Não Incidência',
    };
}

public static function labelOf(?string $value): string
{
    return self::tryFrom((string) $value)?->label() ?? '-';
}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Dps/Enums/Valores/TribISSQNLabelTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Dps/Enums/Valores/TribISSQN.php tests/Unit/Dps/Enums/Valores/TribISSQNLabelTest.php
git commit -m "feat(danfse): add label() and labelOf() to TribISSQN"
```

---

## Task 7: Estender enum `NfseAmbiente` com `label()`

**Files:**
- Modify: `src/Enums/NfseAmbiente.php`
- Modify: `tests/Unit/Enums/NfseAmbienteTest.php`

- [ ] **Step 1: Adicionar testes falhantes**

Adicionar ao final de `tests/Unit/Enums/NfseAmbienteTest.php`:

```php
it('label for PRODUCAO', function () {
    expect(NfseAmbiente::PRODUCAO->label())->toBe('Produção');
});

it('label for HOMOLOGACAO', function () {
    expect(NfseAmbiente::HOMOLOGACAO->label())->toBe('Homologação');
});

it('isHomologacao is true only for HOMOLOGACAO', function () {
    expect(NfseAmbiente::HOMOLOGACAO->isHomologacao())->toBeTrue();
    expect(NfseAmbiente::PRODUCAO->isHomologacao())->toBeFalse();
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Enums/NfseAmbienteTest.php`
Expected: FAIL (métodos não existem).

- [ ] **Step 3: Implementar**

Adicionar ao final do enum `NfseAmbiente` (em `src/Enums/NfseAmbiente.php`), antes do `}` final:

```php
public function label(): string
{
    return match ($this) {
        self::PRODUCAO => 'Produção',
        self::HOMOLOGACAO => 'Homologação',
    };
}

public function isHomologacao(): bool
{
    return $this === self::HOMOLOGACAO;
}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Enums/NfseAmbienteTest.php`
Expected: PASS (todos os casos antigos e novos).

- [ ] **Step 5: Commit**

```bash
git add src/Enums/NfseAmbiente.php tests/Unit/Enums/NfseAmbienteTest.php
git commit -m "feat(danfse): add label() and isHomologacao() to NfseAmbiente"
```

---

## Task 8: Criar `XmlParseException`

**Files:**
- Create: `src/Exceptions/XmlParseException.php`
- Test: `tests/Unit/Exceptions/XmlParseExceptionTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Exceptions/XmlParseExceptionTest.php`:

```php
<?php

use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Exceptions\XmlParseException;

covers(XmlParseException::class);

it('extends NfseException', function () {
    expect(new XmlParseException('boom'))->toBeInstanceOf(NfseException::class);
});

it('preserves message and previous', function () {
    $prev = new RuntimeException('cause');
    $ex = new XmlParseException('boom', previous: $prev);

    expect($ex->getMessage())->toBe('boom');
    expect($ex->getPrevious())->toBe($prev);
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Exceptions/XmlParseExceptionTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

Criar `src/Exceptions/XmlParseException.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Exceptions;

final class XmlParseException extends NfseException {}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Exceptions/XmlParseExceptionTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Exceptions/XmlParseException.php tests/Unit/Exceptions/XmlParseExceptionTest.php
git commit -m "feat(danfse): add XmlParseException"
```

---

## Task 9: Criar `Formatter`

**Files:**
- Create: `src/Danfse/Formatter.php`
- Test: `tests/Unit/Danfse/FormatterTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Danfse/FormatterTest.php`:

```php
<?php

use OwnerPro\Nfsen\Danfse\Formatter;

covers(Formatter::class);

beforeEach(function () {
    $this->fmt = new Formatter();
});

it('cnpjCpf formats 14-digit CNPJ', function () {
    expect($this->fmt->cnpjCpf('11222333000181'))->toBe('11.222.333/0001-81');
});

it('cnpjCpf formats 11-digit CPF', function () {
    expect($this->fmt->cnpjCpf('12345678901'))->toBe('123.456.789-01');
});

it('cnpjCpf returns dash for empty or dash', function () {
    expect($this->fmt->cnpjCpf(''))->toBe('-');
    expect($this->fmt->cnpjCpf('-'))->toBe('-');
});

it('cnpjCpf returns input when length is not 11 or 14', function () {
    expect($this->fmt->cnpjCpf('123'))->toBe('123');
});

it('phone formats 11 digits', function () {
    expect($this->fmt->phone('11987654321'))->toBe('(11) 98765-4321');
});

it('phone formats 10 digits', function () {
    expect($this->fmt->phone('1133334444'))->toBe('(11) 3333-4444');
});

it('phone returns dash for empty or dash', function () {
    expect($this->fmt->phone(''))->toBe('-');
    expect($this->fmt->phone('-'))->toBe('-');
});

it('phone returns input when length differs', function () {
    expect($this->fmt->phone('123'))->toBe('123');
});

it('cep formats 8 digits', function () {
    expect($this->fmt->cep('01310100'))->toBe('01310-100');
});

it('cep returns dash for empty or dash', function () {
    expect($this->fmt->cep(''))->toBe('-');
    expect($this->fmt->cep('-'))->toBe('-');
});

it('cep returns input when length differs', function () {
    expect($this->fmt->cep('123'))->toBe('123');
});

it('date formats ISO to BR', function () {
    expect($this->fmt->date('2026-01-15'))->toBe('15/01/2026');
});

it('date returns dash for empty or dash', function () {
    expect($this->fmt->date(''))->toBe('-');
    expect($this->fmt->date('-'))->toBe('-');
});

it('date returns input when invalid', function () {
    expect($this->fmt->date('not-a-date'))->toBe('not-a-date');
});

it('dateTime formats ISO to BR', function () {
    expect($this->fmt->dateTime('2026-01-15T14:30:00-03:00'))->toBe('15/01/2026 14:30:00');
});

it('dateTime returns dash for empty or dash', function () {
    expect($this->fmt->dateTime(''))->toBe('-');
});

it('dateTime returns input when invalid', function () {
    expect($this->fmt->dateTime('not-a-date'))->toBe('not-a-date');
});

it('currency formats float', function () {
    expect($this->fmt->currency(1500.5))->toBe('R$ 1.500,50');
});

it('currency formats string', function () {
    expect($this->fmt->currency('1292.75'))->toBe('R$ 1.292,75');
});

it('currency returns dash for empty or dash', function () {
    expect($this->fmt->currency(''))->toBe('-');
    expect($this->fmt->currency('-'))->toBe('-');
});

it('currency formats zero', function () {
    expect($this->fmt->currency('0'))->toBe('R$ 0,00');
});

it('codTribNacional formats 6-digit code', function () {
    expect($this->fmt->codTribNacional('010700'))->toBe('01.07.00');
});

it('codTribNacional returns dash for empty or dash', function () {
    expect($this->fmt->codTribNacional(''))->toBe('-');
    expect($this->fmt->codTribNacional('-'))->toBe('-');
});

it('codTribNacional returns input when length differs', function () {
    expect($this->fmt->codTribNacional('1'))->toBe('1');
});

it('limit truncates long strings', function () {
    expect($this->fmt->limit('abcdefghij', 5))->toBe('abcde...');
});

it('limit preserves short strings', function () {
    expect($this->fmt->limit('abc', 5))->toBe('abc');
});

it('limit respects custom suffix', function () {
    expect($this->fmt->limit('abcdefghij', 5, '>>'))->toBe('abcde>>');
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Danfse/FormatterTest.php`
Expected: FAIL (classe não existe).

- [ ] **Step 3: Implementar**

Criar `src/Danfse/Formatter.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use DateTimeImmutable;
use Exception;

/**
 * Formatadores para padrões brasileiros (CNPJ, CPF, telefone, CEP, moeda, datas).
 *
 * Portado de andrevabo/danfse-nacional (https://github.com/andrevabo/danfse-nacional) — MIT.
 *
 * @api
 */
final class Formatter
{
    public function cnpjCpf(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        if (strlen($digits) === 14) {
            return (string) preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
        }

        if (strlen($digits) === 11) {
            return (string) preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }

        return $digits;
    }

    public function phone(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        if (strlen($digits) === 11) {
            return (string) preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
        }

        if (strlen($digits) === 10) {
            return (string) preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits);
        }

        return $digits;
    }

    public function cep(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        if (strlen($digits) === 8) {
            return (string) preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits);
        }

        return $digits;
    }

    public function date(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        try {
            return (new DateTimeImmutable($value))->format('d/m/Y');
        } catch (Exception) {
            return $value;
        }
    }

    public function dateTime(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        try {
            return (new DateTimeImmutable($value))->format('d/m/Y H:i:s');
        } catch (Exception) {
            return $value;
        }
    }

    public function currency(string|float $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    public function codTribNacional(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        if (strlen($digits) === 6) {
            return (string) preg_replace('/(\d{2})(\d{2})(\d{2})/', '$1.$2.$3', $digits);
        }

        return $digits;
    }

    public function limit(string $value, int $limit, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit).$end;
    }
}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Danfse/FormatterTest.php`
Expected: PASS (todos os 27 testes).

- [ ] **Step 5: Commit**

```bash
git add src/Danfse/Formatter.php tests/Unit/Danfse/FormatterTest.php
git commit -m "feat(danfse): add Formatter for BR formatting masks"
```

---

## Task 10: Portar tabela IBGE como JSON + criar `Municipios`

**Files:**
- Create: `storage/ibge-municipios.json`
- Create: `src/Danfse/Municipios.php`
- Test: `tests/Unit/Danfse/MunicipiosTest.php`

- [ ] **Step 1: Gerar o JSON a partir da lib de referência**

Shape do `MAP` verificado: `array<int, array{nome:string, uf:string}>` (ex.: `5200050 => ['nome' => 'Abadia de Goiás', 'uf' => 'GO']`).

```bash
REF=$(ls -d /tmp/danfse-nacional-ref-* | head -1)
php -r '
require "'$REF'/src/Data/Municipios.php";
$r = new ReflectionClass(DanfseNacional\Data\Municipios::class);
$map = $r->getReflectionConstant("MAP")->getValue();

// Sanity check do shape
$first = $map[array_key_first($map)];
if (! isset($first["nome"], $first["uf"])) {
    fwrite(STDERR, "ERRO: shape do MAP divergiu. Primeiro item: " . var_export($first, true) . "\n");
    exit(1);
}

file_put_contents("storage/ibge-municipios.json", json_encode($map, JSON_UNESCAPED_UNICODE));
echo "Wrote ", count($map), " entries\n";
'
```
Expected: `Wrote 5571 entries` (sem mensagem de ERRO).

- [ ] **Step 2: Criar teste falhante**

Criar `tests/Unit/Danfse/MunicipiosTest.php`:

```php
<?php

use OwnerPro\Nfsen\Danfse\Municipios;

covers(Municipios::class);

it('looks up São Paulo by IBGE code', function () {
    expect(Municipios::lookup(3550308))->toBe('São Paulo - SP');
});

it('looks up Niterói by IBGE code', function () {
    expect(Municipios::lookup(3303302))->toBe('Niterói - RJ');
});

it('accepts string IBGE code', function () {
    expect(Municipios::lookup('4304606'))->toContain(' - RS');
});

it('returns dash for unknown code', function () {
    expect(Municipios::lookup(0))->toBe('-');
    expect(Municipios::lookup('9999999'))->toBe('-');
});

it('caches JSON after first call', function () {
    $first = Municipios::lookup(3550308);
    $second = Municipios::lookup(3550308);
    expect($second)->toBe($first);
});
```

- [ ] **Step 3: Rodar**

Run: `./vendor/bin/pest tests/Unit/Danfse/MunicipiosTest.php`
Expected: FAIL.

- [ ] **Step 4: Implementar**

Criar `src/Danfse/Municipios.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

/**
 * Lookup IBGE → "Nome - UF".
 *
 * Dados de storage/ibge-municipios.json (origem: kelvins/municipios-brasileiros, MIT).
 *
 * @internal Tabela estática carregada uma vez por processo.
 */
final class Municipios
{
    /** @var array<int,array{nome:string,uf:string}>|null */
    private static ?array $map = null;

    public static function lookup(string|int $cMun): string
    {
        self::$map ??= self::load();

        $entry = self::$map[(int) $cMun] ?? null;

        return $entry !== null ? $entry['nome'].' - '.$entry['uf'] : '-';
    }

    /** @return array<int,array{nome:string,uf:string}> */
    private static function load(): array
    {
        $path = __DIR__.'/../../storage/ibge-municipios.json';
        $json = (string) file_get_contents($path);

        /** @var array<int,array{nome:string,uf:string}> $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
```

- [ ] **Step 5: Rodar**

Run: `./vendor/bin/pest tests/Unit/Danfse/MunicipiosTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Danfse/Municipios.php storage/ibge-municipios.json tests/Unit/Danfse/MunicipiosTest.php
git commit -m "feat(danfse): add IBGE city lookup via JSON"
```

---

## Task 11: Criar sub-DTOs de `NfseData`

**Files:**
- Create: `src/Danfse/Data/DanfseParte.php`
- Create: `src/Danfse/Data/DanfseServico.php`
- Create: `src/Danfse/Data/DanfseTributacaoMunicipal.php`
- Create: `src/Danfse/Data/DanfseTributacaoFederal.php`
- Create: `src/Danfse/Data/DanfseTotais.php`
- Create: `src/Danfse/Data/DanfseTotaisTributos.php`
- Create: `src/Danfse/NfseData.php`

Esses são DTOs readonly sem lógica. Testados indiretamente via `DanfseDataBuilder`. Não precisam de teste próprio (seriam testes de construtor — violam "não teste o framework").

- [ ] **Step 1: Criar `DanfseParte`**

Criar `src/Danfse/Data/DanfseParte.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * DTO permissivo para emitente, tomador ou intermediário do DANFSE.
 * Todos os campos são strings já formatadas (ou '-' para ausentes).
 */
final readonly class DanfseParte
{
    public function __construct(
        public string $nome,
        public string $cnpjCpf,
        public string $im,
        public string $telefone,
        public string $email,
        public string $endereco,
        public string $municipio,
        public string $cep,
        public string $simplesNacional = '-',
        public string $regimeSN = '-',
    ) {}
}
```

- [ ] **Step 2: Criar `DanfseServico`**

Criar `src/Danfse/Data/DanfseServico.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

final readonly class DanfseServico
{
    public function __construct(
        public string $codigoTribNacional,
        public string $descTribNacional,
        public string $codigoTribMunicipal,
        public string $descTribMunicipal,
        public string $localPrestacao,
        public string $paisPrestacao,
        public string $descricao,
    ) {}
}
```

- [ ] **Step 3: Criar `DanfseTributacaoMunicipal`**

Criar `src/Danfse/Data/DanfseTributacaoMunicipal.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

final readonly class DanfseTributacaoMunicipal
{
    public function __construct(
        public string $tributacaoIssqn,
        public string $municipioIncidencia,
        public string $regimeEspecial,
        public string $valorServico,
        public string $bcIssqn,
        public string $aliquota,
        public string $retencaoIssqn,
        public string $issqnApurado,
    ) {}
}
```

- [ ] **Step 4: Criar `DanfseTributacaoFederal`**

Criar `src/Danfse/Data/DanfseTributacaoFederal.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

final readonly class DanfseTributacaoFederal
{
    public function __construct(
        public string $irrf,
        public string $cp,
        public string $csll,
        public string $pis,
        public string $cofins,
    ) {}
}
```

- [ ] **Step 5: Criar `DanfseTotais`**

Criar `src/Danfse/Data/DanfseTotais.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

final readonly class DanfseTotais
{
    public function __construct(
        public string $valorServico,
        public string $descontoCondicionado,
        public string $descontoIncondicionado,
        public string $issqnRetido,
        public string $retencoesFederais,
        public string $pisCofins,
        public string $valorLiquido,
    ) {}
}
```

- [ ] **Step 6: Criar `DanfseTotaisTributos`**

Criar `src/Danfse/Data/DanfseTotaisTributos.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

final readonly class DanfseTotaisTributos
{
    public function __construct(
        public string $federais,
        public string $estaduais,
        public string $municipais,
    ) {}
}
```

- [ ] **Step 7: Criar `NfseData`**

Criar `src/Danfse/NfseData.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use OwnerPro\Nfsen\Danfse\Data\DanfseParte;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Enums\NfseAmbiente;

/**
 * DTO "display-ready" para o template do DANFSE.
 *
 * Todos os campos são strings já formatadas (ou '-' para ausentes).
 * Sub-DTOs agrupam campos por bloco visual do PDF.
 *
 * @api
 */
final readonly class NfseData
{
    public function __construct(
        public string $chaveAcesso,
        public string $numeroNfse,
        public string $competencia,
        public string $emissaoNfse,
        public string $numeroDps,
        public string $serieDps,
        public string $emissaoDps,
        public NfseAmbiente $ambiente,
        public DanfseParte $emitente,
        public DanfseParte $tomador,
        public ?DanfseParte $intermediario,
        public DanfseServico $servico,
        public DanfseTributacaoMunicipal $tribMun,
        public DanfseTributacaoFederal $tribFed,
        public DanfseTotais $totais,
        public DanfseTotaisTributos $totaisTributos,
        public string $informacoesComplementares,
    ) {}
}
```

- [ ] **Step 8: Rodar tipo + análise estática para validar**

Run: `./vendor/bin/phpstan analyse src/Danfse`
Expected: no errors.

Run: `./vendor/bin/pest --type-coverage --min=100 src/Danfse`
Expected: 100% type coverage.

- [ ] **Step 9: Commit**

```bash
git add src/Danfse/
git commit -m "feat(danfse): add NfseData display DTO and sub-DTOs"
```

---

## Task 12: Criar `MunicipalityBranding`, `DanfseConfig` e copiar asset do logo

**Files:**
- Create: `src/Danfse/MunicipalityBranding.php`
- Create: `src/Danfse/DanfseConfig.php`
- Create: `storage/danfse/logo-nfse.png` (copiado da lib)
- Test: `tests/Unit/Danfse/MunicipalityBrandingTest.php`
- Test: `tests/Unit/Danfse/DanfseConfigTest.php`

- [ ] **Step 1: Copiar logo default da lib**

```bash
REF=$(ls -d /tmp/danfse-nacional-ref-* | head -1)
mkdir -p storage/danfse
cp $REF/assets/logo-nfse.png storage/danfse/logo-nfse.png
```

- [ ] **Step 2: Criar teste de `MunicipalityBranding`**

Criar `tests/Unit/Danfse/MunicipalityBrandingTest.php`:

```php
<?php

use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

covers(MunicipalityBranding::class);

it('accepts only name', function () {
    $b = new MunicipalityBranding(name: 'Prefeitura X');
    expect($b->name)->toBe('Prefeitura X');
    expect($b->department)->toBe('');
    expect($b->email)->toBe('');
    expect($b->logoDataUri)->toBeNull();
});

it('reads logo from path', function () {
    $path = __DIR__.'/../../fixtures/danfse/tiny-logo.png';

    $b = new MunicipalityBranding(name: 'X', logoPath: $path);

    expect($b->logoDataUri)->toStartWith('data:image/');
    expect($b->logoDataUri)->toContain('base64,');
});

it('prefers logoDataUri over logoPath', function () {
    $path = __DIR__.'/../../fixtures/danfse/tiny-logo.png';

    $b = new MunicipalityBranding(
        name: 'X',
        logoDataUri: 'data:image/png;base64,DIRECT',
        logoPath: $path,
    );

    expect($b->logoDataUri)->toBe('data:image/png;base64,DIRECT');
});

it('throws for missing logo file', function () {
    expect(fn () => new MunicipalityBranding(name: 'X', logoPath: '/nope/missing.png'))
        ->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 3: Criar fixture tiny PNG**

```bash
mkdir -p tests/fixtures/danfse
php -r 'file_put_contents("tests/fixtures/danfse/tiny-logo.png", base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="));'
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Danfse/MunicipalityBrandingTest.php`
Expected: FAIL.

- [ ] **Step 5: Implementar `MunicipalityBranding`**

Criar `src/Danfse/MunicipalityBranding.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use InvalidArgumentException;
use RuntimeException;

/**
 * Identificação do município emissor no cabeçalho do DANFSE.
 *
 * Portado de andrevabo/danfse-nacional (MIT).
 *
 * @api
 */
final readonly class MunicipalityBranding
{
    public ?string $logoDataUri;

    public function __construct(
        public string $name,
        public string $department = '',
        public string $email = '',
        ?string $logoDataUri = null,
        ?string $logoPath = null,
    ) {
        $this->logoDataUri = $logoDataUri
            ?? ($logoPath !== null ? self::pathToDataUri($logoPath) : null);
    }

    private static function pathToDataUri(string $path): string
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("Arquivo de logo não encontrado ou ilegível: {$path}");
        }

        $mime = mime_content_type($path) ?: 'image/png';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Não foi possível ler o arquivo de logo: {$path}");
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
```

- [ ] **Step 6: Rodar `MunicipalityBranding`**

Run: `./vendor/bin/pest tests/Unit/Danfse/MunicipalityBrandingTest.php`
Expected: PASS.

- [ ] **Step 7: Criar teste de `DanfseConfig`**

Criar `tests/Unit/Danfse/DanfseConfigTest.php`:

```php
<?php

use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

covers(DanfseConfig::class);

it('uses package default logo when no args', function () {
    $c = new DanfseConfig();
    expect($c->logoDataUri)->toStartWith('data:image/');
});

it('reads custom logo from path', function () {
    $path = __DIR__.'/../../fixtures/danfse/tiny-logo.png';
    $c = new DanfseConfig(logoPath: $path);
    expect($c->logoDataUri)->toStartWith('data:image/');
});

it('suppresses logo when logoPath is false', function () {
    $c = new DanfseConfig(logoPath: false);
    expect($c->logoDataUri)->toBeNull();
});

it('false logoPath overrides logoDataUri', function () {
    $c = new DanfseConfig(logoDataUri: 'data:image/png;base64,X', logoPath: false);
    expect($c->logoDataUri)->toBeNull();
});

it('prefers logoDataUri over logoPath', function () {
    $path = __DIR__.'/../../fixtures/danfse/tiny-logo.png';
    $c = new DanfseConfig(logoDataUri: 'data:image/png;base64,DIRECT', logoPath: $path);
    expect($c->logoDataUri)->toBe('data:image/png;base64,DIRECT');
});

it('carries municipality branding', function () {
    $branding = new MunicipalityBranding(name: 'X');
    $c = new DanfseConfig(municipality: $branding);
    expect($c->municipality)->toBe($branding);
});

it('throws for missing logo path', function () {
    expect(fn () => new DanfseConfig(logoPath: '/nope/missing.png'))
        ->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 8: Rodar**

Run: `./vendor/bin/pest tests/Unit/Danfse/DanfseConfigTest.php`
Expected: FAIL.

- [ ] **Step 9: Implementar `DanfseConfig`**

Criar `src/Danfse/DanfseConfig.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use InvalidArgumentException;
use RuntimeException;

/**
 * Configuração opcional da geração do DANFSE.
 *
 * - `logoDataUri` tem precedência sobre `logoPath` quando ambos são informados.
 * - `logoPath: false` suprime o logo completamente (ignora `logoDataUri`).
 * - `logoPath: null` usa o logo padrão do pacote.
 *
 * Portado de andrevabo/danfse-nacional (MIT).
 *
 * @api
 */
final readonly class DanfseConfig
{
    public ?string $logoDataUri;

    public function __construct(
        ?string $logoDataUri = null,
        string|false|null $logoPath = null,
        public ?MunicipalityBranding $municipality = null,
    ) {
        if ($logoPath === false) {
            $this->logoDataUri = null;

            return;
        }

        $this->logoDataUri = $logoDataUri
            ?? ($logoPath !== null ? self::pathToDataUri($logoPath) : self::defaultLogoDataUri());
    }

    private static function defaultLogoDataUri(): ?string
    {
        $path = __DIR__.'/../../storage/danfse/logo-nfse.png';

        return is_readable($path) ? self::pathToDataUri($path) : null;
    }

    private static function pathToDataUri(string $path): string
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("Arquivo de logo não encontrado ou ilegível: {$path}");
        }

        $mime = mime_content_type($path) ?: 'image/png';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Não foi possível ler o arquivo de logo: {$path}");
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
```

- [ ] **Step 10: Rodar**

Run: `./vendor/bin/pest tests/Unit/Danfse/DanfseConfigTest.php`
Expected: PASS.

- [ ] **Step 11: Commit**

```bash
git add src/Danfse/MunicipalityBranding.php src/Danfse/DanfseConfig.php \
  storage/danfse/logo-nfse.png \
  tests/Unit/Danfse/MunicipalityBrandingTest.php tests/Unit/Danfse/DanfseConfigTest.php \
  tests/fixtures/danfse/tiny-logo.png
git commit -m "feat(danfse): add DanfseConfig and MunicipalityBranding"
```

---

## Task 13: Criar driving port `RendersDanfse`

**Files:**
- Create: `src/Contracts/Driving/RendersDanfse.php`

Interface pura — sem teste próprio (testada via implementação na Task 19).

- [ ] **Step 1: Criar interface**

Criar `src/Contracts/Driving/RendersDanfse.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Exceptions\XmlParseException;
use OwnerPro\Nfsen\Responses\DanfseResponse;

/**
 * @api
 */
interface RendersDanfse
{
    /**
     * Renderiza o DANFSE como PDF a partir do XML da NFS-e autorizada.
     * Erros são encapsulados no `DanfseResponse` retornado.
     */
    public function toPdf(string $xmlNfse): DanfseResponse;

    /**
     * Renderiza o DANFSE como HTML a partir do XML da NFS-e autorizada.
     *
     * @throws XmlParseException quando o XML é inválido ou não contém NFS-e
     */
    public function toHtml(string $xmlNfse): string;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Contracts/Driving/RendersDanfse.php
git commit -m "feat(danfse): add RendersDanfse driving port"
```

---

## Task 14: Criar 4 driven ports

**Files:**
- Create: `src/Contracts/Driven/BuildsDanfseData.php`
- Create: `src/Contracts/Driven/RendersDanfseHtml.php`
- Create: `src/Contracts/Driven/ConvertsHtmlToPdf.php`
- Create: `src/Contracts/Driven/GeneratesQrCode.php`

- [ ] **Step 1: Criar `BuildsDanfseData`**

Criar `src/Contracts/Driven/BuildsDanfseData.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

use OwnerPro\Nfsen\Danfse\NfseData;
use OwnerPro\Nfsen\Exceptions\XmlParseException;

interface BuildsDanfseData
{
    /** @throws XmlParseException */
    public function build(string $xmlNfse): NfseData;
}
```

- [ ] **Step 2: Criar `RendersDanfseHtml`**

Criar `src/Contracts/Driven/RendersDanfseHtml.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

use OwnerPro\Nfsen\Danfse\NfseData;

interface RendersDanfseHtml
{
    public function render(NfseData $data): string;
}
```

- [ ] **Step 3: Criar `ConvertsHtmlToPdf`**

Criar `src/Contracts/Driven/ConvertsHtmlToPdf.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

interface ConvertsHtmlToPdf
{
    public function convert(string $html): string;
}
```

- [ ] **Step 4: Criar `GeneratesQrCode`**

Criar `src/Contracts/Driven/GeneratesQrCode.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

interface GeneratesQrCode
{
    /** Retorna um data URI (SVG ou PNG) com o conteúdo codificado. */
    public function dataUri(string $payload): string;
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Contracts/Driven/BuildsDanfseData.php \
  src/Contracts/Driven/RendersDanfseHtml.php \
  src/Contracts/Driven/ConvertsHtmlToPdf.php \
  src/Contracts/Driven/GeneratesQrCode.php
git commit -m "feat(danfse): add driven ports for DANFSE pipeline"
```

---

## Task 15: Adapter `BaconQrCodeGenerator`

**Files:**
- Create: `src/Adapters/BaconQrCodeGenerator.php`
- Test: `tests/Unit/Adapters/BaconQrCodeGeneratorTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Adapters/BaconQrCodeGeneratorTest.php`:

```php
<?php

use OwnerPro\Nfsen\Adapters\BaconQrCodeGenerator;

covers(BaconQrCodeGenerator::class);

it('produces an SVG data URI', function () {
    $gen = new BaconQrCodeGenerator();

    $dataUri = $gen->dataUri('https://example.com/test');

    expect($dataUri)->toStartWith('data:image/svg+xml;base64,');
});

it('encodes payload as valid base64 SVG', function () {
    $gen = new BaconQrCodeGenerator();

    $dataUri = $gen->dataUri('hello');

    $encoded = substr($dataUri, strlen('data:image/svg+xml;base64,'));
    $svg = base64_decode($encoded, strict: true);

    expect($svg)->toBeString();
    expect($svg)->toContain('<svg');
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Adapters/BaconQrCodeGeneratorTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

Criar `src/Adapters/BaconQrCodeGenerator.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use OwnerPro\Nfsen\Contracts\Driven\GeneratesQrCode;

final readonly class BaconQrCodeGenerator implements GeneratesQrCode
{
    public function __construct(private int $size = 200) {}

    public function dataUri(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($this->size),
            new SvgImageBackEnd(),
        );
        $svg = (new Writer($renderer))->writeString($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Adapters/BaconQrCodeGeneratorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Adapters/BaconQrCodeGenerator.php tests/Unit/Adapters/BaconQrCodeGeneratorTest.php
git commit -m "feat(danfse): add BaconQrCodeGenerator adapter"
```

---

## Task 16: Adapter `DompdfHtmlToPdfConverter`

**Files:**
- Create: `src/Adapters/DompdfHtmlToPdfConverter.php`
- Test: `tests/Unit/Adapters/DompdfHtmlToPdfConverterTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Adapters/DompdfHtmlToPdfConverterTest.php`:

```php
<?php

use OwnerPro\Nfsen\Adapters\DompdfHtmlToPdfConverter;

covers(DompdfHtmlToPdfConverter::class);

it('outputs a PDF with %PDF- prefix', function () {
    $converter = new DompdfHtmlToPdfConverter();

    $pdf = $converter->convert('<html><body><p>Teste</p></body></html>');

    expect($pdf)->toStartWith('%PDF-');
});

it('disables remote resources to prevent SSRF', function () {
    $converter = new DompdfHtmlToPdfConverter();

    // Remote image should NOT be loaded (isRemoteEnabled=false).
    // We just verify the PDF generates without fetching anything.
    $pdf = $converter->convert(
        '<html><body><img src="http://example.invalid/logo.png" alt=""/></body></html>'
    );

    expect($pdf)->toStartWith('%PDF-');
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Adapters/DompdfHtmlToPdfConverterTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

Criar `src/Adapters/DompdfHtmlToPdfConverter.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use Dompdf\Dompdf;
use Dompdf\Options;
use OwnerPro\Nfsen\Contracts\Driven\ConvertsHtmlToPdf;

final readonly class DompdfHtmlToPdfConverter implements ConvertsHtmlToPdf
{
    public function convert(string $html): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Adapters/DompdfHtmlToPdfConverterTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Adapters/DompdfHtmlToPdfConverter.php tests/Unit/Adapters/DompdfHtmlToPdfConverterTest.php
git commit -m "feat(danfse): add DompdfHtmlToPdfConverter adapter"
```

---

## Task 16.5: Criar helpers compartilhados de teste (`tests/helpers/danfse.php`)

**Files:**
- Create: `tests/helpers/danfse.php`
- Modify: `tests/helpers.php`

**Motivação:** Tasks 17 e 19 precisam de fabricadores de DTO (`sampleData`, `sampleParte`) e stubs de ports (`stubBuilder`, `stubRenderer`, `stubConverter`). Funções PHP no escopo global não podem ser declaradas duas vezes — se cada arquivo de teste declarar as suas, a suite quebra com `Cannot redeclare function`. Centralizamos em um único arquivo e carregamos uma vez via `tests/helpers.php` (padrão já usado pelo repo — ver `tests/Pest.php:8`).

- [ ] **Step 1: Criar diretório**

```bash
mkdir -p tests/helpers
```

- [ ] **Step 2: Criar `tests/helpers/danfse.php`**

```php
<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Contracts\Driven\BuildsDanfseData;
use OwnerPro\Nfsen\Contracts\Driven\ConvertsHtmlToPdf;
use OwnerPro\Nfsen\Contracts\Driven\GeneratesQrCode;
use OwnerPro\Nfsen\Contracts\Driven\RendersDanfseHtml;
use OwnerPro\Nfsen\Danfse\Data\DanfseParte;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\NfseData;
use OwnerPro\Nfsen\Enums\NfseAmbiente;

function fakeQrGen(): GeneratesQrCode
{
    return new class implements GeneratesQrCode {
        public function dataUri(string $payload): string
        {
            return 'data:image/svg+xml;base64,FAKEQR_'.base64_encode($payload);
        }
    };
}

function sampleParte(string $nome = 'ACME LTDA'): DanfseParte
{
    return new DanfseParte(
        nome: $nome, cnpjCpf: '11.222.333/0001-81', im: '-',
        telefone: '(11) 3333-4444', email: 'acme@example.com',
        endereco: 'Rua X, 100, Centro', municipio: 'São Paulo - SP',
        cep: '01310-100', simplesNacional: 'Não Optante', regimeSN: '-',
    );
}

function sampleData(NfseAmbiente $ambiente = NfseAmbiente::PRODUCAO, ?DanfseParte $interm = null): NfseData
{
    return new NfseData(
        chaveAcesso: '3303302112233450000195000000000000100000000001',
        numeroNfse: '10', competencia: '15/01/2026', emissaoNfse: '15/01/2026 14:30:00',
        numeroDps: '5', serieDps: '20261', emissaoDps: '15/01/2026 14:00:00',
        ambiente: $ambiente,
        emitente: sampleParte('EMITENTE LTDA'),
        tomador: sampleParte('TOMADOR S.A.'),
        intermediario: $interm,
        servico: new DanfseServico(
            codigoTribNacional: '01.07.00', descTribNacional: 'Desenvolvimento de software',
            codigoTribMunicipal: '007', descTribMunicipal: 'Desenvolvimento',
            localPrestacao: 'São Paulo', paisPrestacao: '-', descricao: 'Projeto Pulsar',
        ),
        tribMun: new DanfseTributacaoMunicipal(
            tributacaoIssqn: 'Operação Tributável', municipioIncidencia: 'São Paulo - SP',
            regimeEspecial: 'Nenhum', valorServico: 'R$ 1.500,00', bcIssqn: 'R$ 1.350,00',
            aliquota: '2.00%', retencaoIssqn: 'Retido pelo Tomador', issqnApurado: 'R$ 27,00',
        ),
        tribFed: new DanfseTributacaoFederal(
            irrf: 'R$ 22,50', cp: 'R$ 15,00', csll: '-', pis: 'R$ 9,75', cofins: 'R$ 45,00',
        ),
        totais: new DanfseTotais(
            valorServico: 'R$ 1.500,00', descontoCondicionado: '-', descontoIncondicionado: '-',
            issqnRetido: 'R$ 27,00', retencoesFederais: 'R$ 52,50', pisCofins: 'R$ 54,75',
            valorLiquido: 'R$ 1.292,75',
        ),
        totaisTributos: new DanfseTotaisTributos(federais: '4.50%', estaduais: '0.10%', municipais: '2.00%'),
        informacoesComplementares: 'Referente ao contrato 2026-001',
    );
}

function stubBuilder(NfseData|Throwable $result): BuildsDanfseData
{
    return new class($result) implements BuildsDanfseData {
        public function __construct(private NfseData|Throwable $result) {}

        public function build(string $xmlNfse): NfseData
        {
            if ($this->result instanceof Throwable) {
                throw $this->result;
            }

            return $this->result;
        }
    };
}

function stubHtmlRenderer(string|Throwable $html = '<html>OK</html>'): RendersDanfseHtml
{
    return new class($html) implements RendersDanfseHtml {
        public function __construct(private string|Throwable $html) {}

        public function render(NfseData $data): string
        {
            if ($this->html instanceof Throwable) {
                throw $this->html;
            }

            return $this->html;
        }
    };
}

function stubPdfConverter(string|Throwable $pdf = '%PDF-1.4'): ConvertsHtmlToPdf
{
    return new class($pdf) implements ConvertsHtmlToPdf {
        public function __construct(private string|Throwable $pdf) {}

        public function convert(string $html): string
        {
            if ($this->pdf instanceof Throwable) {
                throw $this->pdf;
            }

            return $this->pdf;
        }
    };
}
```

- [ ] **Step 3: Registrar em `tests/helpers.php`**

Ler `tests/helpers.php` atual e adicionar ao final:

```php
require_once __DIR__.'/helpers/danfse.php';
```

- [ ] **Step 4: Commit**

```bash
git add tests/helpers/danfse.php tests/helpers.php
git commit -m "test(danfse): add shared helpers for DANFSE test factories"
```

---

## Task 17: Portar template HTML + criar `DanfseHtmlRenderer`

**Files:**
- Create: `storage/danfse/template.php` (portado da lib)
- Create: `src/Adapters/DanfseHtmlRenderer.php`
- Test: `tests/Unit/Adapters/DanfseHtmlRendererTest.php`

- [ ] **Step 1: Copiar template da lib**

```bash
REF=$(ls -d /tmp/danfse-nacional-ref-* | head -1)
cp $REF/src/Template/danfse.php storage/danfse/template.php
```

- [ ] **Step 2: Adaptar template para consumir `NfseData` tipado + helper `$h()` para escape**

**Estratégia de escape:** o renderer injeta uma closure `$h` no escopo do template (`fn (string $s) => htmlspecialchars(...)`). Todas as interpolações de conteúdo vindo do XML usam `<?= $h($data->...) ?>`. Isso substitui o `array_walk_recursive` da lib original, fica explícito no template (auditável) e não exige reconstrução de DTO.

**Edições no `storage/danfse/template.php`:**

1. Adicionar no topo (após `<?php`):

```php
/** @var \OwnerPro\Nfsen\Danfse\NfseData $data */
/** @var \OwnerPro\Nfsen\Danfse\MunicipalityBranding|null $municipality */
/** @var string|null $logo */
/** @var string $qrCode */
/** @var \Closure(string):string $h */
```

2. Substituições de array → DTO (aplicar em TODAS as ocorrências):

| De (array) | Para (DTO) |
|---|---|
| `$data['chave_acesso']` | `$data->chaveAcesso` |
| `$data['numero_nfse']` | `$data->numeroNfse` |
| `$data['competencia']` | `$data->competencia` |
| `$data['emissao_nfse']` | `$data->emissaoNfse` |
| `$data['numero_dps']` | `$data->numeroDps` |
| `$data['serie_dps']` | `$data->serieDps` |
| `$data['emissao_dps']` | `$data->emissaoDps` |
| `$data['emitente']['nome']` | `$data->emitente->nome` (idem para cnpj_cpf→cnpjCpf, im, telefone, email, endereco, municipio, cep, simples_nacional→simplesNacional, regime_sn→regimeSN) |
| `$data['tomador'][...]` | `$data->tomador->...` (mesmos subcampos, exceto sem simplesNacional/regimeSN) |
| `$data['intermediario']` (array ou null) | `$data->intermediario` (`DanfseParte` ou null) — substituir `$data['intermediario'][...]` por `$data->intermediario->...` |
| `$data['servico']['codigo_trib_nacional']` | `$data->servico->codigoTribNacional` (idem descTribNacional, codigoTribMunicipal, descTribMunicipal, localPrestacao, paisPrestacao, descricao) |
| `$data['tributacao_municipal'][...]` | `$data->tribMun->...` (tributacaoIssqn, municipioIncidencia, regimeEspecial, valorServico, bcIssqn, aliquota, retencaoIssqn, issqnApurado) |
| `$data['tributacao_federal'][...]` | `$data->tribFed->...` (irrf, cp, csll, pis, cofins) |
| `$data['totais'][...]` | `$data->totais->...` (valorServico, descontoCondicionado, descontoIncondicionado, issqnRetido, retencoesFederais, pisCofins, valorLiquido) |
| `$data['totais_tributos'][...]` | `$data->totaisTributos->...` (federais, estaduais, municipais) |
| `$data['informacoes_complementares']` | `$data->informacoesComplementares` |

3. **Marca d'água (ambiente):** há exatamente **duas ocorrências** de `$data['ambiente'] == 2` (linhas 132 e 147 do arquivo original da lib). Trocar **ambas** por `$data->ambiente->isHomologacao()`. **Não** usar `->value` em lugar nenhum.

4. **Wrap de escape:** toda interpolação `<?= $data->... ?>` e `<?= $data->algo->campo ?>` vira `<?= $h($data->... ) ?>` / `<?= $h($data->algo->campo) ?>`. Para as interpolações que já usam `htmlspecialchars($logo)`, `htmlspecialchars($qrCode)`, `htmlspecialchars($municipality->...)`, simplificar para `$h($logo)`, `$h($qrCode)`, `$h($municipality->nome)` etc. (comportamento idêntico, notação uniforme).

Comando de validação sintática após as substituições:

```bash
php -l storage/danfse/template.php
```
Expected: `No syntax errors`.

**Conferência rápida** de que não sobrou `$data[...]` sintático no template:

```bash
! grep -qE "\\\$data\\[" storage/danfse/template.php && echo "OK: nenhum acesso array remanescente" || echo "FALHOU: ainda há \$data[...]"
```
Expected: `OK: nenhum acesso array remanescente`.

- [ ] **Step 3: Criar teste falhante**

Criar `tests/Unit/Adapters/DanfseHtmlRendererTest.php`:

```php
<?php

use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;
use OwnerPro\Nfsen\Danfse\NfseData;
use OwnerPro\Nfsen\Enums\NfseAmbiente;

covers(DanfseHtmlRenderer::class);

// Helpers `fakeQrGen()`, `sampleParte()`, `sampleData()` vêm de tests/helpers/danfse.php (Task 16.5).

it('produces HTML containing chave de acesso', function () {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData());

    expect($html)->toContain('3303302112233450000195000000000000100000000001');
});

it('embeds QR code data URI from generator', function () {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData());

    expect($html)->toContain('FAKEQR_');
    expect($html)->toContain('nfse.gov.br/ConsultaPublica');
});

it('shows watermark only in homologacao', function () {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $prod = $r->render(sampleData(NfseAmbiente::PRODUCAO));
    $homo = $r->render(sampleData(NfseAmbiente::HOMOLOGACAO));

    expect($prod)->not->toContain('SEM VALIDADE JURÍDICA');
    expect($homo)->toContain('SEM VALIDADE JURÍDICA');
});

it('shows "NÃO IDENTIFICADO" when there is no intermediario', function () {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData(interm: null));

    expect($html)->toContain('NÃO IDENTIFICADO');
});

it('shows intermediario block when present', function () {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $interm = sampleParte('INTERMEDIÁRIO LTDA');
    $html = $r->render(sampleData(interm: $interm));

    expect($html)->toContain('INTERMEDIÁRIO LTDA');
});

it('includes municipality branding in header when provided', function () {
    $branding = new MunicipalityBranding(
        name: 'Prefeitura de Teste',
        department: 'Secretaria de Fazenda',
        email: 'iss@teste.gov.br',
    );
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false, municipality: $branding));

    $html = $r->render(sampleData());

    expect($html)->toContain('Prefeitura de Teste');
    expect($html)->toContain('Secretaria de Fazenda');
    expect($html)->toContain('iss@teste.gov.br');
});

it('renders empty municipality cell when branding is absent', function () {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData());

    expect($html)->not->toContain('Prefeitura de Teste');
});

it('uses default NFSe logo when no custom logo configured', function () {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig());

    $html = $r->render(sampleData());

    expect($html)->toContain('data:image/');
});

it('omits logo when logoPath is false', function () {
    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));

    $html = $r->render(sampleData());

    // Deve ter só o QR code como data URI, não o logo.
    $dataUriCount = substr_count($html, 'data:image/');
    expect($dataUriCount)->toBe(1); // apenas o QR
});

it('escapes HTML in data fields (XSS prevention)', function () {
    $malicious = sampleParte('<script>alert(1)</script>');
    $data = new NfseData(
        chaveAcesso: 'X', numeroNfse: '1', competencia: '-', emissaoNfse: '-',
        numeroDps: '1', serieDps: '1', emissaoDps: '-',
        ambiente: NfseAmbiente::PRODUCAO,
        emitente: $malicious, tomador: sampleParte(), intermediario: null,
        servico: new DanfseServico('-', '-', '-', '-', '-', '-', '-'),
        tribMun: new DanfseTributacaoMunicipal('-', '-', '-', '-', '-', '-', '-', '-'),
        tribFed: new DanfseTributacaoFederal('-', '-', '-', '-', '-'),
        totais: new DanfseTotais('-', '-', '-', '-', '-', '-', '-'),
        totaisTributos: new DanfseTotaisTributos('-', '-', '-'),
        informacoesComplementares: '',
    );

    $r = new DanfseHtmlRenderer(fakeQrGen(), new DanfseConfig(logoPath: false));
    $html = $r->render($data);

    expect($html)->not->toContain('<script>alert(1)</script>');
    expect($html)->toContain('&lt;script&gt;');
});
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Adapters/DanfseHtmlRendererTest.php`
Expected: FAIL (classe não existe).

- [ ] **Step 5: Implementar `DanfseHtmlRenderer`**

Criar `src/Adapters/DanfseHtmlRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use Closure;
use OwnerPro\Nfsen\Contracts\Driven\GeneratesQrCode;
use OwnerPro\Nfsen\Contracts\Driven\RendersDanfseHtml;
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;
use OwnerPro\Nfsen\Danfse\NfseData;

final readonly class DanfseHtmlRenderer implements RendersDanfseHtml
{
    private const CONSULTA_URL = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=';

    private const TEMPLATE_PATH = __DIR__.'/../../storage/danfse/template.php';

    public function __construct(
        private GeneratesQrCode $qrGenerator,
        private DanfseConfig $config,
    ) {}

    public function render(NfseData $data): string
    {
        $qrCode = $this->qrGenerator->dataUri(self::CONSULTA_URL.$data->chaveAcesso);
        $logo = $this->config->logoDataUri;
        $municipality = $this->config->municipality;
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $this->renderTemplate($data, $qrCode, $logo, $municipality, $h);
    }

    /**
     * @param  Closure(string):string  $h
     */
    private function renderTemplate(
        NfseData $data,
        string $qrCode,
        ?string $logo,
        ?MunicipalityBranding $municipality,
        Closure $h,
    ): string {
        ob_start();
        include self::TEMPLATE_PATH;

        return (string) ob_get_clean();
    }
}
```

Observações:
- `$h` é a closure de escape disponível no template — substitui o `array_walk_recursive` da lib por escape explícito em cada interpolação (auditável).
- Sem reconstrução de DTO: adicionar campo a um sub-DTO não exige tocar no renderer. Se esquecer o `$h()` em uma interpolação do template, o teste de XSS (Step 3) pega.

- [ ] **Step 6: Rodar**

Run: `./vendor/bin/pest tests/Unit/Adapters/DanfseHtmlRendererTest.php`
Expected: PASS (todos os 10 testes).

Se algum falhar, provavelmente é o template ainda acessando `$data` como array em algum ponto — revise as substituições do Step 2.

- [ ] **Step 7: Commit**

```bash
git add storage/danfse/template.php src/Adapters/DanfseHtmlRenderer.php \
  tests/Unit/Adapters/DanfseHtmlRendererTest.php
git commit -m "feat(danfse): add DanfseHtmlRenderer adapter with PHP template"
```

---

## Task 18: Adapter `DanfseDataBuilder`

**Files:**
- Create: `src/Adapters/DanfseDataBuilder.php`
- Create: `tests/fixtures/danfse/nfse-autorizada.xml` (copiado da lib)
- Test: `tests/Unit/Adapters/DanfseDataBuilderTest.php`

- [ ] **Step 1: Copiar XML fixture da lib**

```bash
REF=$(ls -d /tmp/danfse-nacional-ref-* | head -1)
mkdir -p tests/fixtures/danfse
cp $REF/examples/nfse_exemplo.xml tests/fixtures/danfse/nfse-autorizada.xml
```

- [ ] **Step 2: Gerar fixture de homologação**

O XML exemplo da lib contém `<tpAmb>1</tpAmb>` dentro de `<infDPS>` (verificado). Copiar e trocar para `2`:

```bash
cp tests/fixtures/danfse/nfse-autorizada.xml tests/fixtures/danfse/nfse-homologacao.xml
sed -i 's|<tpAmb>1</tpAmb>|<tpAmb>2</tpAmb>|g' tests/fixtures/danfse/nfse-homologacao.xml

# Verificar a substituição:
grep -c '<tpAmb>2</tpAmb>' tests/fixtures/danfse/nfse-homologacao.xml
```
Expected: `1` (uma ocorrência substituída).

- [ ] **Step 3: Criar teste falhante**

Criar `tests/Unit/Adapters/DanfseDataBuilderTest.php`:

```php
<?php

use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Danfse\NfseData;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Exceptions\XmlParseException;

covers(DanfseDataBuilder::class);

beforeEach(function () {
    $this->builder = new DanfseDataBuilder();
    $this->xml = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-autorizada.xml');
});

it('builds NfseData from authorized XML', function () {
    $data = $this->builder->build($this->xml);

    expect($data)->toBeInstanceOf(NfseData::class);
    expect($data->chaveAcesso)->toBe('3303302112233450000195000000000000100000000001');
    expect($data->numeroNfse)->toBe('10');
});

it('extracts emitente CNPJ formatted', function () {
    $data = $this->builder->build($this->xml);
    expect($data->emitente->cnpjCpf)->toBe('11.222.333/0001-81');
});

it('extracts tomador city via Municipios lookup', function () {
    $data = $this->builder->build($this->xml);
    // CLIENTE FICTICIO COMERCIO S.A. em São Paulo (3550308)
    expect($data->tomador->municipio)->toBe('São Paulo - SP');
});

it('sets intermediario to null when absent', function () {
    // Remove bloco <interm>... do XML e rebuild
    $xml = preg_replace('|<interm>.*?</interm>|s', '', $this->xml);
    $data = $this->builder->build((string) $xml);
    expect($data->intermediario)->toBeNull();
});

it('detects ambiente Producao', function () {
    $data = $this->builder->build($this->xml);
    expect($data->ambiente)->toBe(NfseAmbiente::PRODUCAO);
});

it('detects ambiente Homologacao from fixture', function () {
    $xml = (string) file_get_contents(__DIR__.'/../../fixtures/danfse/nfse-homologacao.xml');
    $data = $this->builder->build($xml);
    expect($data->ambiente)->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('throws for empty XML', function () {
    expect(fn () => $this->builder->build(''))
        ->toThrow(XmlParseException::class);
});

it('throws for malformed XML', function () {
    expect(fn () => $this->builder->build('<not-xml'))
        ->toThrow(XmlParseException::class);
});

it('throws for XML without NFSe namespace', function () {
    expect(fn () => $this->builder->build('<?xml version="1.0"?><foo><bar/></foo>'))
        ->toThrow(XmlParseException::class);
});
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Adapters/DanfseDataBuilderTest.php`
Expected: FAIL.

- [ ] **Step 5: Implementar**

Criar `src/Adapters/DanfseDataBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use OwnerPro\Nfsen\Contracts\Driven\BuildsDanfseData;
use OwnerPro\Nfsen\Danfse\Data\DanfseParte;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\Formatter;
use OwnerPro\Nfsen\Danfse\Municipios;
use OwnerPro\Nfsen\Danfse\NfseData;
use OwnerPro\Nfsen\Dps\Enums\Prest\OpSimpNac;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegApTribSN;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegEspTrib;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;
use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Exceptions\XmlParseException;
use SimpleXMLElement;
use Throwable;

/**
 * Constrói NfseData a partir do XML da NFS-e Nacional autorizada.
 *
 * Parseia com SimpleXMLElement usando LIBXML_NONET (sem carregamento de rede/XXE),
 * valida o namespace oficial, e aplica formatters + enum labels.
 */
final readonly class DanfseDataBuilder implements BuildsDanfseData
{
    private const NFSE_NS = 'http://www.sped.fazenda.gov.br/nfse';

    public function __construct(private Formatter $fmt = new Formatter()) {}

    public function build(string $xmlNfse): NfseData
    {
        if (trim($xmlNfse) === '') {
            throw new XmlParseException('XML vazio.');
        }

        try {
            $root = new SimpleXMLElement($xmlNfse, LIBXML_NONET);
        } catch (Throwable $e) {
            throw new XmlParseException('XML malformado: '.$e->getMessage(), previous: $e);
        }

        // Registra o namespace para XPath e valida presença
        $ns = $root->getNamespaces(true);
        if (! in_array(self::NFSE_NS, $ns, true)) {
            throw new XmlParseException('XML não está no namespace NFS-e ('.self::NFSE_NS.').');
        }
        $root->registerXPathNamespace('n', self::NFSE_NS);

        $infNFSe = $root->children(self::NFSE_NS)->infNFSe ?? null;
        if ($infNFSe === null) {
            throw new XmlParseException('XML não contém infNFSe.');
        }

        return $this->fromInf($infNFSe);
    }

    private function fromInf(SimpleXMLElement $inf): NfseData
    {
        $id = (string) ($inf['Id'] ?? '');
        $chave = str_starts_with($id, 'NFS') ? substr($id, 3) : $id;

        $dps = $inf->DPS ?? null;
        $infDps = $dps?->infDPS ?? null;
        $prest = $infDps?->prest ?? null;
        $regTrib = $prest?->regTrib ?? null;
        $emit = $inf->emit ?? null;
        $toma = $infDps?->toma ?? null;
        $interm = $infDps?->interm ?? null;
        $serv = $infDps?->serv ?? null;
        $cServ = $serv?->cServ ?? null;
        $valores = $infDps?->valores ?? null;
        $trib = $valores?->trib ?? null;
        $tribMun = $trib?->tribMun ?? null;
        $tribFed = $trib?->tribFed ?? null;
        $totTrib = $trib?->totTrib ?? null;
        $valNfse = $inf->valores ?? null;

        $ambiente = NfseAmbiente::tryFrom((string) ($infDps?->tpAmb ?? '1')) ?? NfseAmbiente::PRODUCAO;

        return new NfseData(
            chaveAcesso: $chave,
            numeroNfse: (string) ($inf->nNFSe ?? '-'),
            competencia: $this->fmt->date((string) ($infDps?->dCompet ?? '')),
            emissaoNfse: $this->fmt->dateTime((string) ($inf->dhProc ?? '')),
            numeroDps: (string) ($infDps?->nDPS ?? '-'),
            serieDps: (string) ($infDps?->serie ?? '-'),
            emissaoDps: $this->fmt->dateTime((string) ($infDps?->dhEmi ?? '')),
            ambiente: $ambiente,
            emitente: $this->buildEmitente($emit, $inf, $regTrib),
            tomador: $this->buildTomador($toma),
            intermediario: $interm !== null ? $this->buildIntermediario($interm) : null,
            servico: $this->buildServico($inf, $serv, $cServ),
            tribMun: $this->buildTribMun($inf, $tribMun, $valores?->vServPrest, $regTrib),
            tribFed: $this->buildTribFed($tribFed),
            totais: $this->buildTotais($valores?->vServPrest, $tribMun, $tribFed, $valNfse),
            totaisTributos: $this->buildTotaisTributos($totTrib),
            informacoesComplementares: (string) ($serv?->infoCompl?->xInfComp ?? ''),
        );
    }

    private function buildEmitente(?SimpleXMLElement $emit, SimpleXMLElement $inf, ?SimpleXMLElement $regTrib): DanfseParte
    {
        $ender = $emit?->enderNac ?? null;
        $doc = (string) ($emit?->CNPJ ?? $emit?->CPF ?? $emit?->NIF ?? '');

        $endereco = implode(', ', array_filter([
            (string) ($ender?->xLgr ?? ''),
            (string) ($ender?->nro ?? ''),
            (string) ($ender?->xBairro ?? ''),
        ], fn ($v) => $v !== ''));

        $municipio = '-';
        $xLocEmi = (string) ($inf->xLocEmi ?? '');
        $uf = (string) ($ender?->UF ?? '');
        if ($xLocEmi !== '' && $uf !== '') {
            $municipio = $xLocEmi.' - '.$uf;
        }

        return new DanfseParte(
            nome: (string) ($emit?->xNome ?? '-'),
            cnpjCpf: $this->fmt->cnpjCpf($doc),
            im: '-',
            telefone: $this->fmt->phone((string) ($emit?->fone ?? '')),
            email: strtolower((string) ($emit?->email ?? '')),
            endereco: $endereco !== '' ? $endereco : '-',
            municipio: $municipio,
            cep: $this->fmt->cep((string) ($ender?->CEP ?? '')),
            simplesNacional: OpSimpNac::labelOf((string) ($regTrib?->opSimpNac ?? '')),
            regimeSN: RegApTribSN::labelOf((string) ($regTrib?->regApTribSN ?? '')),
        );
    }

    private function buildTomador(?SimpleXMLElement $toma): DanfseParte
    {
        if ($toma === null) {
            return $this->emptyParte();
        }
        $end = $toma->end ?? null;
        $endNac = $end?->endNac ?? null;
        $doc = (string) ($toma->CNPJ ?? $toma->CPF ?? $toma->NIF ?? '');

        $endereco = implode(', ', array_filter([
            (string) ($end?->xLgr ?? ''),
            (string) ($end?->nro ?? ''),
            (string) ($end?->xBairro ?? ''),
        ], fn ($v) => $v !== ''));

        return new DanfseParte(
            nome: (string) ($toma->xNome ?? '-'),
            cnpjCpf: $this->fmt->cnpjCpf($doc),
            im: (string) ($toma->IM ?? '') !== '' ? (string) $toma->IM : '-',
            telefone: $this->fmt->phone((string) ($toma->fone ?? '')),
            email: strtolower((string) ($toma->email ?? '')),
            endereco: $endereco !== '' ? $endereco : '-',
            municipio: $endNac !== null ? Municipios::lookup((string) $endNac->cMun) : '-',
            cep: $this->fmt->cep((string) ($endNac?->CEP ?? '')),
        );
    }

    private function buildIntermediario(SimpleXMLElement $interm): DanfseParte
    {
        $end = $interm->end ?? null;
        $endNac = $end?->endNac ?? null;
        $doc = (string) ($interm->CNPJ ?? $interm->CPF ?? $interm->NIF ?? '');

        $endereco = implode(', ', array_filter([
            (string) ($end?->xLgr ?? ''),
            (string) ($end?->nro ?? ''),
            (string) ($end?->xBairro ?? ''),
        ], fn ($v) => $v !== ''));

        return new DanfseParte(
            nome: (string) ($interm->xNome ?? '-'),
            cnpjCpf: $this->fmt->cnpjCpf($doc),
            im: (string) ($interm->IMPrestMun ?? '') !== '' ? (string) $interm->IMPrestMun : '-',
            telefone: $this->fmt->phone((string) ($interm->fone ?? '')),
            email: strtolower((string) ($interm->email ?? '')),
            endereco: $endereco !== '' ? $endereco : '-',
            municipio: $endNac !== null ? Municipios::lookup((string) $endNac->cMun) : '-',
            cep: $this->fmt->cep((string) ($endNac?->CEP ?? '')),
        );
    }

    private function emptyParte(): DanfseParte
    {
        return new DanfseParte('-', '-', '-', '-', '-', '-', '-', '-');
    }

    private function buildServico(SimpleXMLElement $inf, ?SimpleXMLElement $serv, ?SimpleXMLElement $cServ): DanfseServico
    {
        $locPrest = $serv?->locPrest ?? null;

        return new DanfseServico(
            codigoTribNacional: $this->fmt->codTribNacional((string) ($cServ?->cTribNac ?? '')),
            descTribNacional: $this->fmt->limit(trim((string) ($inf->xTribNac ?? '')), 60),
            codigoTribMunicipal: (string) ($cServ?->cTribMun ?? '') !== '' ? (string) $cServ->cTribMun : '-',
            descTribMunicipal: $this->fmt->limit(trim((string) ($inf->xTribMun ?? '')), 60),
            localPrestacao: (string) ($inf->xLocPrestacao ?? '-'),
            paisPrestacao: (string) ($locPrest?->cPaisPrestacao ?? '-'),
            descricao: (string) ($cServ?->xDescServ ?? '-'),
        );
    }

    private function buildTribMun(
        SimpleXMLElement $inf,
        ?SimpleXMLElement $tribMun,
        ?SimpleXMLElement $vServPrest,
        ?SimpleXMLElement $regTrib,
    ): DanfseTributacaoMunicipal {
        return new DanfseTributacaoMunicipal(
            tributacaoIssqn: TribISSQN::labelOf((string) ($tribMun?->tribISSQN ?? '')),
            municipioIncidencia: (string) ($inf->xLocIncid ?? '-'),
            regimeEspecial: RegEspTrib::labelOf((string) ($regTrib?->regEspTrib ?? '')),
            valorServico: $this->fmt->currency((string) ($vServPrest?->vServ ?? '')),
            bcIssqn: (string) ($tribMun?->vBC ?? '') !== '' ? $this->fmt->currency((string) $tribMun->vBC) : '-',
            aliquota: (string) ($tribMun?->pAliq ?? '') !== '' ? ((string) $tribMun->pAliq).'%' : '-',
            retencaoIssqn: TpRetISSQN::labelOf((string) ($tribMun?->tpRetISSQN ?? '')),
            issqnApurado: (string) ($tribMun?->vISSQN ?? '') !== '' ? $this->fmt->currency((string) $tribMun->vISSQN) : '-',
        );
    }

    private function buildTribFed(?SimpleXMLElement $tribFed): DanfseTributacaoFederal
    {
        $pc = $tribFed?->piscofins ?? null;

        return new DanfseTributacaoFederal(
            irrf: (string) ($tribFed?->vRetIRRF ?? '') !== '' ? $this->fmt->currency((string) $tribFed->vRetIRRF) : '-',
            cp: (string) ($tribFed?->vRetCP ?? '') !== '' ? $this->fmt->currency((string) $tribFed->vRetCP) : '-',
            csll: (string) ($tribFed?->vRetCSLL ?? '') !== '' ? $this->fmt->currency((string) $tribFed->vRetCSLL) : '-',
            pis: (string) ($pc?->vPis ?? '') !== '' ? $this->fmt->currency((string) $pc->vPis) : '-',
            cofins: (string) ($pc?->vCofins ?? '') !== '' ? $this->fmt->currency((string) $pc->vCofins) : '-',
        );
    }

    private function buildTotais(
        ?SimpleXMLElement $vServPrest,
        ?SimpleXMLElement $tribMun,
        ?SimpleXMLElement $tribFed,
        ?SimpleXMLElement $valNfse,
    ): DanfseTotais {
        $issqnRetido = '-';
        if ((string) ($tribMun?->vISSQN ?? '') !== '' && (string) ($tribMun?->tpRetISSQN ?? '1') !== '1') {
            $issqnRetido = $this->fmt->currency((string) $tribMun->vISSQN);
        }

        $pc = $tribFed?->piscofins ?? null;

        return new DanfseTotais(
            valorServico: $this->fmt->currency((string) ($vServPrest?->vServ ?? '')),
            descontoCondicionado: (string) ($tribMun?->vDescCond ?? '') !== '' ? $this->fmt->currency((string) $tribMun->vDescCond) : '-',
            descontoIncondicionado: (string) ($tribMun?->vDescIncond ?? '') !== '' ? $this->fmt->currency((string) $tribMun->vDescIncond) : '-',
            issqnRetido: $issqnRetido,
            retencoesFederais: $this->sumCurrency(
                (string) ($tribFed?->vRetIRRF ?? ''),
                (string) ($tribFed?->vRetCP ?? ''),
                (string) ($tribFed?->vRetCSLL ?? ''),
            ),
            pisCofins: $this->sumCurrency(
                (string) ($pc?->vPis ?? ''),
                (string) ($pc?->vCofins ?? ''),
            ),
            valorLiquido: $this->fmt->currency((string) ($valNfse?->vLiq ?? '')),
        );
    }

    private function buildTotaisTributos(?SimpleXMLElement $totTrib): DanfseTotaisTributos
    {
        $p = $totTrib?->pTotTrib ?? null;

        return new DanfseTotaisTributos(
            federais: (string) ($p?->pTotTribFed ?? '') !== '' ? ((string) $p->pTotTribFed).'%' : '-',
            estaduais: (string) ($p?->pTotTribEst ?? '') !== '' ? ((string) $p->pTotTribEst).'%' : '-',
            municipais: (string) ($p?->pTotTribMun ?? '') !== '' ? ((string) $p->pTotTribMun).'%' : '-',
        );
    }

    private function sumCurrency(string ...$values): string
    {
        $sum = 0.0;
        $hasValue = false;
        foreach ($values as $v) {
            if ($v !== '') {
                $sum += (float) $v;
                $hasValue = true;
            }
        }

        return $hasValue ? $this->fmt->currency((string) $sum) : '-';
    }
}
```

- [ ] **Step 6: Rodar**

Run: `./vendor/bin/pest tests/Unit/Adapters/DanfseDataBuilderTest.php`
Expected: PASS.

Se testes específicos falharem (por exemplo, `detects ambiente Producao`), inspecionar o XML fixture com `grep -n 'tpAmb\|xLocEmi' tests/fixtures/danfse/nfse-autorizada.xml` e ajustar a navegação no builder.

- [ ] **Step 7: Commit**

```bash
git add src/Adapters/DanfseDataBuilder.php \
  tests/Unit/Adapters/DanfseDataBuilderTest.php \
  tests/fixtures/danfse/nfse-autorizada.xml \
  tests/fixtures/danfse/nfse-homologacao.xml
git commit -m "feat(danfse): add DanfseDataBuilder adapter"
```

---

## Task 19: Operation `NfseDanfseRenderer`

**Files:**
- Create: `src/Operations/NfseDanfseRenderer.php`
- Test: `tests/Unit/Operations/NfseDanfseRendererTest.php`

- [ ] **Step 1: Criar teste falhante**

Criar `tests/Unit/Operations/NfseDanfseRendererTest.php`:

```php
<?php

use Dompdf\Exception as DompdfException;
use OwnerPro\Nfsen\Exceptions\XmlParseException;
use OwnerPro\Nfsen\Operations\NfseDanfseRenderer;
use OwnerPro\Nfsen\Responses\DanfseResponse;

covers(NfseDanfseRenderer::class);

// Helpers `stubBuilder()`, `stubHtmlRenderer()`, `stubPdfConverter()`, `sampleData()` vêm de tests/helpers/danfse.php (Task 16.5).

beforeEach(function () {
    $this->xml = '<xml>irrelevant (builder stubbed)</xml>';
});

it('toPdf returns successful DanfseResponse', function () {
    $op = new NfseDanfseRenderer(stubBuilder(sampleData()), stubHtmlRenderer(), stubPdfConverter());

    $resp = $op->toPdf($this->xml);

    expect($resp)->toBeInstanceOf(DanfseResponse::class);
    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->toBe('%PDF-1.4');
    expect($resp->erros)->toBe([]);
});

it('toPdf wraps XmlParseException into DanfseResponse', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(new XmlParseException('xml foo')),
        stubHtmlRenderer(),
        stubPdfConverter(),
    );

    $resp = $op->toPdf($this->xml);

    expect($resp->sucesso)->toBeFalse();
    expect($resp->erros)->toHaveCount(1);
    expect($resp->erros[0]->descricao)->toBe('XML da NFS-e inválido ou malformado.');
    expect($resp->erros[0]->complemento)->toBe('xml foo');
    expect($resp->pdf)->toBeNull();
});

it('toPdf wraps Dompdf exception', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(sampleData()),
        stubHtmlRenderer(),
        stubPdfConverter(new DompdfException('pdf broke')),
    );

    $resp = $op->toPdf($this->xml);

    expect($resp->sucesso)->toBeFalse();
    expect($resp->erros[0]->descricao)->toBe('Falha ao renderizar o PDF.');
    expect($resp->erros[0]->complemento)->toBe('pdf broke');
});

it('toPdf wraps generic Throwable', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(sampleData()),
        stubHtmlRenderer(new RuntimeException('boom')),
        stubPdfConverter(),
    );

    $resp = $op->toPdf($this->xml);

    expect($resp->sucesso)->toBeFalse();
    expect($resp->erros[0]->descricao)->toBe('Erro inesperado ao gerar DANFSE.');
    expect($resp->erros[0]->complemento)->toBe('boom');
});

it('toHtml returns the html on success', function () {
    $op = new NfseDanfseRenderer(stubBuilder(sampleData()), stubHtmlRenderer('<html>X</html>'), stubPdfConverter());

    expect($op->toHtml($this->xml))->toBe('<html>X</html>');
});

it('toHtml propagates XmlParseException', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(new XmlParseException('bad')),
        stubHtmlRenderer(),
        stubPdfConverter(),
    );

    expect(fn () => $op->toHtml($this->xml))->toThrow(XmlParseException::class, 'bad');
});

it('toHtml propagates generic exception', function () {
    $op = new NfseDanfseRenderer(
        stubBuilder(sampleData()),
        stubHtmlRenderer(new RuntimeException('render boom')),
        stubPdfConverter(),
    );

    expect(fn () => $op->toHtml($this->xml))->toThrow(RuntimeException::class, 'render boom');
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseDanfseRendererTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

Criar `src/Operations/NfseDanfseRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use Dompdf\Exception as DompdfException;
use OwnerPro\Nfsen\Contracts\Driven\BuildsDanfseData;
use OwnerPro\Nfsen\Contracts\Driven\ConvertsHtmlToPdf;
use OwnerPro\Nfsen\Contracts\Driven\RendersDanfseHtml;
use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Exceptions\XmlParseException;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;
use Throwable;

/**
 * Orquestra a renderização local do DANFSE.
 *
 * @api
 */
final readonly class NfseDanfseRenderer implements RendersDanfse
{
    public function __construct(
        private BuildsDanfseData $builder,
        private RendersDanfseHtml $htmlRenderer,
        private ConvertsHtmlToPdf $pdfConverter,
    ) {}

    public function toPdf(string $xmlNfse): DanfseResponse
    {
        try {
            $data = $this->builder->build($xmlNfse);
            $html = $this->htmlRenderer->render($data);
            $pdf = $this->pdfConverter->convert($html);

            return new DanfseResponse(sucesso: true, pdf: $pdf);
        } catch (XmlParseException $e) {
            return $this->failure('XML da NFS-e inválido ou malformado.', $e);
        } catch (DompdfException $e) {
            return $this->failure('Falha ao renderizar o PDF.', $e);
        } catch (Throwable $e) {
            return $this->failure('Erro inesperado ao gerar DANFSE.', $e);
        }
    }

    public function toHtml(string $xmlNfse): string
    {
        $data = $this->builder->build($xmlNfse);

        return $this->htmlRenderer->render($data);
    }

    private function failure(string $descricao, Throwable $e): DanfseResponse
    {
        return new DanfseResponse(
            sucesso: false,
            erros: [new ProcessingMessage(descricao: $descricao, complemento: $e->getMessage())],
        );
    }
}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Unit/Operations/NfseDanfseRendererTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/NfseDanfseRenderer.php tests/Unit/Operations/NfseDanfseRendererTest.php
git commit -m "feat(danfse): add NfseDanfseRenderer operation"
```

---

## Task 20: Wire up `NfsenClient::danfe()` + integração end-to-end

**Files:**
- Modify: `src/NfsenClient.php`
- Test: `tests/Feature/NfsenClientDanfeTest.php`

- [ ] **Step 1: Criar teste de integração end-to-end**

Criar `tests/Feature/NfsenClientDanfeTest.php`:

```php
<?php

use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use Smalot\PdfParser\Parser as PdfParser;

beforeEach(function () {
    $this->xml = (string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-autorizada.xml');

    // Usa o helper makeNfsenClient() (tests/helpers.php) — cria NfsenClient pronto
    // com cert fake + senha correta. Prefeitura é irrelevante para danfe() (que só
    // consome XML; não bate em nenhum endpoint).
    $this->client = makeNfsenClient();
});

it('generates DANFSE PDF end-to-end', function () {
    $resp = $this->client->danfe()->toPdf($this->xml);

    expect($resp)->toBeInstanceOf(DanfseResponse::class);
    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->toStartWith('%PDF-');
});

it('generated PDF contains chave de acesso and emitente', function () {
    $resp = $this->client->danfe()->toPdf($this->xml);

    $text = (new PdfParser())->parseContent($resp->pdf)->getText();

    expect($text)->toContain('3303302112233450000195000000000000100000000001');
    expect($text)->toContain('EMPRESA EXEMPLO DESENVOLVIMENTO');
});

it('returns failure DanfseResponse for malformed XML', function () {
    $resp = $this->client->danfe()->toPdf('<not-xml');

    expect($resp->sucesso)->toBeFalse();
    expect($resp->pdf)->toBeNull();
    expect($resp->erros[0]->descricao)->toBe('XML da NFS-e inválido ou malformado.');
});

it('toHtml returns HTML string', function () {
    $html = $this->client->danfe()->toHtml($this->xml);

    expect($html)->toContain('DANFSe');
    expect($html)->toContain('3303302112233450000195000000000000100000000001');
});

it('toHtml throws on malformed XML', function () {
    expect(fn () => $this->client->danfe()->toHtml('<not-xml'))
        ->toThrow(\OwnerPro\Nfsen\Exceptions\XmlParseException::class);
});

it('applies MunicipalityBranding in rendered PDF', function () {
    $config = new DanfseConfig(
        municipality: new MunicipalityBranding(
            name: 'Município de Teste',
            department: 'Secretaria X',
            email: 'teste@example.com',
        ),
    );

    $html = $this->client->danfe($config)->toHtml($this->xml);

    expect($html)->toContain('Município de Teste');
    expect($html)->toContain('Secretaria X');
});
```

- [ ] **Step 2: Rodar**

Run: `./vendor/bin/pest tests/Feature/NfsenClientDanfeTest.php`
Expected: FAIL (método `danfe()` não existe no `NfsenClient`).

- [ ] **Step 3: Adicionar método `danfe()` no `NfsenClient`**

Adicionar os use statements em `src/NfsenClient.php` (após os existentes):

```php
use OwnerPro\Nfsen\Adapters\BaconQrCodeGenerator;
use OwnerPro\Nfsen\Adapters\DanfseDataBuilder;
use OwnerPro\Nfsen\Adapters\DanfseHtmlRenderer;
use OwnerPro\Nfsen\Adapters\DompdfHtmlToPdfConverter;
use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Operations\NfseDanfseRenderer;
```

Adicionar o método no final da classe (antes do `}` final), após `consultar()`:

```php
public function danfe(?DanfseConfig $config = null): RendersDanfse
{
    return new NfseDanfseRenderer(
        new DanfseDataBuilder(),
        new DanfseHtmlRenderer(
            new BaconQrCodeGenerator(),
            $config ?? new DanfseConfig(),
        ),
        new DompdfHtmlToPdfConverter(),
    );
}
```

- [ ] **Step 4: Rodar**

Run: `./vendor/bin/pest tests/Feature/NfsenClientDanfeTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/NfsenClient.php tests/Feature/NfsenClientDanfeTest.php
git commit -m "feat(danfse): wire NfsenClient::danfe() entry point"
```

---

## Task 21: Configurar exclusões de análise estática e coverage

**Files:**
- Modify: `phpunit.xml`
- Modify: `phpstan.neon`
- Modify: `psalm.xml`

- [ ] **Step 1: Excluir template PHP do coverage no `phpunit.xml`**

Modificar `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

Note: `storage/danfse/template.php` **não está em `src/`**, então já está fora do coverage por default. Nenhuma mudança necessária no `phpunit.xml`.

- [ ] **Step 2: Excluir template do `phpstan.neon`**

O template também não está em `src/`, então o `phpstan` (path `src`) não o analisa. Se o `larastan`/`phpstan` reclamar de algo dentro de `storage/`, adicionar ao `phpstan.neon`:

```neon
parameters:
    excludePaths:
        - storage/danfse/template.php
```

Rodar para verificar:

Run: `./vendor/bin/phpstan analyse`
Expected: no errors.

Se aparecerem erros relacionados ao template, adicionar `excludePaths` conforme acima.

- [ ] **Step 3: Excluir template do `psalm.xml` (se necessário)**

Psalm analisa `src` por default, template está em `storage/` — fora do escopo.

Run: `./vendor/bin/psalm --taint-analysis`
Expected: no errors relacionados ao DANFSE.

Se `psalm --taint-analysis` marcar `DanfseConfig::pathToDataUri` como sink tainted, adicionar suppression no arquivo (dentro da docstring do método):

```php
/**
 * @psalm-taint-escape file
 */
```

- [ ] **Step 4: Commit (se houve mudanças)**

```bash
git add phpstan.neon psalm.xml src/Danfse/DanfseConfig.php src/Danfse/MunicipalityBranding.php
git commit -m "chore(danfse): exclude template from static analysis"
```

Se nenhuma mudança foi necessária, pular o commit.

---

## Task 22: Atualizar README e CHANGELOG

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Adicionar seção no `README.md`**

Abrir `README.md` e adicionar após a última seção de funcionalidades (ou antes de "Créditos/Licença"):

````markdown
## Renderização local do DANFSE

O SDK gera o DANFSE (PDF ou HTML) localmente a partir do XML da NFS-e autorizada, útil como alternativa quando o endpoint ADN oficial está indisponível ou quando você precisa renderizar offline.

### Uso básico

```php
use OwnerPro\Nfsen\NfsenClient;

$client = NfsenClient::for($pfx, $senha, $prefeitura);
$response = $client->emitir($dps);

$pdf = $client->danfe()->toPdf($response->xml);
file_put_contents('danfse.pdf', $pdf->pdf);
```

### Customização: logo da empresa e identificação do município

```php
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

$config = new DanfseConfig(
    logoPath: '/caminho/para/logo.png',
    municipality: new MunicipalityBranding(
        name: 'Município de Canela',
        department: '(54) 3282-5155',
        email: 'issqn@canela.rs.gov.br',
        logoPath: '/caminho/para/brasao.png',
    ),
);

$pdf = $client->danfe($config)->toPdf($response->xml);
```

### Debug: obter o HTML intermediário

```php
$html = $client->danfe()->toHtml($response->xml);
file_put_contents('danfse.html', $html);
```

### Atribuição

A renderização do DANFSE foi portada da biblioteca [`andrevabo/danfse-nacional`](https://github.com/andrevabo/danfse-nacional) (MIT) e adaptada à arquitetura deste SDK. A tabela de municípios IBGE vem de [`kelvins/municipios-brasileiros`](https://github.com/kelvins/municipios-brasileiros) (MIT).
````

- [ ] **Step 2: Adicionar entrada no `CHANGELOG.md`**

No topo do `CHANGELOG.md`, adicionar (adaptando a formatação existente):

```markdown
## [Unreleased]

### Added

- Renderização local do DANFSE (PDF e HTML) a partir do XML da NFS-e autorizada via `NfsenClient::danfe($config)->toPdf($xml)` / `->toHtml($xml)`. Alternativa ao endpoint ADN oficial quando este estiver indisponível.
- Customização via `DanfseConfig` (logo de empresa) e `MunicipalityBranding` (identificação do município emissor).
- Métodos `label()` e `labelOf(?string)` nos enums `OpSimpNac`, `RegApTribSN`, `RegEspTrib`, `TpRetISSQN`, `TribISSQN` e `NfseAmbiente`.
- Exceção `XmlParseException`.
```

- [ ] **Step 3: Commit**

```bash
git add README.md CHANGELOG.md
git commit -m "docs(danfse): document local DANFSE rendering"
```

---

## Task 23: Quality gate completo

- [ ] **Step 1: Rodar suite completa com coverage**

Run: `./vendor/bin/pest --coverage --min=100 --parallel`
Expected: PASS, coverage ≥ 100%.

Se coverage não fechar em 100%, identificar linhas descobertas e adicionar testes. Linhas defensivas comprovadamente inatingíveis podem ser cobertas com `@codeCoverageIgnore` **apenas com justificativa**.

- [ ] **Step 2: Rodar mutation testing**

Run: `./vendor/bin/pest --mutate --min=100 --parallel`
Expected: MSI (mutation score) ≥ 100%.

Mutantes sobreviventes em `Formatter` e builder são comuns — adicionar assertions. Se algum mutante genuinamente inatingível (ex.: `@pest-mutate-ignore UnwrapStrVal — reason: defensive cast on invariant`), documentar inline.

- [ ] **Step 3: Rodar type coverage**

Run: `./vendor/bin/pest --type-coverage --min=100`
Expected: 100%.

- [ ] **Step 4: Rodar análise estática**

Run: `./vendor/bin/phpstan analyse`
Expected: no errors.

- [ ] **Step 5: Rodar security analysis**

Run: `./vendor/bin/psalm --taint-analysis`
Expected: no errors.

- [ ] **Step 6: Rodar Rector (dry-run)**

Run: `./vendor/bin/rector --dry-run`
Expected: no changes suggested (ou apenas cosméticos).

Se sugerir refactors, avaliar caso a caso — aplicar os aplicáveis.

- [ ] **Step 7: Rodar Pint (formatação)**

Run: `./vendor/bin/pint -p`
Expected: no changes (ou auto-format aplicado).

Se aplicou mudanças, commit:

```bash
git add -A
git commit -m "style: apply pint formatting"
```

- [ ] **Step 8: Rodar suite completa UMA VEZ MAIS após formatações**

Run: `./vendor/bin/pest --coverage --min=100 --parallel`
Expected: PASS.

- [ ] **Step 9: Commit final (se aplicável)**

Se nenhum ajuste adicional, verificar árvore limpa:

```bash
git status
```
Expected: `nothing to commit, working tree clean`.

---

## Checklist final de entrega

- [ ] Todos os 24 tasks completos com commits (1-16, 16.5, 17-23)
- [ ] `./vendor/bin/pest --coverage --min=100 --parallel` passa
- [ ] `./vendor/bin/pest --mutate --min=100 --parallel` passa
- [ ] `./vendor/bin/pest --type-coverage --min=100` passa
- [ ] `./vendor/bin/phpstan analyse` passa
- [ ] `./vendor/bin/psalm --taint-analysis` passa
- [ ] `./vendor/bin/rector --dry-run` limpo
- [ ] `./vendor/bin/pint -p` limpo
- [ ] `README.md` documenta `danfe()`
- [ ] `CHANGELOG.md` atualizado
- [ ] `composer.lock` **não foi commitado** (library ignore policy)
- [ ] Todas as strings user-facing em PT-BR
- [ ] Headers de atribuição MIT nos arquivos portados (`Formatter`, `DanfseConfig`, `MunicipalityBranding`, template)
