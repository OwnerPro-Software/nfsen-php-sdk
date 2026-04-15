# Auto-geração de DANFSE PDF — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Anexar DANFSE PDF ao `NfseResponse` nas operações `emitir`/`emitirDecisaoJudicial`/`substituir`/`consultar()->nfse()` quando o cliente configurar; refatorar `::danfe()` → `::danfse()` aceitando array payload.

**Architecture:** Decoradores opcionais sobre os driving ports (`EmitterWithDanfse`, `SubstitutorWithDanfse`, `ConsulterWithDanfse`), injetados em `NfsenClient::forStandalone()` quando o parâmetro `danfse` é fornecido. Core hexagonal (`NfseEmitter`, `NfseSubstitutor`, `NfseConsulter`) permanece inalterado. Validação de shape schema-like em `DanfseConfig::fromArray` + `MunicipalityBranding::fromArray` via trait `ValidatesArrayShape`.

**Tech Stack:** PHP 8.3+, Pest 4 + Mutation Testing, Orchestra Testbench, PHPStan (max), Psalm (taint), Rector, Pint, dompdf, bacon-qr-code, smalot/pdfparser (dev).

**Convenção de commits:** Por solicitação do autor, **não há commits intermediários**. Toda a implementação é concluída em um único commit final (Task 15). Pule os passos de commit em cada tarefa — eles aparecem aqui apenas como marcadores lógicos de completude.

**Spec:** `docs/plans/2026-04-15-auto-danfse-pdf-design.md`.

---

## Preflight (verificar antes de iniciar Task 1)

Fixtures reutilizados em múltiplas tasks. Se algum faltar, tarefas de coverage e feature explodem com `file-not-found` antes do bug real aparecer. Rodar:

```bash
ls -la \
    tests/fixtures/danfse/tiny-logo.png \
    tests/fixtures/danfse/nfse-autorizada.xml \
    tests/fixtures/certs/fake.pfx \
    storage/danfse/logo-nfse.png
```

Todos os 4 arquivos devem existir (criados no commit `e054601`). Onde cada um é usado:

| Fixture                                | Tasks que consomem                             |
| -------------------------------------- | ---------------------------------------------- |
| `tests/fixtures/danfse/tiny-logo.png`  | `MunicipalityBrandingFromArrayTest` (Task 2), `DanfseConfigFromArrayTest` (Task 3) |
| `tests/fixtures/danfse/nfse-autorizada.xml` | `NfsenClientAutoDanfseTest` (Task 13) via `makeDanfseAutorizadoApiResponse()` |
| `tests/fixtures/certs/fake.pfx`        | `NfsenClientAutoDanfseTest` (Task 13) + tests existentes |
| `storage/danfse/logo-nfse.png`         | `NfsenClientDanfseTest` (Task 9) — asserção data URI default |

**Invariantes do template DANFSE e do core** — rodar os greps abaixo antes da Task 1. Todos devem retornar matches:

```bash
# 1. Guarda do bloco município (defesa em profundidade depende disso):
grep -n 'if (\$municipality)' storage/danfse/template.php

# 2. Renderização literal do nome do município (teste "Município Tenant X" depende):
grep -n 'municipality->name' storage/danfse/template.php

# 3. Alias DpsDataArray existe (@phpstan-import-type dos decorators depende):
grep -n 'DpsDataArray' src/Dps/DTO/DpsData.php

# 4. Construtor do NfseSubstitutor aceita EmitsNfse raw:
grep -nA 3 'class NfseSubstitutor' src/Operations/NfseSubstitutor.php

# 5. DanfseResponse aceita (sucesso, pdf, erros) + EventsResponse aceita (sucesso, ...):
# (Usa GNU grep BRE alternation \| — confirmado no ambiente; em busybox/minimal usar -E e descartar backslash)
grep -rnA 5 'class DanfseResponse\|class EventsResponse' src/Responses/

# 6. ServiceProvider chama mergeConfigFrom (senão buildFor sem config explode):
grep -n 'mergeConfigFrom' src/NfsenServiceProvider.php
```

Se qualquer grep retornar vazio, **parar** — o código divergiu da spec; abrir ticket antes de seguir.

**PHP: `abstract private` em trait** — confirmado funcional no PHP 8.3 usado pelo projeto (método `renderer()` do trait `AttachesDanfsePdf`). `phpstan.neon` tem `level: 10` (verificado) — aceita a construção.

**Validação antecipada de phpstan (opcional mas recomendada)**: depois de Task 5 Step 5 (trait + testes verdes), rodar só o escopo do novo arquivo antes de seguir:

```bash
./vendor/bin/phpstan analyse src/Operations/Decorators/Concerns/AttachesDanfsePdf.php
```

Pega regressão cedo em vez de no quality gate final (Task 15 Step 5).

---

## Estrutura de arquivos a criar/modificar

**Novos:**

- `src/Danfse/Concerns/ValidatesArrayShape.php` — trait compartilhado de validação
- `src/Operations/Decorators/EmitterWithDanfse.php`
- `src/Operations/Decorators/SubstitutorWithDanfse.php`
- `src/Operations/Decorators/ConsulterWithDanfse.php`
- `src/Operations/Decorators/Concerns/AttachesDanfsePdf.php`
- `tests/Unit/Danfse/ValidatesArrayShapeTest.php`
- `tests/Unit/Danfse/DanfseConfigFromArrayTest.php`
- `tests/Unit/Danfse/MunicipalityBrandingFromArrayTest.php`
- `tests/Unit/Operations/Decorators/EmitterWithDanfseTest.php`
- `tests/Unit/Operations/Decorators/SubstitutorWithDanfseTest.php`
- `tests/Unit/Operations/Decorators/ConsulterWithDanfseTest.php`
- `tests/Unit/Operations/Decorators/AttachesDanfsePdfTest.php`
- `tests/Feature/NfsenClientAutoDanfseTest.php`
- `tests/Fakes/FakeEmitsNfse.php`
- `tests/Fakes/FakeSubstitutesNfse.php`
- `tests/Fakes/FakeConsultsNfse.php`
- `tests/Fakes/FakeRendersDanfse.php`
- `tests/Unit/NfsenClientIsDanfseEnabledTest.php` — cobertura do helper público (Task 11 Step 2)

**Modificados:**

- `src/Danfse/DanfseConfig.php` — adicionar `fromArray()`, `buildMunicipality()`, trait
- `src/Danfse/MunicipalityBranding.php` — adicionar `fromArray()`, trait, validação de `name`
- `src/Responses/NfseResponse.php` — novos campos `pdf` (nullable string) e `pdfErrors` (list) no fim
- `src/NfsenClient.php` — parâmetro `$danfse` em `for()/forStandalone()`, rename `danfe()` → `danfse()`, aceita array
- `src/NfsenServiceProvider.php` — repassar config `danfse` para `forStandalone()`
- `config/nfsen.php` — novo bloco `danfse` com ternário em `municipality`
- `tests/Feature/NfsenClientDanfeTest.php` → renomeado para `NfsenClientDanfseTest.php`, `danfe` → `danfse`
- `README.md` — nova seção + rename
- `CHANGELOG.md` — entrada
- `phpunit.xml` — (conferir se `Unit/Danfse` e `Unit/Operations/Decorators` são pegos pelos globs atuais; provavelmente sim pois o source já é `<directory>src</directory>`)

---

## Task 1: Trait `ValidatesArrayShape` + teste unit

**Files:**
- Create: `src/Danfse/Concerns/ValidatesArrayShape.php`
- Create: `tests/Unit/Danfse/ValidatesArrayShapeTest.php`

- [ ] **Step 1: Criar teste falhando**

Arquivo: `tests/Unit/Danfse/ValidatesArrayShapeTest.php`

```php
<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Danfse\Concerns\ValidatesArrayShape;

covers(ValidatesArrayShape::class);

final class ValidatesArrayShapeHarness
{
    use ValidatesArrayShape {
        rejectUnknownKeys as public;
    }
}

it('passa silencioso quando todas as chaves estão na whitelist', function () {
    expect(fn () => ValidatesArrayShapeHarness::rejectUnknownKeys(
        ['a' => 1, 'b' => 2],
        ['a', 'b', 'c'],
        'ctx',
    ))->not->toThrow(InvalidArgumentException::class);
});

it('passa silencioso quando array vazio', function () {
    expect(fn () => ValidatesArrayShapeHarness::rejectUnknownKeys([], ['a', 'b'], 'ctx'))
        ->not->toThrow(InvalidArgumentException::class);
});

it('lança quando há uma chave desconhecida', function () {
    ValidatesArrayShapeHarness::rejectUnknownKeys(
        ['a' => 1, 'foo' => 2],
        ['a', 'b'],
        'danfse',
    );
})->throws(InvalidArgumentException::class, 'danfse: chave(s) desconhecida(s): foo');

it('lança listando todas as chaves desconhecidas', function () {
    ValidatesArrayShapeHarness::rejectUnknownKeys(
        ['a' => 1, 'foo' => 2, 'bar' => 3],
        ['a'],
        'danfse.municipality',
    );
})->throws(InvalidArgumentException::class, 'danfse.municipality: chave(s) desconhecida(s): foo, bar');
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `./vendor/bin/pest tests/Unit/Danfse/ValidatesArrayShapeTest.php`
Expected: FAIL — classe `ValidatesArrayShape` não existe.

- [ ] **Step 3: Implementar o trait**

Arquivo: `src/Danfse/Concerns/ValidatesArrayShape.php`

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Concerns;

use InvalidArgumentException;

trait ValidatesArrayShape
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowed
     */
    private static function rejectUnknownKeys(array $data, array $allowed, string $context): void
    {
        $unknown = array_values(array_diff(array_keys($data), $allowed));

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                sprintf('%s: chave(s) desconhecida(s): %s', $context, implode(', ', $unknown)),
            );
        }
    }
}
```

- [ ] **Step 4: Rodar e verificar passa**

Run: `./vendor/bin/pest tests/Unit/Danfse/ValidatesArrayShapeTest.php`
Expected: PASS — 4 assertions.

---

## Task 2: `MunicipalityBranding::fromArray()` + testes

**Files:**
- Modify: `src/Danfse/MunicipalityBranding.php`
- Create: `tests/Unit/Danfse/MunicipalityBrandingFromArrayTest.php`

- [ ] **Step 1: Criar teste falhando**

Arquivo: `tests/Unit/Danfse/MunicipalityBrandingFromArrayTest.php`

```php
<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

covers(MunicipalityBranding::class);

it('constrói a partir de array completo', function () {
    $mun = MunicipalityBranding::fromArray([
        'name' => 'São Paulo',
        'department' => 'SF/SUBTES',
        'email' => 'nfse@sp.gov.br',
        'logo_path' => null,
        'logo_data_uri' => 'data:image/png;base64,AAA',
    ]);

    expect($mun->name)->toBe('São Paulo');
    expect($mun->department)->toBe('SF/SUBTES');
    expect($mun->email)->toBe('nfse@sp.gov.br');
    expect($mun->logoDataUri)->toBe('data:image/png;base64,AAA');
});

it('defaults department e email para string vazia quando omitidos', function () {
    $mun = MunicipalityBranding::fromArray(['name' => 'Rio de Janeiro']);

    expect($mun->department)->toBe('');
    expect($mun->email)->toBe('');
    expect($mun->logoDataUri)->toBeNull();
});

it('resolve logo_path para data URI', function () {
    $mun = MunicipalityBranding::fromArray([
        'name' => 'Porto Alegre',
        'logo_path' => __DIR__.'/../../fixtures/danfse/tiny-logo.png',
    ]);

    expect($mun->logoDataUri)->toStartWith('data:image/png;base64,');
});

it('logo_data_uri precedência sobre logo_path', function () {
    $mun = MunicipalityBranding::fromArray([
        'name' => 'Curitiba',
        'logo_path' => __DIR__.'/../../fixtures/danfse/tiny-logo.png',
        'logo_data_uri' => 'data:image/png;base64,OVERRIDE',
    ]);

    expect($mun->logoDataUri)->toBe('data:image/png;base64,OVERRIDE');
});

it('lança em chave desconhecida', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'foo' => 1]);
})->throws(InvalidArgumentException::class, 'danfse.municipality: chave(s) desconhecida(s): foo');

it('lança quando name está ausente', function () {
    MunicipalityBranding::fromArray(['department' => 'X']);
})->throws(InvalidArgumentException::class, 'danfse.municipality.name: obrigatório');

it('lança quando name é string vazia', function () {
    MunicipalityBranding::fromArray(['name' => '']);
})->throws(InvalidArgumentException::class, 'danfse.municipality.name: não pode ser vazio');

it('lança quando name não é string', function () {
    MunicipalityBranding::fromArray(['name' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.name: esperado string');

it('lança quando department não é string', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'department' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.department: esperado string');

it('lança quando email não é string', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'email' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.email: esperado string');

it('lança quando logo_path não é string|null', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'logo_path' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.logo_path: esperado string|null');

it('lança quando logo_data_uri não é string|null', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'logo_data_uri' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.logo_data_uri: esperado string|null');
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `./vendor/bin/pest tests/Unit/Danfse/MunicipalityBrandingFromArrayTest.php`
Expected: FAIL — método `fromArray` não existe.

- [ ] **Step 3: Implementar `fromArray()`**

Arquivo: `src/Danfse/MunicipalityBranding.php` (substituir o conteúdo inteiro)

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use InvalidArgumentException;
use OwnerPro\Nfsen\Danfse\Concerns\ValidatesArrayShape;

/**
 * Identificação do município emissor no cabeçalho do DANFSE.
 *
 * Portado de andrevabo/danfse-nacional (MIT).
 *
 * @api
 */
final readonly class MunicipalityBranding
{
    use ValidatesArrayShape;

    private const ALLOWED_KEYS = ['name', 'department', 'email', 'logo_path', 'logo_data_uri'];

    public ?string $logoDataUri;

    public function __construct(
        public string $name,
        public string $department = '',
        public string $email = '',
        ?string $logoDataUri = null,
        ?string $logoPath = null,
    ) {
        $this->logoDataUri = $logoDataUri
            ?? ($logoPath !== null ? LogoLoader::toDataUri($logoPath) : null);
    }

    /**
     * Constrói MunicipalityBranding a partir de array.
     *
     * Pré-condição: `name` é string não-vazia. O caso de bloco ausente ou `name`
     * nulo/vazio é filtrado upstream por `DanfseConfig::buildMunicipality()`.
     * Chamadas diretas devem fornecer `name` válido.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        self::rejectUnknownKeys($data, self::ALLOWED_KEYS, 'danfse.municipality');

        if (! array_key_exists('name', $data)) {
            throw new InvalidArgumentException('danfse.municipality.name: obrigatório');
        }

        $name = $data['name'];
        if (! is_string($name)) {
            throw new InvalidArgumentException('danfse.municipality.name: esperado string');
        }
        if ($name === '') {
            throw new InvalidArgumentException('danfse.municipality.name: não pode ser vazio');
        }

        $department = $data['department'] ?? '';
        if (! is_string($department)) {
            throw new InvalidArgumentException('danfse.municipality.department: esperado string');
        }

        $email = $data['email'] ?? '';
        if (! is_string($email)) {
            throw new InvalidArgumentException('danfse.municipality.email: esperado string');
        }

        $logoPath = $data['logo_path'] ?? null;
        if ($logoPath !== null && ! is_string($logoPath)) {
            throw new InvalidArgumentException('danfse.municipality.logo_path: esperado string|null');
        }

        $logoDataUri = $data['logo_data_uri'] ?? null;
        if ($logoDataUri !== null && ! is_string($logoDataUri)) {
            throw new InvalidArgumentException('danfse.municipality.logo_data_uri: esperado string|null');
        }

        return new self(
            name: $name,
            department: $department,
            email: $email,
            logoDataUri: $logoDataUri,
            logoPath: $logoPath,
        );
    }
}
```

- [ ] **Step 4: Rodar e verificar passa**

Run: `./vendor/bin/pest tests/Unit/Danfse/MunicipalityBrandingFromArrayTest.php`
Expected: PASS — 12 testes.

---

## Task 3: `DanfseConfig::fromArray()` + `buildMunicipality()` + testes

**Files:**
- Modify: `src/Danfse/DanfseConfig.php`
- Create: `tests/Unit/Danfse/DanfseConfigFromArrayTest.php`

- [ ] **Step 1: Criar teste falhando**

Arquivo: `tests/Unit/Danfse/DanfseConfigFromArrayTest.php`

```php
<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

covers(DanfseConfig::class);

it('constrói defaults a partir de array vazio', function () {
    $cfg = DanfseConfig::fromArray([]);

    expect($cfg)->toBeInstanceOf(DanfseConfig::class);
    expect($cfg->municipality)->toBeNull();
    // Default carrega logo padrão embutido do pacote; não-null esperado.
    expect($cfg->logoDataUri)->not->toBeNull();
});

it('logo_path false suprime o logo', function () {
    $cfg = DanfseConfig::fromArray(['logo_path' => false]);

    expect($cfg->logoDataUri)->toBeNull();
});

it('logo_data_uri precedência sobre logo_path', function () {
    $cfg = DanfseConfig::fromArray([
        'logo_path' => __DIR__.'/../../fixtures/danfse/tiny-logo.png',
        'logo_data_uri' => 'data:image/png;base64,OVERRIDE',
    ]);

    expect($cfg->logoDataUri)->toBe('data:image/png;base64,OVERRIDE');
});

it('carrega municipality quando name é string válida', function () {
    $cfg = DanfseConfig::fromArray([
        'municipality' => [
            'name' => 'Curitiba',
            'department' => 'PGM',
        ],
    ]);

    expect($cfg->municipality)->toBeInstanceOf(MunicipalityBranding::class);
    expect($cfg->municipality?->name)->toBe('Curitiba');
});

it('chave enabled é ignorada (gate Laravel)', function () {
    $cfg = DanfseConfig::fromArray([
        'enabled' => true,
        'logo_path' => false,
    ]);

    expect($cfg->logoDataUri)->toBeNull();
});

it('municipality ausente → null', function () {
    $cfg = DanfseConfig::fromArray(['logo_path' => false]);
    expect($cfg->municipality)->toBeNull();
});

it('municipality: null explícito → null', function () {
    $cfg = DanfseConfig::fromArray(['logo_path' => false, 'municipality' => null]);
    expect($cfg->municipality)->toBeNull();
});

it('defesa em profundidade: municipality com name null → null', function () {
    $cfg = DanfseConfig::fromArray([
        'logo_path' => false,
        'municipality' => ['name' => null, 'department' => 'X'],
    ]);

    expect($cfg->municipality)->toBeNull();
});

it('defesa em profundidade: municipality com name string vazia → null', function () {
    $cfg = DanfseConfig::fromArray([
        'logo_path' => false,
        'municipality' => ['name' => '', 'department' => 'X'],
    ]);

    expect($cfg->municipality)->toBeNull();
});

it('lança em chave desconhecida no root', function () {
    DanfseConfig::fromArray(['logo_paht' => 'x']);
})->throws(InvalidArgumentException::class, 'danfse: chave(s) desconhecida(s): logo_paht');

it('lança quando logo_path não é string|false|null', function () {
    DanfseConfig::fromArray(['logo_path' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.logo_path: esperado string|false|null');

it('lança quando logo_data_uri não é string|null', function () {
    DanfseConfig::fromArray(['logo_data_uri' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.logo_data_uri: esperado string|null');

it('lança quando municipality não é array', function () {
    DanfseConfig::fromArray(['municipality' => 'string']);
})->throws(InvalidArgumentException::class, 'danfse.municipality: esperado array|null');
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `./vendor/bin/pest tests/Unit/Danfse/DanfseConfigFromArrayTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar `fromArray()` + `buildMunicipality()`**

Arquivo: `src/Danfse/DanfseConfig.php` (substituir conteúdo inteiro)

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use InvalidArgumentException;
use OwnerPro\Nfsen\Danfse\Concerns\ValidatesArrayShape;

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
    use ValidatesArrayShape;

    // 'enabled' é aceito como no-op — é o gate de NfsenClient::for() lendo config Laravel
    // e chega até aqui quando o array é repassado. Dentro de fromArray não tem efeito.
    private const ALLOWED_KEYS = ['enabled', 'logo_path', 'logo_data_uri', 'municipality'];

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
            ?? ($logoPath !== null ? LogoLoader::toDataUri($logoPath) : $this->defaultLogoDataUri());
    }

    /**
     * @param  array<string, mixed>  $data
     */
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
            logoDataUri: $logoDataUri,
            logoPath: $logoPath,
            municipality: $municipality,
        );
    }

    private static function buildMunicipality(mixed $raw): ?MunicipalityBranding
    {
        if ($raw === null) {
            return null;
        }

        if (! is_array($raw)) {
            throw new InvalidArgumentException('danfse.municipality: esperado array|null');
        }

        // Defesa em profundidade: config Laravel parcial (name null/'') vira ausência.
        $name = $raw['name'] ?? null;
        if ($name === null || $name === '') {
            return null;
        }

        return MunicipalityBranding::fromArray($raw);
    }

    private function defaultLogoDataUri(): ?string
    {
        $path = __DIR__.'/../../storage/danfse/logo-nfse.png';

        return is_readable($path) ? LogoLoader::toDataUri($path) : null;
    }
}
```

- [ ] **Step 4: Rodar e verificar passa**

Run: `./vendor/bin/pest tests/Unit/Danfse/DanfseConfigFromArrayTest.php`
Expected: PASS — 13 testes.

- [ ] **Step 5: Rodar todos os testes Danfse (regressão)**

Run: `./vendor/bin/pest tests/Unit/Danfse/`
Expected: PASS — todos os anteriores continuam verdes.

---

## Task 4: Adicionar `pdf` + `pdfErrors` no `NfseResponse`

**Files:**
- Modify: `src/Responses/NfseResponse.php`

- [ ] **Step 1: Estender `NfseResponse` com novos campos no fim**

Arquivo: `src/Responses/NfseResponse.php` (substituir conteúdo inteiro)

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

/**
 * @api
 */
final readonly class NfseResponse
{
    /**
     * @param  list<ProcessingMessage>  $alertas
     * @param  list<ProcessingMessage>  $erros
     * @param  list<ProcessingMessage>  $pdfErrors
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

- [ ] **Step 2: Rodar suite completa para confirmar zero regressão**

Run: `./vendor/bin/pest --parallel`
Expected: PASS — todos os call sites de `NfseResponse` usam named args; adição no fim não quebra nada.

Se algum teste falhar por positional args: substituir por named args. Não alterar ordem dos campos existentes.

---

## Task 5: Trait `AttachesDanfsePdf`

**Files:**
- Create: `src/Operations/Decorators/Concerns/AttachesDanfsePdf.php`
- Create: `tests/Unit/Operations/Decorators/AttachesDanfsePdfTest.php`
- Create: `tests/Fakes/FakeRendersDanfse.php`

- [ ] **Step 1: Criar fake `RendersDanfse`**

Arquivo: `tests/Fakes/FakeRendersDanfse.php`

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;

final class FakeRendersDanfse implements RendersDanfse
{
    public int $toPdfCalls = 0;

    /** @var list<string> */
    public array $xmlsReceived = [];

    public function __construct(
        private readonly DanfseResponse $nextResponse = new DanfseResponse(
            sucesso: true,
            pdf: '%PDF-1.4 fake',
        ),
    ) {}

    public function toPdf(string $xmlNfse): DanfseResponse
    {
        $this->toPdfCalls++;
        $this->xmlsReceived[] = $xmlNfse;

        return $this->nextResponse;
    }

    public function toHtml(string $xmlNfse): string
    {
        return '<html>fake</html>';
    }

    public static function failing(string $descricao = 'render falhou'): self
    {
        return new self(new DanfseResponse(
            sucesso: false,
            erros: [new ProcessingMessage(descricao: $descricao)],
        ));
    }
}
```

- [ ] **Step 2: Autoload dos fakes (verificado previamente)**

`composer.json` mapeia `OwnerPro\\Nfsen\\Tests\\` → `tests/` via PSR-4 (verificado: seção `autoload-dev.psr-4` já existe). Por isso os fakes vivem em `tests/Fakes/` (capital F — PSR-4 exige case-sensitive match do último segmento). A classe `OwnerPro\Nfsen\Tests\Fakes\FakeRendersDanfse` resolve automaticamente sem mudança em `composer.json`.

Rodar `composer dump-autoload` é obrigatório após criar arquivos novos (repopula o classmap):

```bash
composer dump-autoload
```

- [ ] **Step 3: Criar teste do trait**

Arquivo: `tests/Unit/Operations/Decorators/AttachesDanfsePdfTest.php`

```php
<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Operations\Decorators\Concerns\AttachesDanfsePdf;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;
use OwnerPro\Nfsen\Tests\Fakes\FakeRendersDanfse;

covers(AttachesDanfsePdf::class);

// Harness mínimo expondo attachPdf() para testes isolados.
// Implementa renderer() exigido pelo trait (abstract private).
function makeAttacher(RendersDanfse $r): object
{
    return new class($r)
    {
        use AttachesDanfsePdf {
            attachPdf as public;
        }

        public function __construct(private readonly RendersDanfse $renderer) {}

        private function renderer(): RendersDanfse
        {
            return $this->renderer;
        }
    };
}

it('anexa pdf quando resposta tem sucesso + xml', function () {
    $spy = new FakeRendersDanfse;
    $attacher = makeAttacher($spy);

    $result = $attacher->attachPdf(new NfseResponse(
        sucesso: true,
        chave: 'CHAVE',
        xml: '<xml/>',
    ));

    expect($spy->toPdfCalls)->toBe(1);
    expect($spy->xmlsReceived)->toBe(['<xml/>']);
    expect($result->pdf)->toBe('%PDF-1.4 fake');
    expect($result->pdfErrors)->toBe([]);
    expect($result->sucesso)->toBeTrue();
    expect($result->chave)->toBe('CHAVE');
});

it('não chama renderer quando sucesso é false', function () {
    $spy = new FakeRendersDanfse;
    $attacher = makeAttacher($spy);

    $original = new NfseResponse(
        sucesso: false,
        erros: [new ProcessingMessage(descricao: 'X')],
    );
    $result = $attacher->attachPdf($original);

    expect($spy->toPdfCalls)->toBe(0);
    expect($result)->toBe($original);
});

it('não chama renderer quando xml é null (mesmo com sucesso)', function () {
    $spy = new FakeRendersDanfse;
    $attacher = makeAttacher($spy);

    $original = new NfseResponse(sucesso: true, xml: null);
    $result = $attacher->attachPdf($original);

    expect($spy->toPdfCalls)->toBe(0);
    expect($result)->toBe($original);
});

it('popula pdfErrors quando render falha', function () {
    $spy = FakeRendersDanfse::failing('render quebrou');
    $attacher = makeAttacher($spy);

    $result = $attacher->attachPdf(new NfseResponse(
        sucesso: true,
        chave: 'CHAVE',
        xml: '<xml/>',
    ));

    expect($result->sucesso)->toBeTrue();
    expect($result->chave)->toBe('CHAVE');
    expect($result->pdf)->toBeNull();
    expect($result->pdfErrors)->toHaveCount(1);
    expect($result->pdfErrors[0]->descricao)->toBe('render quebrou');
});

it('preserva todos os campos do NfseResponse original ao anexar pdf', function () {
    $spy = new FakeRendersDanfse;
    $attacher = makeAttacher($spy);

    $result = $attacher->attachPdf(new NfseResponse(
        sucesso: true,
        chave: 'K',
        xml: '<x/>',
        idDps: 'DPS1',
        alertas: [new ProcessingMessage(descricao: 'alert')],
        erros: [],
        tipoAmbiente: 2,
        versaoAplicativo: 'v1',
        dataHoraProcessamento: '2026-04-15T10:00:00',
    ));

    expect($result->chave)->toBe('K');
    expect($result->idDps)->toBe('DPS1');
    expect($result->alertas)->toHaveCount(1);
    expect($result->tipoAmbiente)->toBe(2);
    expect($result->versaoAplicativo)->toBe('v1');
    expect($result->dataHoraProcessamento)->toBe('2026-04-15T10:00:00');
    expect($result->pdf)->toBe('%PDF-1.4 fake');
});
```

- [ ] **Step 4: Rodar e verificar falha**

Run: `./vendor/bin/pest tests/Unit/Operations/Decorators/AttachesDanfsePdfTest.php`
Expected: FAIL — trait não existe.

- [ ] **Step 5: Criar o trait**

Arquivo: `src/Operations/Decorators/Concerns/AttachesDanfsePdf.php`

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations\Decorators\Concerns;

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * Anexa PDF e erros de render a um NfseResponse.
 *
 * Classes hospedeiras devem implementar `renderer()` retornando seu `RendersDanfse`.
 */
trait AttachesDanfsePdf
{
    abstract private function renderer(): RendersDanfse;

    private function attachPdf(NfseResponse $r): NfseResponse
    {
        if (! $r->sucesso || $r->xml === null) {
            return $r;
        }

        $danfse = $this->renderer()->toPdf($r->xml);

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

- [ ] **Step 6: Rodar e verificar passa**

Run: `./vendor/bin/pest tests/Unit/Operations/Decorators/AttachesDanfsePdfTest.php`
Expected: PASS — 5 testes.

---

## Task 6: `EmitterWithDanfse` + testes

**Files:**
- Create: `tests/Fakes/FakeEmitsNfse.php`
- Create: `src/Operations/Decorators/EmitterWithDanfse.php`
- Create: `tests/Unit/Operations/Decorators/EmitterWithDanfseTest.php`

- [ ] **Step 1: Criar fake `EmitsNfse`**

Arquivo: `tests/Fakes/FakeEmitsNfse.php`

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\EmitsNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Responses\NfseResponse;

final class FakeEmitsNfse implements EmitsNfse
{
    public int $emitirCalls = 0;

    public int $emitirDecisaoJudicialCalls = 0;

    public function __construct(
        private readonly NfseResponse $emitirResponse = new NfseResponse(
            sucesso: true,
            chave: 'CHAVE_EMIT',
            xml: '<nfse id="emit"/>',
        ),
        private readonly NfseResponse $decisaoResponse = new NfseResponse(
            sucesso: true,
            chave: 'CHAVE_DECISAO',
            xml: '<nfse id="decisao"/>',
        ),
    ) {}

    public function emitir(DpsData|array $data): NfseResponse
    {
        $this->emitirCalls++;

        return $this->emitirResponse;
    }

    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        $this->emitirDecisaoJudicialCalls++;

        return $this->decisaoResponse;
    }
}
```

- [ ] **Step 2: Criar testes do decorator**

Arquivo: `tests/Unit/Operations/Decorators/EmitterWithDanfseTest.php`

```php
<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Operations\Decorators\EmitterWithDanfse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Tests\Fakes\FakeEmitsNfse;
use OwnerPro\Nfsen\Tests\Fakes\FakeRendersDanfse;

covers(EmitterWithDanfse::class);

it('emitir sucesso anexa pdf', function (DpsData $data) {
    $inner = new FakeEmitsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitir($data);

    expect($inner->emitirCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(1);
    expect($resp->chave)->toBe('CHAVE_EMIT');
    expect($resp->pdf)->toBe('%PDF-1.4 fake');
})->with('dpsData');

it('emitirDecisaoJudicial sucesso anexa pdf', function (DpsData $data) {
    $inner = new FakeEmitsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitirDecisaoJudicial($data);

    expect($inner->emitirDecisaoJudicialCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(1);
    expect($resp->chave)->toBe('CHAVE_DECISAO');
    expect($resp->pdf)->toBe('%PDF-1.4 fake');
})->with('dpsData');

it('não chama renderer quando emit retorna falha', function (DpsData $data) {
    $inner = new FakeEmitsNfse(
        emitirResponse: new NfseResponse(sucesso: false),
    );
    $renderer = new FakeRendersDanfse;
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitir($data);

    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBeNull();
    expect($resp->sucesso)->toBeFalse();
})->with('dpsData');

it('não chama renderer quando xml é null', function (DpsData $data) {
    $inner = new FakeEmitsNfse(
        emitirResponse: new NfseResponse(sucesso: true, chave: 'K', xml: null),
    );
    $renderer = new FakeRendersDanfse;
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitir($data);

    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBeNull();
})->with('dpsData');

it('render falha → pdf null, pdfErrors populado, sucesso preservado', function (DpsData $data) {
    $inner = new FakeEmitsNfse;
    $renderer = FakeRendersDanfse::failing('dompdf quebrou');
    $decorator = new EmitterWithDanfse($inner, $renderer);

    $resp = $decorator->emitir($data);

    expect($resp->sucesso)->toBeTrue();
    expect($resp->chave)->toBe('CHAVE_EMIT');
    expect($resp->pdf)->toBeNull();
    expect($resp->pdfErrors)->toHaveCount(1);
    expect($resp->pdfErrors[0]->descricao)->toBe('dompdf quebrou');
})->with('dpsData');
```

- [ ] **Step 3: Rodar e verificar falha**

Run: `./vendor/bin/pest tests/Unit/Operations/Decorators/EmitterWithDanfseTest.php`
Expected: FAIL — classe não existe.

- [ ] **Step 4: Implementar o decorator**

Arquivo: `src/Operations/Decorators/EmitterWithDanfse.php`

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations\Decorators;

use OwnerPro\Nfsen\Contracts\Driving\EmitsNfse;
use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Operations\Decorators\Concerns\AttachesDanfsePdf;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
final readonly class EmitterWithDanfse implements EmitsNfse
{
    use AttachesDanfsePdf;

    public function __construct(
        private EmitsNfse $inner,
        private RendersDanfse $renderer,
    ) {}

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitir(DpsData|array $data): NfseResponse
    {
        return $this->attachPdf($this->inner->emitir($data));
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        return $this->attachPdf($this->inner->emitirDecisaoJudicial($data));
    }

    private function renderer(): RendersDanfse
    {
        return $this->renderer;
    }
}
```

- [ ] **Step 5: Rodar e verificar passa**

Run: `./vendor/bin/pest tests/Unit/Operations/Decorators/EmitterWithDanfseTest.php`
Expected: PASS — 5 testes.

---

## Task 7: `SubstitutorWithDanfse` + testes

**Files:**
- Create: `tests/Fakes/FakeSubstitutesNfse.php`
- Create: `src/Operations/Decorators/SubstitutorWithDanfse.php`
- Create: `tests/Unit/Operations/Decorators/SubstitutorWithDanfseTest.php`

- [ ] **Step 1: Criar fake**

Arquivo: `tests/Fakes/FakeSubstitutesNfse.php`

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\SubstitutesNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Responses\NfseResponse;

final class FakeSubstitutesNfse implements SubstitutesNfse
{
    public int $substituirCalls = 0;

    public function __construct(
        private readonly NfseResponse $response = new NfseResponse(
            sucesso: true,
            chave: 'CHAVE_SUBST',
            xml: '<nfse id="subst"/>',
        ),
    ) {}

    public function substituir(
        string $chave,
        DpsData|array $dps,
        CodigoJustificativaSubstituicao|string $codigoMotivo,
        ?string $descricao = null,
    ): NfseResponse {
        $this->substituirCalls++;

        return $this->response;
    }
}
```

- [ ] **Step 2: Criar testes**

Arquivo: `tests/Unit/Operations/Decorators/SubstitutorWithDanfseTest.php`

```php
<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Operations\Decorators\SubstitutorWithDanfse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Tests\Fakes\FakeRendersDanfse;
use OwnerPro\Nfsen\Tests\Fakes\FakeSubstitutesNfse;

covers(SubstitutorWithDanfse::class);

it('substituir sucesso anexa pdf — render chamado exatamente 1x', function (DpsData $data) {
    $inner = new FakeSubstitutesNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new SubstitutorWithDanfse($inner, $renderer);

    $resp = $decorator->substituir(
        'CHAVE_ANTIGA',
        $data,
        CodigoJustificativaSubstituicao::ErroPreenchimento,
        'descr',
    );

    expect($inner->substituirCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(1);  // invariante de wiring: emit interno do substitutor é cru.
    expect($resp->chave)->toBe('CHAVE_SUBST');
    expect($resp->pdf)->toBe('%PDF-1.4 fake');
})->with('dpsData');

it('não chama renderer quando substituir falha', function (DpsData $data) {
    $inner = new FakeSubstitutesNfse(new NfseResponse(sucesso: false));
    $renderer = new FakeRendersDanfse;
    $decorator = new SubstitutorWithDanfse($inner, $renderer);

    $resp = $decorator->substituir(
        'CHAVE',
        $data,
        CodigoJustificativaSubstituicao::ErroPreenchimento,
    );

    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBeNull();
})->with('dpsData');

it('render falha anexa pdfErrors', function (DpsData $data) {
    $inner = new FakeSubstitutesNfse;
    $renderer = FakeRendersDanfse::failing('boom');
    $decorator = new SubstitutorWithDanfse($inner, $renderer);

    $resp = $decorator->substituir(
        'CHAVE',
        $data,
        CodigoJustificativaSubstituicao::ErroPreenchimento,
    );

    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->toBeNull();
    expect($resp->pdfErrors[0]->descricao)->toBe('boom');
})->with('dpsData');
```

- [ ] **Step 3: Rodar e verificar falha**

Run: `./vendor/bin/pest tests/Unit/Operations/Decorators/SubstitutorWithDanfseTest.php`
Expected: FAIL.

- [ ] **Step 4: Implementar o decorator**

Arquivo: `src/Operations/Decorators/SubstitutorWithDanfse.php`

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations\Decorators;

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Contracts\Driving\SubstitutesNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Operations\Decorators\Concerns\AttachesDanfsePdf;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
final readonly class SubstitutorWithDanfse implements SubstitutesNfse
{
    use AttachesDanfsePdf;

    public function __construct(
        private SubstitutesNfse $inner,
        private RendersDanfse $renderer,
    ) {}

    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(
        string $chave,
        DpsData|array $dps,
        CodigoJustificativaSubstituicao|string $codigoMotivo,
        ?string $descricao = null,
    ): NfseResponse {
        return $this->attachPdf($this->inner->substituir($chave, $dps, $codigoMotivo, $descricao));
    }

    private function renderer(): RendersDanfse
    {
        return $this->renderer;
    }
}
```

- [ ] **Step 5: Rodar e verificar passa**

Run: `./vendor/bin/pest tests/Unit/Operations/Decorators/SubstitutorWithDanfseTest.php`
Expected: PASS — 3 testes.

---

## Task 8: `ConsulterWithDanfse` + testes

**Files:**
- Create: `tests/Fakes/FakeConsultsNfse.php`
- Create: `src/Operations/Decorators/ConsulterWithDanfse.php`
- Create: `tests/Unit/Operations/Decorators/ConsulterWithDanfseTest.php`

- [ ] **Step 1: Criar fake**

Arquivo: `tests/Fakes/FakeConsultsNfse.php`

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Tests\Fakes;

use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\EventsResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;

final class FakeConsultsNfse implements ConsultsNfse
{
    public int $nfseCalls = 0;

    public int $dpsCalls = 0;

    public int $danfseCalls = 0;

    public int $eventosCalls = 0;

    public int $verificarDpsCalls = 0;

    public function __construct(
        private readonly NfseResponse $nfseResponse = new NfseResponse(
            sucesso: true,
            chave: 'CHAVE_CONSULT',
            xml: '<nfse id="consult"/>',
        ),
        private readonly NfseResponse $dpsResponse = new NfseResponse(sucesso: true, chave: 'K'),
        private readonly DanfseResponse $danfseResponse = new DanfseResponse(sucesso: true, pdf: '%PDF-official'),
        private readonly EventsResponse $eventosResponse = new EventsResponse(sucesso: true),
        private readonly bool $verificarDpsResponse = true,
    ) {}

    public function nfse(string $chave): NfseResponse
    {
        $this->nfseCalls++;

        return $this->nfseResponse;
    }

    public function dps(string $id): NfseResponse
    {
        $this->dpsCalls++;

        return $this->dpsResponse;
    }

    public function danfse(string $chave): DanfseResponse
    {
        $this->danfseCalls++;

        return $this->danfseResponse;
    }

    public function eventos(
        string $chave,
        TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador,
        int $nSequencial = 1,
    ): EventsResponse {
        $this->eventosCalls++;

        return $this->eventosResponse;
    }

    public function verificarDps(string $id): bool
    {
        $this->verificarDpsCalls++;

        return $this->verificarDpsResponse;
    }
}
```

- [ ] **Step 2: Criar testes**

Arquivo: `tests/Unit/Operations/Decorators/ConsulterWithDanfseTest.php`

```php
<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Operations\Decorators\ConsulterWithDanfse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Tests\Fakes\FakeConsultsNfse;
use OwnerPro\Nfsen\Tests\Fakes\FakeRendersDanfse;

covers(ConsulterWithDanfse::class);

it('nfse() sucesso anexa pdf', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->nfse('CHAVE_X');

    expect($inner->nfseCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(1);
    expect($resp->chave)->toBe('CHAVE_CONSULT');
    expect($resp->pdf)->toBe('%PDF-1.4 fake');
});

it('nfse() falha não chama renderer', function () {
    $inner = new FakeConsultsNfse(new NfseResponse(sucesso: false));
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->nfse('CHAVE');

    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBeNull();
});

it('dps() delega sem chamar renderer', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->dps('DPS1');

    expect($inner->dpsCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->chave)->toBe('K');
});

it('danfse() delega (retorna DanfseResponse oficial) sem chamar renderer', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->danfse('CHAVE');

    expect($inner->danfseCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->pdf)->toBe('%PDF-official');
});

it('eventos() delega sem chamar renderer', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $resp = $decorator->eventos('CHAVE', TipoEvento::CancelamentoPorIniciativaPrestador, 1);

    expect($inner->eventosCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(0);
    expect($resp->sucesso)->toBeTrue();
});

it('verificarDps() delega sem chamar renderer', function () {
    $inner = new FakeConsultsNfse;
    $renderer = new FakeRendersDanfse;
    $decorator = new ConsulterWithDanfse($inner, $renderer);

    $ok = $decorator->verificarDps('DPS1');

    expect($inner->verificarDpsCalls)->toBe(1);
    expect($renderer->toPdfCalls)->toBe(0);
    expect($ok)->toBeTrue();
});
```

- [ ] **Step 3: Rodar e verificar falha**

Run: `./vendor/bin/pest tests/Unit/Operations/Decorators/ConsulterWithDanfseTest.php`
Expected: FAIL.

- [ ] **Step 4: Implementar o decorator**

Arquivo: `src/Operations/Decorators/ConsulterWithDanfse.php`

```php
<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations\Decorators;

use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Operations\Decorators\Concerns\AttachesDanfsePdf;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\EventsResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;

final readonly class ConsulterWithDanfse implements ConsultsNfse
{
    use AttachesDanfsePdf;

    public function __construct(
        private ConsultsNfse $inner,
        private RendersDanfse $renderer,
    ) {}

    public function nfse(string $chave): NfseResponse
    {
        return $this->attachPdf($this->inner->nfse($chave));
    }

    public function dps(string $id): NfseResponse
    {
        return $this->inner->dps($id);
    }

    public function danfse(string $chave): DanfseResponse
    {
        return $this->inner->danfse($chave);
    }

    public function eventos(
        string $chave,
        TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador,
        int $nSequencial = 1,
    ): EventsResponse {
        return $this->inner->eventos($chave, $tipoEvento, $nSequencial);
    }

    public function verificarDps(string $id): bool
    {
        return $this->inner->verificarDps($id);
    }

    private function renderer(): RendersDanfse
    {
        return $this->renderer;
    }
}
```

- [ ] **Step 5: Rodar e verificar passa**

Run: `./vendor/bin/pest tests/Unit/Operations/Decorators/ConsulterWithDanfseTest.php`
Expected: PASS — 6 testes.

---

## Task 9: Rename `danfe()` → `danfse()` + aceitar array + atualizar teste existente

**Files:**
- Modify: `src/NfsenClient.php:153-163`
- Rename: `tests/Feature/NfsenClientDanfeTest.php` → `tests/Feature/NfsenClientDanfseTest.php`

- [ ] **Step 1: Renomear arquivo de teste**

`git mv` ajusta o tracking do git sem exigir commit imediato — consistente com a política de commit único no fim.

```bash
git mv tests/Feature/NfsenClientDanfeTest.php tests/Feature/NfsenClientDanfseTest.php
```

- [ ] **Step 2: Editar `NfsenClient.php` — renomear método + aceitar array**

Abra `src/NfsenClient.php`. Substituir o método `danfe()` (linhas atuais ~153-163):

```php
public function danfse(DanfseConfig|array|null $config = null): RendersDanfse
{
    $resolved = $config instanceof DanfseConfig
        ? $config
        : DanfseConfig::fromArray($config ?? []);

    return self::buildDanfseRenderer($resolved);
}

private static function buildDanfseRenderer(DanfseConfig $config): RendersDanfse
{
    return new NfseDanfseRenderer(
        new DanfseDataBuilder,
        new DanfseHtmlRenderer(
            new BaconQrCodeGenerator,
            $config,
        ),
        new DompdfHtmlToPdfConverter,
    );
}
```

- [ ] **Step 3: Atualizar `tests/Feature/NfsenClientDanfseTest.php`**

No arquivo renomeado, substituir todas ocorrências de `danfe()` por `danfse()`:

Ambiente confirmado como Linux (GNU sed — suporta `-i` sem argumento). Em BSD/macOS seria `sed -i '' 's/.../.../g'`.

```bash
sed -i 's/->danfe(/->danfse(/g' tests/Feature/NfsenClientDanfseTest.php
```

**Auditoria pós-sed** — `sed` só pega o padrão exato `->danfe(`. Resíduos possíveis: descrições de teste (`it('danfe ...')`), comentários, `::danfe`, `$danfe = ...`. `grep 'danfe'` casa `danfse` também — usar regex excludente:

```bash
grep -nE 'danfe([^s]|$)' tests/Feature/NfsenClientDanfseTest.php
```

Substituir manualmente cada ocorrência restante por `danfse` (caso haja).

Garantir que os `use` no topo do arquivo contêm `DanfseConfig` e `MunicipalityBranding` (já existem — L8-9 do original). Confirmar também que `beforeEach()` define `$this->client` e `$this->xml` (existe — L24-31 do original). Adicionar 2 testes novos ao final do arquivo:

```php
it('aceita array payload equivalente a DanfseConfig', function () {
    $respObj = $this->client->danfse(new DanfseConfig(
        municipality: new MunicipalityBranding(name: 'X'),
    ))->toHtml($this->xml);

    $respArr = $this->client->danfse([
        'municipality' => ['name' => 'X'],
    ])->toHtml($this->xml);

    // HTMLs iguais (mesmo template, mesmos dados).
    expect($respArr)->toBe($respObj);
});

it('danfse() sem argumento usa defaults (inclui logo padrão do pacote)', function () {
    $html = $this->client->danfse()->toHtml($this->xml);

    expect($html)->toContain('DANFSe');
    // Default DanfseConfig carrega storage/danfse/logo-nfse.png via data URI.
    expect($html)->toContain('data:image/png;base64,');
});
```

- [ ] **Step 4: Rodar apenas o teste renomeado**

Run: `./vendor/bin/pest tests/Feature/NfsenClientDanfseTest.php`
Expected: PASS.

- [ ] **Step 5: Rodar suite completa para garantir zero quebras**

Run: `./vendor/bin/pest --parallel`
Expected: PASS em tudo. Se algum teste ainda usa `danfe()`: grep no repo (`grep -rn '->danfe(' src tests`) e corrigir.

---

## Task 10: Estender `NfsenClient::forStandalone()` com parâmetro `$danfse`

**Files:**
- Modify: `src/NfsenClient.php` (método `forStandalone()`)

- [ ] **Step 1: Adicionar parâmetro `$danfse` + wiring de decorators**

Abrir `src/NfsenClient.php`. Localizar `forStandalone()`. Adicionar novo parâmetro `array|false|null $danfse = null` como **último** parâmetro (após `validateIdentity`). Atualizar corpo para envolver operações quando `$danfse` é array:

```php
/**
 * @param  array<string, mixed>|false|null  $danfse
 *         - `null` (default): sem auto-render.
 *         - array (incluindo `[]`): ativa auto-render. Array vazio produz `DanfseConfig`
 *           default (logo padrão, sem município). Chave `enabled` dentro do array é
 *           ignorada aqui — só tem efeito em `NfsenClient::for()` lendo config global.
 *         - `false`: sentinel; em `forStandalone()` equivale a `null` (sem auto-render).
 *           Útil em `NfsenClient::for()` para sobrescrever `config.enabled=true`.
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
    $jsonPath = $prefeiturasJsonPath ?? __DIR__.'/../storage/prefeituras.json';
    $schemasPath ??= __DIR__.'/../storage/schemes';

    $prefeituraResolver = new PrefeituraResolver($jsonPath);
    $xsdValidator = new XsdValidator($schemasPath);
    $certManager = new CertificateManager($pfxContent, $senha);
    $effectiveSslVerify = $ambiente === NfseAmbiente::PRODUCAO || $sslVerify;
    $httpClient = new NfseHttpClient($certManager->getCertificate(), $timeout, $connectTimeout, $effectiveSslVerify);

    $signer = new XmlSigner($certManager->getCertificate(), $signingAlgorithm);

    $pipeline = new NfseRequestPipeline(
        ambiente: $ambiente,
        prefeituraResolver: $prefeituraResolver,
        gzipCompressor: new GzipCompressor,
        signer: $signer,
        authorIdentity: $certManager,
        prefeitura: $prefeitura,
        httpClient: $httpClient,
        validateIdentity: $validateIdentity,
    );

    $queryExecutor = new NfseResponsePipeline($httpClient);
    $seFinUrl = $prefeituraResolver->resolveSeFinUrl($prefeitura, $ambiente);
    $adnUrl = $prefeituraResolver->resolveAdnUrl($prefeitura, $ambiente);

    $emitter = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));
    $canceller = new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente);
    // IMPORTANTE: $emitter cru (não decorado). Invariante de wiring — senão PDF renderiza 2x em substituir().
    $substitutor = new NfseSubstitutor($emitter);
    $consulter = new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura);

    if ($danfse === null || $danfse === false) {
        return new self(
            emitter: $emitter,
            canceller: $canceller,
            substitutor: $substitutor,
            consulter: $consulter,
        );
    }

    $renderer = self::buildDanfseRenderer(DanfseConfig::fromArray($danfse));

    return new self(
        emitter: new EmitterWithDanfse($emitter, $renderer),
        canceller: $canceller,
        substitutor: new SubstitutorWithDanfse($substitutor, $renderer),
        consulter: new ConsulterWithDanfse($consulter, $renderer),
    );
}
```

Adicionar os novos imports no topo do arquivo:

```php
use OwnerPro\Nfsen\Operations\Decorators\ConsulterWithDanfse;
use OwnerPro\Nfsen\Operations\Decorators\EmitterWithDanfse;
use OwnerPro\Nfsen\Operations\Decorators\SubstitutorWithDanfse;
```

- [ ] **Step 2: Rodar suite completa**

Run: `./vendor/bin/pest --parallel`
Expected: PASS — comportamento existente preservado (nenhum test passa `$danfse` ainda).

---

## Task 11: Estender `NfsenClient::for()` + sentinel `false`

**Files:**
- Modify: `src/NfsenClient.php` (método `for()`)

- [ ] **Step 1: Adicionar parâmetro `$danfse` + lógica de precedência**

Substituir o método `for()` inteiro:

```php
public static function for(
    #[SensitiveParameter] string $pfxContent,
    #[SensitiveParameter] string $senha,
    string $prefeitura,
    ?NfseAmbiente $ambiente = null,
    array|false|null $danfse = null,
): self {
    // Sentinel: false força desligar, ignora config global.
    if ($danfse === false) {
        return self::buildFor($pfxContent, $senha, $prefeitura, $ambiente, null);
    }

    // null + config global ativo: usa config.
    if ($danfse === null && function_exists('config')) {
        /** @var array<string, mixed>|null $fromConfig */
        $fromConfig = config('nfsen.danfse');
        if (self::isDanfseEnabled($fromConfig)) {
            $danfse = $fromConfig;
        }
    }

    return self::buildFor($pfxContent, $senha, $prefeitura, $ambiente, $danfse);
}

/**
 * Gate DRY usado por `for()` e por `NfsenServiceProvider`.
 *
 * Contrato: config/nfsen.php aplica `(bool)` cast em `enabled`, logo o valor chegando
 * aqui é bool. Strict `=== true` enforces o contrato — se alguém publicar um config
 * com `'enabled' => 1`, o auto-render silenciosamente não ativa. Intencional:
 * falha fechada é mais segura que ativar por coerção frouxa.
 *
 * Visibilidade public obrigatória: consumido por `NfsenServiceProvider`. Marcada como
 * `@api` preemptivamente: `NfsenServiceProvider` é um consumidor externo efetivo do
 * helper (mesma lib, mas acoplamento cross-class). `@api` garante que `tomasvotruba/unused-public`
 * não reclame em Task 15 Step 5 sem precisar de retry-com-edit.
 *
 * @phpstan-assert-if-true array<string, mixed> $block
 *
 * @api
 */
public static function isDanfseEnabled(mixed $block): bool
{
    return is_array($block) && ($block['enabled'] ?? false) === true;
}
```

**Fallback phpstan**: se `@phpstan-assert-if-true` com expressão composta (`is_array && ternary`) falhar em Task 15 Step 5 (level 10 é fussy com narrowing em ternários), reescrever com early-return:

```php
public static function isDanfseEnabled(mixed $block): bool
{
    if (! is_array($block)) {
        return false;
    }

    return ($block['enabled'] ?? false) === true;
}
```

O `@phpstan-assert-if-true` agora narra `$block` para `array` no ramo true trivialmente (phpstan reconhece padrão guard-clause + return expression).

Continuando a edição de `for()` — método `buildFor()` privado:

```php
/**
 * @param  array<string, mixed>|null  $danfse
 */
private static function buildFor(
    #[SensitiveParameter] string $pfxContent,
    #[SensitiveParameter] string $senha,
    string $prefeitura,
    ?NfseAmbiente $ambiente,
    ?array $danfse,
): self {
    // Chave `danfse?` opcional no shape: cobre instalações antigas cujo config/nfsen.php
    // publicado não tem o bloco novo (buildFor não acessa essa chave, mas o ServiceProvider sim).
    if (function_exists('config') && config('nfsen') !== null) {
        /** @var array{ambiente: int|string, timeout: int, connect_timeout: int, signing_algorithm: string, ssl_verify: bool, validate_identity: bool, danfse?: array<string, mixed>} $config */
        $config = config('nfsen');

        return self::forStandalone(
            pfxContent: $pfxContent,
            senha: $senha,
            prefeitura: $prefeitura,
            ambiente: $ambiente ?? NfseAmbiente::fromConfig($config['ambiente']),
            timeout: $config['timeout'],
            signingAlgorithm: $config['signing_algorithm'],
            sslVerify: $config['ssl_verify'],
            connectTimeout: $config['connect_timeout'],
            validateIdentity: $config['validate_identity'],
            danfse: $danfse,
        );
    }

    return self::forStandalone(
        pfxContent: $pfxContent,
        senha: $senha,
        prefeitura: $prefeitura,
        ambiente: $ambiente ?? NfseAmbiente::HOMOLOGACAO,
        danfse: $danfse,
    );
}
```

- [ ] **Step 2: Teste unit dedicado para `isDanfseEnabled`**

Helper público reutilizado pelo ServiceProvider. Precisa cobertura completa das ramificações para satisfazer mutation testing 100%.

Criar arquivo: `tests/Unit/NfsenClientIsDanfseEnabledTest.php`

```php
<?php

declare(strict_types=1);

use OwnerPro\Nfsen\NfsenClient;

covers(NfsenClient::class);

it('retorna true quando array tem enabled === true', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => true]))->toBeTrue();
});

it('retorna false quando block é null', function () {
    expect(NfsenClient::isDanfseEnabled(null))->toBeFalse();
});

it('retorna false quando block não é array', function () {
    expect(NfsenClient::isDanfseEnabled('string'))->toBeFalse();
});

it('retorna false quando enabled ausente', function () {
    expect(NfsenClient::isDanfseEnabled([]))->toBeFalse();
});

it('retorna false quando enabled é false', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => false]))->toBeFalse();
});

it('retorna false quando enabled é 1 (strict check enforces bool contract)', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => 1]))->toBeFalse();
});

it('retorna false quando enabled é string "true" (strict check)', function () {
    expect(NfsenClient::isDanfseEnabled(['enabled' => 'true']))->toBeFalse();
});
```

- [ ] **Step 3: Rodar suite completa**

Run: `./vendor/bin/pest --parallel`
Expected: PASS — testes existentes não passam `$danfse`, comportamento default inalterado; 7 testes novos do helper passam.

---

## Task 12: Atualizar `config/nfsen.php` + `NfsenServiceProvider`

**Files:**
- Modify: `config/nfsen.php`
- Modify: `src/NfsenServiceProvider.php`

- [ ] **Step 1: Atualizar `config/nfsen.php`**

Substituir o conteúdo:

```php
<?php

use OwnerPro\Nfsen\Enums\NfseAmbiente;

return [
    'ambiente' => env('NFSE_AMBIENTE', NfseAmbiente::HOMOLOGACAO->value),
    'prefeitura' => env('NFSE_PREFEITURA', null),
    'certificado' => [
        'path' => env('NFSE_CERT_PATH'),
        'senha' => env('NFSE_CERT_SENHA'),
    ],
    'timeout' => (int) env('NFSE_TIMEOUT', 30),
    'connect_timeout' => (int) env('NFSE_CONNECT_TIMEOUT', 10),
    'signing_algorithm' => env('NFSE_SIGNING_ALGORITHM', 'sha1'),
    'ssl_verify' => (bool) env('NFSE_SSL_VERIFY', true),
    'validate_identity' => (bool) env('NFSE_VALIDATE_IDENTITY', true),

    // DANFSE auto-render. Quando `enabled=true`, `NfsenClient::for()` anexa o PDF
    // ao NfseResponse em emitir/emitirDecisaoJudicial/substituir/consultar()->nfse().
    // Em multi-tenant, passe o array explicitamente em NfsenClient::for(danfse: [...]).
    'danfse' => [
        'enabled' => (bool) env('NFSE_DANFSE_AUTO', false),
        // logo_path: se não setado (null), DanfseConfig usa o logo padrão embutido do pacote.
        // Para emitir sem logo algum, construir o cliente via código: NfsenClient::for(danfse: ['logo_path' => false]).
        // Não há forma de representar `false` via env (sempre string/null), e essa é uma escolha rara.
        'logo_path' => env('NFSE_DANFSE_LOGO_PATH'),
        'logo_data_uri' => env('NFSE_DANFSE_LOGO_DATA_URI'),
        // Bloco municipality é incluído apenas quando MUN_NAME foi SETADO (não apenas "truthy").
        // Usar !== null evita que strings válidas como "0" sejam consideradas falsy e suprimam o bloco.
        'municipality' => env('NFSE_DANFSE_MUN_NAME') !== null ? [
            'name' => env('NFSE_DANFSE_MUN_NAME'),
            'department' => env('NFSE_DANFSE_MUN_DEPT', ''),
            'email' => env('NFSE_DANFSE_MUN_EMAIL', ''),
            'logo_path' => env('NFSE_DANFSE_MUN_LOGO_PATH'),
            'logo_data_uri' => env('NFSE_DANFSE_MUN_LOGO_DATA_URI'),
        ] : null,
    ],
];
```

- [ ] **Step 2: Atualizar `NfsenServiceProvider` para repassar config `danfse`**

Em `src/NfsenServiceProvider.php`, atualizar o phpstan docblock e a chamada a `forStandalone()` dentro do closure do binding:

```php
/**
 * @var array{
 *     ambiente: int|string,
 *     prefeitura: string|null,
 *     certificado: array{
 *         path: string|null,
 *         senha: string|null,
 *     },
 *     timeout: int,
 *     connect_timeout: int,
 *     signing_algorithm: string,
 *     ssl_verify: bool,
 *     validate_identity: bool,
 *     danfse?: array<string, mixed>,
 * } $config
 */
$config = config('nfsen');
```

Ainda dentro do closure, adicionar resolução da flag `enabled` antes do `return`:

```php
// Reusa o gate público NfsenClient::isDanfseEnabled() (declarado em Task 11).
// Única fonte de verdade — mesma regra usada em NfsenClient::for().
// @phpstan-assert-if-true narra `$danfseBlock` para array<string, mixed> no ramo true,
// permitindo passar direto ao parâmetro `danfse: array|false|null`. Se phpstan reclamar
// de "mixed given", adicionar `/** @var array<string, mixed>|null $danfseBlock */` antes.
$danfseBlock = $config['danfse'] ?? null;
$danfsePayload = NfsenClient::isDanfseEnabled($danfseBlock) ? $danfseBlock : null;

return NfsenClient::forStandalone(
    pfxContent: $certContent,
    senha: $certSenha,
    prefeitura: $prefeitura,
    ambiente: NfseAmbiente::fromConfig($config['ambiente']),
    timeout: $config['timeout'],
    signingAlgorithm: $config['signing_algorithm'],
    sslVerify: $config['ssl_verify'],
    connectTimeout: $config['connect_timeout'],
    validateIdentity: $config['validate_identity'],
    danfse: $danfsePayload,
);
```

- [ ] **Step 3: Rodar suite completa**

Run: `./vendor/bin/pest --parallel`
Expected: PASS — `ServiceProviderTest` continua verde porque default `enabled=false`.

---

## Task 13: Feature test `NfsenClientAutoDanfseTest`

**Files:**
- Create: `tests/Feature/NfsenClientAutoDanfseTest.php`

**Pré-requisitos (verificados previamente):**

- `tests/fixtures/danfse/nfse-autorizada.xml` existe (criado no commit `e054601` — contém NFS-e autorizada com `chaveAcesso = 3303302112233450000195000000000000100000000001`).
- `tests/fixtures/certs/fake.pfx` existe (usado em todos os feature tests hoje).
- `storage/danfse/logo-nfse.png` existe (logo padrão embutido, referenciado em `DanfseConfig::defaultLogoDataUri()`).
- `makePfxContent()` existe em `tests/helpers.php:36`.
- Shape da resposta API `chaveAcesso` + `nfseXmlGZipB64` + `idDps` + `tipoAmbiente` + `versaoAplicativo` + `dataHoraProcessamento` bate com o parser no método `NfseEmitter::doEmitir()` (src/Operations/NfseEmitter.php, procurar pelo docblock `@var array{...}` descrevendo os campos esperados de `$result`). Usa-se o símbolo (método + docblock) em vez de linhas fixas porque números mudam com refactors.

Se qualquer uma dessas falhar na Task 13 com "fixture not found" ou similar, **pare** e verifique — não crie fixtures silenciosamente; algo regrediu no ambiente.

- [ ] **Step 1: Criar o arquivo de testes**

Arquivo: `tests/Feature/NfsenClientAutoDanfseTest.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\Decorators\ConsulterWithDanfse;
use OwnerPro\Nfsen\Operations\Decorators\EmitterWithDanfse;
use OwnerPro\Nfsen\Operations\Decorators\SubstitutorWithDanfse;
use Smalot\PdfParser\Parser as PdfParser;

covers(
    NfsenClient::class,
    EmitterWithDanfse::class,
    SubstitutorWithDanfse::class,
    ConsulterWithDanfse::class,
);

// Payload API autorizado (chaveAcesso + nfseXmlGZipB64) no formato esperado por
// NfseEmitter; conteúdo XML vem do fixture de NFS-e autorizada. Função prefixada
// com `makeDanfseAutorizadoApiResponse` para minimizar risco de colisão no namespace
// global do pest — helpers de teste em tests/helpers.php seguem padrão similar.
function makeDanfseAutorizadoApiResponse(): array
{
    $xml = (string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-autorizada.xml');
    $gzip = base64_encode((string) gzencode($xml));

    return [
        'chaveAcesso' => '3303302112233450000195000000000000100000000001',
        'nfseXmlGZipB64' => $gzip,
        'idDps' => 'DPS1',
        'tipoAmbiente' => 2,
        'versaoAplicativo' => '1.0',
        'dataHoraProcessamento' => '2026-04-15T10:00:00-03:00',
    ];
}

it('forStandalone com danfse array: emit retorna pdf %PDF- e conteúdo esperado', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    $client = NfsenClient::forStandalone(
        pfxContent: makePfxContent(),
        senha: 'secret',
        prefeitura: '9999999',
        validateIdentity: false,
        danfse: ['logo_path' => false],
    );

    $resp = $client->emitir($data);

    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->not->toBeNull();
    expect($resp->pdf)->toStartWith('%PDF-');

    $text = (new PdfParser)->parseContent((string) $resp->pdf)->getText();

    expect($text)->toContain('3303302112233450000195000000000000100000000001');
})->with('dpsData');

it('forStandalone sem danfse: pdf é null e pdfErrors vazio', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    $client = NfsenClient::forStandalone(
        pfxContent: makePfxContent(),
        senha: 'secret',
        prefeitura: '9999999',
        validateIdentity: false,
    );

    $resp = $client->emitir($data);

    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->toBeNull();
    expect($resp->pdfErrors)->toBe([]);
})->with('dpsData');

// NOTA dos testes NfsenClient::for(...) abaixo: quando a chamada passa $pfxContent e $senha
// explícitos, NfsenClient::for() NÃO consulta nfsen.certificado.* do config — ela delega a
// forStandalone() que recebe o cert direto. Os keys 'nfsen.certificado.path', 'senha',
// 'prefeitura' abaixo são inofensivos (dead config) mas ajudam caso o teste seja refatorado
// para exercitar app(NfsenClient::class) (ServiceProvider path). Mantidos por defense-in-depth.

it('for() com config.enabled=true ativa auto-render', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => true,
        'nfsen.danfse.logo_path' => false,
        'nfsen.danfse.municipality' => null,
    ]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    $resp = $client->emitir($data);

    expect($resp->pdf)->not->toBeNull();
})->with('dpsData');

it('for() com config.enabled=false não ativa auto-render', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => false,
    ]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    $resp = $client->emitir($data);

    expect($resp->pdf)->toBeNull();
})->with('dpsData');

it('for(danfse: [...]) sobrescreve config global — município do array aparece', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => true,
        'nfsen.danfse.municipality' => ['name' => 'Município Global'],
    ]);

    $client = NfsenClient::for(
        makePfxContent(),
        'secret',
        '9999999',
        danfse: [
            'logo_path' => false,
            'municipality' => ['name' => 'Município Tenant X'],
        ],
    );

    $resp = $client->emitir($data);

    expect($resp->pdf)->not->toBeNull();

    $text = (new PdfParser)->parseContent((string) $resp->pdf)->getText();
    expect($text)->toContain('Município Tenant X');
    expect($text)->not->toContain('Município Global');
})->with('dpsData');

it('for(danfse: false) força desligar mesmo com config.enabled=true', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => true,
        'nfsen.danfse.logo_path' => false,
    ]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999', danfse: false);

    $resp = $client->emitir($data);

    expect($resp->pdf)->toBeNull();
})->with('dpsData');

it('forStandalone(danfse: false) equivale a null (paridade)', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    $client = NfsenClient::forStandalone(
        pfxContent: makePfxContent(),
        senha: 'secret',
        prefeitura: '9999999',
        validateIdentity: false,
        danfse: false,
    );

    $resp = $client->emitir($data);

    expect($resp->pdf)->toBeNull();
    expect($resp->pdfErrors)->toBe([]);
})->with('dpsData');

it('for() com municipality=null (ternário do config retornou null): boot OK e emit gera PDF', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => true,
        'nfsen.danfse.logo_path' => false,
        'nfsen.danfse.municipality' => null, // estado resultante quando env NFSE_DANFSE_MUN_NAME não setado (ternário do config/nfsen.php)
    ]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $resp = $client->emitir($data);

    // Boot não explodiu + render rodou sem cabeçalho municipal (name filtrado upstream).
    expect($resp->pdf)->not->toBeNull();
    expect($resp->pdfErrors)->toBe([]);
})->with('dpsData');

it('defesa em profundidade: municipality {name: null} vira ausente — emit também gera PDF', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    // Cenário hipotético: array parcial chega via código (não pelo config/nfsen.php
    // que já tem o ternário). fromArray deve tolerar sem throw E render deve completar.
    $client = NfsenClient::forStandalone(
        pfxContent: makePfxContent(),
        senha: 'secret',
        prefeitura: '9999999',
        validateIdentity: false,
        danfse: [
            'logo_path' => false,
            'municipality' => ['name' => null, 'department' => 'X'],
        ],
    );
    $resp = $client->emitir($data);

    expect($resp->pdf)->not->toBeNull();
    expect($resp->pdfErrors)->toBe([]);
})->with('dpsData');
```

- [ ] **Step 2: Rodar os feature tests novos**

Run: `./vendor/bin/pest tests/Feature/NfsenClientAutoDanfseTest.php`
Expected: PASS — 9 testes.

---

## Task 14: Atualizar README e CHANGELOG

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Atualizar README — seção `danfse()` direto**

Localizar a seção atual sobre `::danfe()` no README. Renomear referências para `::danfse()` e substituir exemplos de `new DanfseConfig` pelo formato array. Conteúdo a ser inserido:

~~~markdown
### Geração do DANFSE local

```php
$resp = $client->danfse([
    'logo_path' => '/path/to/custom-logo.png',
    'municipality' => [
        'name' => 'São Paulo',
        'department' => 'SF/SUBTES',
        'email' => 'nfse@sp.gov.br',
    ],
])->toPdf($xmlNfse);

if ($resp->sucesso) {
    file_put_contents('danfse.pdf', $resp->pdf);
}
```
~~~

- [ ] **Step 2: Adicionar nova seção "Geração automática do DANFSE"**

Inserir após a seção de emissão, antes de "Consulta". Conteúdo:

~~~markdown
### Geração automática do DANFSE

O PDF é anexado ao `NfseResponse` em `emitir()`, `emitirDecisaoJudicial()`, `substituir()`
e `consultar()->nfse()` quando a DANFSE é configurada.

**Modo Laravel simples** — via `config/nfsen.php`:

```env
NFSE_DANFSE_AUTO=true
NFSE_DANFSE_LOGO_PATH=/path/to/logo.png
NFSE_DANFSE_MUN_NAME="São Paulo"
NFSE_DANFSE_MUN_DEPT="SF/SUBTES"
NFSE_DANFSE_MUN_EMAIL=nfse@sp.gov.br
```

```php
$resp = NfsenClient::for($pfx, $senha, $ibge)->emitir($dps);
echo $resp->pdf;                 // string com o PDF (ou null se render falhou)
print_r($resp->pdfErrors);       // list<ProcessingMessage> quando render falha
```

**Modo multi-tenant** — passe o array por requisição:

```php
$client = NfsenClient::for($pfx, $senha, $tenant->ibge, danfse: [
    'logo_path' => $tenant->logoPath,
    'municipality' => [
        'name' => $tenant->municipio,
        'email' => $tenant->emailPrefeitura,
    ],
]);

$resp = $client->emitir($dps);
```

**Desligar pontualmente** (mesmo com `NFSE_DANFSE_AUTO=true`):

```php
$client = NfsenClient::for($pfx, $senha, $ibge, danfse: false);
```

**Quando o PDF falha** (`$resp->sucesso === true` mas `$resp->pdf === null`): a NFS-e
foi emitida com sucesso. Tente regenerar sob demanda com
`$client->danfse($config)->toPdf($resp->xml)`.

**Gotcha do `enabled`**: a flag `nfsen.danfse.enabled` é checada com `=== true` estrito.
O config publicado aplica `(bool)` cast; se consumir config de outra fonte (ex.: banco de
dados) garanta o tipo bool. `1`, `'true'`, ou `'on'` **não** ativam auto-render.
~~~

- [ ] **Step 3: Atualizar CHANGELOG**

No topo do `CHANGELOG.md`, adicionar nova entrada sem versão (ou incrementar conforme convenção do projeto — inspecionar entradas prévias):

```markdown
## [Unreleased]

### Added
- `NfsenClient::for()` e `NfsenClient::forStandalone()` ganham parâmetro `array|false|null $danfse`
  que ativa auto-geração de DANFSE PDF em `emitir()`, `emitirDecisaoJudicial()`, `substituir()`
  e `consultar()->nfse()`. Sentinel `false` força desligar quando config global está ativa.
- Campos `pdf: ?string` e `pdfErrors: list<ProcessingMessage>` em `NfseResponse`.
- `DanfseConfig::fromArray()` e `MunicipalityBranding::fromArray()` com validação schema-like
  (whitelist de chaves + tipos + regras de negócio; `InvalidArgumentException` no boot).
- Bloco `danfse` em `config/nfsen.php` com `enabled` gate e envs `NFSE_DANFSE_*`.
- `NfsenClient::danfse()` passa a aceitar `DanfseConfig|array|null`.

### Changed (BREAKING)
- `NfsenClient::danfe()` renomeado para `NfsenClient::danfse()`. Não há alias — consumidores
  devem atualizar chamadas.
```

- [ ] **Step 4: Rodar suite completa novamente**

Run: `./vendor/bin/pest --parallel`
Expected: PASS.

---

## Task 15: Quality gate completo + commit final

**Files:** (nenhum novo — só verificação)

- [ ] **Step 1: Testes completos com cobertura**

Run: `./vendor/bin/pest --coverage --min=100 --parallel`
Expected: PASS com 100% de cobertura.

Se alguma linha nova não coberta: adicionar caso de teste até fechar 100%.

- [ ] **Step 2: Mutation testing**

Run: `./vendor/bin/pest --mutate --min=100 --parallel`
Expected: PASS com 100% MSI.

Se algum mutante sobrevive:

1. **Primeira escolha — matar via asserção**: adicionar observação nos fakes (contadores `->toPdfCalls`, `->xmlsReceived`) e assertar em `->toBe(N)` ou `->toBe([...])`. Mutation de `===`/`!==` e `||`/`&&` no trait costuma cair com combinação dos 5 casos do `AttachesDanfsePdfTest`. Mutações em strings de `InvalidArgumentException` caem pelo matcher do Pest (`->throws(..., 'trecho')`) que faz **substring match** — suficiente para swap/remoção de caracteres no meio da mensagem, mas **não** para alterações em prefixo/sufixo. Se houver mutante em prefixo/sufixo sobrevivente, substituir por try/catch explícito:

   ```php
   // composer.json pin: "pestphp/pest": "^4.0" — versão instalada 4.4.3.
   // expect()->fail() suportado. Em versões anteriores, usar $this->fail(...) ou throw.
   it('valida mensagem exata do InvalidArgumentException', function () {
       try {
           DanfseConfig::fromArray(['logo_paht' => 'x']);
           expect()->fail('Deveria ter lançado InvalidArgumentException');
       } catch (InvalidArgumentException $e) {
           expect($e->getMessage())->toBe('danfse: chave(s) desconhecida(s): logo_paht');
       }
   });
   ```

2. **Segunda escolha — pre-autorização documentada**: alvos prováveis onde mutação defensiva é semanticamente irrelevante:
   - Guarda redundante em `AttachesDanfsePdf::attachPdf` — se algum mutante que inverte o early-return sobreviver em contextos não testáveis pela API pública. Precedente: `NfsenClient.php`, `Support/GzipCompressor.php`, `Xml/DpsBuilder.php` já usam `// @pest-mutate-ignore <MutatorName> — <razão>`.
   - Formato exato da mensagem em `InvalidArgumentException` (ex.: mutação troca `:` por espaço) — mensagem é para humanos, não faz parte do contrato.

Pre-autorização **apenas** quando (a) a mutação não muda comportamento observável e (b) a razão for documentada no mesmo comentário. Evitar em ramos de lógica core (decorators, fromArray).

- [ ] **Step 3: Type coverage**

Run: `./vendor/bin/pest --type-coverage --min=100`
Expected: PASS — 100%.

Se cair abaixo: adicionar type hints/phpdoc faltantes. Provável alvo: arrays em `fromArray` e docblock `@var` nos testes.

- [ ] **Step 4: Rector dry-run**

Run: `./vendor/bin/rector --dry-run`
Expected: PASS sem diffs.

Se houver sugestões: aplicar com `./vendor/bin/rector` e re-rodar a suite.

- [ ] **Step 5: PHPStan**

Run: `./vendor/bin/phpstan analyse`
Expected: PASS.

Se houver erros: corrigir (tipos em `config()` do Laravel costumam pedir `@var` local).

- [ ] **Step 6: Psalm taint analysis**

Run: `./vendor/bin/psalm --taint-analysis`
Expected: PASS.

- [ ] **Step 7: Pint format**

Run: `./vendor/bin/pint -p`
Expected: PASS / arquivos formatados.

- [ ] **Step 8: Segundo passe completo da suite (após possíveis mudanças de formatação)**

Conforme `CLAUDE.md`: "if any quality checks changed any file, full suite need to be run again!"

Run (nesta ordem):

1. `./vendor/bin/pest --coverage --min=100 --parallel`
2. `./vendor/bin/pest --mutate --min=100 --parallel`
3. `./vendor/bin/pest --type-coverage --min=100`
4. `./vendor/bin/rector --dry-run`
5. `./vendor/bin/phpstan analyse`
6. `./vendor/bin/psalm --taint-analysis`
7. `./vendor/bin/pint -p`

Todos devem passar sem mudanças.

- [ ] **Step 9: Checagem de referências residuais ao método antigo**

Run: `grep -rn '->danfe(' src tests config docs README.md CHANGELOG.md || echo "OK: sem referências"`
Expected: "OK: sem referências".

- [ ] **Step 10: Commit único final**

Usar `git add` com paths explícitos — evita staging acidental de caches, coverage, `.phpunit.result.cache`, etc.

**Antes de qualquer `git add`**, listar tudo que está untracked para decidir conscientemente o que entra:

```bash
git status -uall
```

Revisar a saída. Depois, `add` path-específico (preferir listar arquivos novos um a um quando couber na cabeça):

```bash
git add \
    src/ \
    tests/Unit/ \
    tests/Feature/ \
    tests/Fakes/ \
    config/nfsen.php \
    README.md \
    CHANGELOG.md \
    composer.json
git status
```

**Nunca** `git add .` ou `git add -A`. Se `composer dump-autoload` (Task 5) tiver tocado `composer.lock`, **NÃO** adicionar esse arquivo (CLAUDE.md proíbe — é uma lib).

Confirmar visualmente que:
- `src/Danfse/Concerns/ValidatesArrayShape.php` criado
- `src/Danfse/DanfseConfig.php`, `MunicipalityBranding.php` modificados
- `src/Operations/Decorators/` com 4 arquivos novos
- `src/Responses/NfseResponse.php` modificado
- `src/NfsenClient.php` modificado
- `src/NfsenServiceProvider.php` modificado
- `config/nfsen.php` modificado
- `tests/Fakes/` com 4 fakes
- `tests/Unit/Danfse/` com 3 arquivos novos
- `tests/Unit/Operations/Decorators/` com 4 arquivos novos
- `tests/Unit/NfsenClientIsDanfseEnabledTest.php` novo (cobertura do helper)
- `tests/Feature/NfsenClientDanfseTest.php` (renomeado)
- `tests/Feature/NfsenClientAutoDanfseTest.php` novo
- `README.md`, `CHANGELOG.md` modificados
- `composer.json` com autoload-dev adicional (se Task 5 Step 2 foi necessário)

Nenhum arquivo desnecessário staged (ex.: `composer.lock`, caches).

```bash
git commit -m "$(cat <<'EOF'
feat(danfse): auto-PDF em emit/substituir/consultar + danfse() aceita array

Anexa DANFSE PDF ao NfseResponse em emitir/emitirDecisaoJudicial/substituir/
consultar()->nfse() via decorators opcionais (EmitterWithDanfse,
SubstitutorWithDanfse, ConsulterWithDanfse) injetados em NfsenClient quando
o parâmetro `danfse` é fornecido. Core hexagonal (NfseEmitter, Substitutor,
Consulter) permanece inalterado.

Refactor ergonômico: NfsenClient::danfe() renomeado para ::danfse() (hard
rename, sem alias); método passa a aceitar DanfseConfig|array|null, com
DanfseConfig::fromArray() e MunicipalityBranding::fromArray() validando
shape via trait ValidatesArrayShape (whitelist + tipos + regras). Cliente
não precisa mais instanciar DTOs manualmente.

Config Laravel: novo bloco `danfse` em config/nfsen.php com gate `enabled`
e envs NFSE_DANFSE_*. Multi-tenant sobrescreve via
NfsenClient::for(..., danfse: [...]). Sentinel `danfse: false` força
desligar pontualmente mesmo com config global ativa.

NfseResponse ganha campos `pdf: ?string` e `pdfErrors: list<ProcessingMessage>`
no fim do construtor (named args preservados). Quando o render falha, NFS-e
segue sucesso=true; PDF pode ser regenerado sob demanda.

Quality: 100% coverage, 100% mutation, 100% type-coverage, phpstan/psalm/
rector/pint limpos.

BREAKING CHANGE: NfsenClient::danfe() removido em favor de NfsenClient::danfse().

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 11: Verificar status pós-commit**

Run: `git status && git log -1 --stat`
Expected: working tree clean; commit contém todos os arquivos esperados.

---

## Self-review do plano

**Spec coverage:**

- ✓ Auto-render em emit/emitirDecisaoJudicial/substituir/consultar()->nfse (Tasks 6, 7, 8, 10)
- ✓ Decorators sobre driving ports (Tasks 6, 7, 8)
- ✓ Trait `AttachesDanfsePdf` (Task 5)
- ✓ `NfseResponse.pdf` + `pdfErrors` no fim (Task 4)
- ✓ `DanfseConfig::fromArray` + `buildMunicipality` defesa em profundidade (Task 3)
- ✓ `MunicipalityBranding::fromArray` validação estrita (Task 2)
- ✓ `ValidatesArrayShape` trait (Task 1)
- ✓ Rename `danfe()` → `danfse()` (Task 9)
- ✓ `danfse()` aceita `DanfseConfig|array|null` (Task 9)
- ✓ `forStandalone(..., danfse)` (Task 10)
- ✓ `for(..., danfse: false)` sentinel (Task 11)
- ✓ Config Laravel com ternário `municipality` (Task 12)
- ✓ `NfsenServiceProvider` repassa danfse (Task 12)
- ✓ Feature tests cobrindo todos os cenários (Task 13)
- ✓ README + CHANGELOG (Task 14)
- ✓ Invariante de wiring documentado (Task 10 + teste Task 7)
- ✓ Sanity %PDF- + smalot/pdfparser no feature test (Task 13)
- ✓ Fakes com contador (spy) para matar mutants (Tasks 5-8)
- ✓ Commit único no fim (Task 15)

**Placeholder scan:** sem TBD, TODO, "add validation", "implement later" no plano. Cada passo tem código ou comando concreto.

**Type consistency:** nomes verificados entre tasks:
- `attachPdf` (trait) — Task 5 define, Tasks 6/7/8 usam via `use AttachesDanfsePdf`
- `renderer()` (abstract method no trait) — Task 5 declara, Tasks 6/7/8 implementam retornando `$this->renderer`. Phpstan max feliz.
- `DanfseConfig::fromArray` — Task 3 define, Task 9/10 usam
- `MunicipalityBranding::fromArray` — Task 2 define, Task 3 (buildMunicipality) usa
- `ValidatesArrayShape::rejectUnknownKeys` — Task 1 define, Tasks 2/3 usam via trait
- `FakeRendersDanfse::$toPdfCalls` — Task 5 define, Tasks 6/7/8 asseveram
- `NfseResponse` posicional: Tasks usam named args exclusivamente
- Tests unit de decorators (Tasks 6/7) recebem `DpsData` via dataset `dpsData` existente em `tests/datasets.php` (shape válido, phpstan/shipmonk felizes)
- Feature test (Task 13) sem imports não usados; helper `makeDanfseAutorizadoApiResponse` prefixado
- Fakes em `tests/Fakes/` (capital F) — PSR-4 existente resolve sem mudança em `composer.json`

Plano consistente. Sem gaps vs spec.
