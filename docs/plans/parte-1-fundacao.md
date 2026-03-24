# Reescrita nfse-nacional — Plano de Implementação

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

> **Parte 1 de 3** — Tasks 1–7 (Fundação): bootstrap, enums, exceptions, DTOs, certificates, prefeitura resolver, XML signer.


**Goal:** Reescrever o pacote nfse-nacional com namespace `OwnerPro\Nfsen`, integração nativa com Laravel HTTP client (mTLS via tmpfile), testes automatizados e API pública fluente.

**Architecture:** Pacote Laravel com suporte standalone. `NfseClient::for()` (via container) ou `NfseClient::forStandalone()` (sem Laravel) recebem cert PFX + prefeitura e retornam instância pronta; `emitir()`, `cancelar()` e `consultar()->nfse/dps/danfse/eventos()` orquestram builders XML, assinatura, compressão e HTTP. Código novo vive em `src-new/` (namespace `OwnerPro\Nfsen`); código legado coexiste em `src/` (namespace `Hadder\Nfsen`) via dual autoload até Task 18 (limpeza: `src/` → `src-old/`, `src-new/` → `src/`).

> **Nota standalone:** Em modo standalone (sem Laravel bootado), os Laravel Events (`NfseEmitted`, `NfseFailed`, etc.) **não são disparados** — o `dispatchEvent()` silencia a ausência do dispatcher. Todas as demais funcionalidades (emitir, cancelar, consultar) operam normalmente.

**Tech Stack:** PHP 8.2+, Laravel 11/12 (illuminate/http, illuminate/support), nfephp-org/sped-common, Pest 3 + orchestra/testbench 9.

---

## Convenções

- Namespace: `OwnerPro\Nfsen`
- Testes rodam com: `./vendor/bin/pest`
- Fixtures de cert: `tests/fixtures/certs/fake.pfx` (senha: `secret`) — sem OID ICP-Brasil
- Fixtures de cert: `tests/fixtures/certs/fake-icpbr.pfx` (senha: `secret`) — com OID ICP-Brasil (CNPJ extraível via `Certificate::getCnpj()`)
- Fixtures de cert: `tests/fixtures/certs/expired.pfx` (senha: `secret`) — certificado expirado (fixture estática)
- Fixtures de resposta: `tests/fixtures/responses/*.json`
- Todos os `stdClass` internos de DpsData mantêm propriedades em **minúsculas** (padrão atual via `propertiesToLower`)

---

## Task 1: Bootstrap — composer.json, diretórios e certificado de teste

**Files:**
- Modify: `composer.json`
- Create: `tests/fixtures/certs/.gitkeep`
- Create: `tests/fixtures/responses/.gitkeep`
- Create: `phpunit.xml`

**Step 1: Atualizar composer.json**

```json
{
  "name": "ownerpro/nfsen",
  "description": "Pacote Laravel para emissão de NFSe Nacional",
  "license": "MIT",
  "require": {
    "php": "^8.2",
    "nfephp-org/sped-common": "^5.1",
    "illuminate/http": "^11.0|^12.0",
    "illuminate/support": "^11.0|^12.0",
    "illuminate/contracts": "^11.0|^12.0",
    "tecnickcom/tcpdf": "^6.7",
    "symfony/var-dumper": "^7.1|^6.4",
    "ext-curl": "*",
    "ext-dom": "*",
    "ext-zlib": "*",
    "ext-openssl": "*",
    "ext-mbstring": "*"
  },
  "require-dev": {
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "orchestra/testbench": "^9.0"
  },
  "autoload": {
    "psr-4": {
      "Hadder\\Nfsen\\": "src/",
      "OwnerPro\\Nfsen\\": "src-new/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "OwnerPro\\Nfsen\\Tests\\": "tests/"
    }
  },
  "config": {
    "optimize-autoloader": true
  },
  "minimum-stability": "dev",
  "prefer-stable": true,
  "extra": {
    "laravel": {
      "providers": ["OwnerPro\\Nfsen\\NfseNacionalServiceProvider"]
    }
  }
}
```

> **Nota transição:** Mantemos o autoload `Hadder\Nfsen` e as deps legadas (`tcpdf`, `var-dumper`) durante a reescrita para não quebrar código existente em `src/`. A remoção ocorre na Task 18 (limpeza final).

> **Nota Helpers.php:** O `composer.json` legado tinha `"files": ["Helpers.php"]` que define `now()` global. Esse autoload é **intencionalmente removido** — o `now()` do `illuminate/support` substitui. Padronizar o `Helpers.php` (Step 4d) para segurança caso o arquivo seja carregado manualmente.

> **Nota composer.lock:** O `.gitignore` já ignora `composer.lock` — convenção correta para packages/libraries. Manter assim durante toda a reescrita. Não commitar `composer.lock`.

**Step 2: Criar phpunit.xml**

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
</phpunit>
```

**Step 3: Criar estrutura de diretórios**

```bash
mkdir -p src-new/{Enums,Http,Certificates,Xml/Builders,Signing,Services,Consulta,Events,DTOs,Exceptions,Facades}
mkdir -p tests/{Unit/{Xml,Signing,Certificates,Services},Feature,fixtures/{certs,responses}}
mkdir -p config
```

**Step 4: Gerar certificados de teste**

Gerar 3 fixtures de certificado:

**4a) `fake.pfx` — certificado simples sem OID ICP-Brasil:**
```bash
openssl genrsa -out tests/fixtures/certs/fake.key 2048
openssl req -new -x509 -key tests/fixtures/certs/fake.key \
  -out tests/fixtures/certs/fake.crt -days 3650 \
  -subj "/CN=Fake Test/O=OwnerPro/C=BR"
openssl pkcs12 -export \
  -out tests/fixtures/certs/fake.pfx \
  -inkey tests/fixtures/certs/fake.key \
  -in tests/fixtures/certs/fake.crt \
  -passout pass:secret
rm tests/fixtures/certs/fake.key tests/fixtures/certs/fake.crt
```

**4b) `fake-icpbr.pfx` — certificado com OID ICP-Brasil (CNPJ extraível):**

Criar `tests/fixtures/certs/icpbr.cnf`:
```ini
[req]
distinguished_name = req_dn
x509_extensions = v3_req
prompt = no

[req_dn]
CN = Fake ICP-BR Test
O = OwnerPro
C = BR

[v3_req]
subjectAltName = @alt_names

[alt_names]
otherName.1 = 2.16.76.1.3.3;FORMAT:UTF8,UTF8:12345678000195
```

```bash
openssl genrsa -out tests/fixtures/certs/fake-icpbr.key 2048
openssl req -new -x509 -key tests/fixtures/certs/fake-icpbr.key \
  -out tests/fixtures/certs/fake-icpbr.crt -days 3650 \
  -config tests/fixtures/certs/icpbr.cnf
openssl pkcs12 -export \
  -out tests/fixtures/certs/fake-icpbr.pfx \
  -inkey tests/fixtures/certs/fake-icpbr.key \
  -in tests/fixtures/certs/fake-icpbr.crt \
  -passout pass:secret
rm tests/fixtures/certs/fake-icpbr.key tests/fixtures/certs/fake-icpbr.crt tests/fixtures/certs/icpbr.cnf
```

> **Nota:** O OID `2.16.76.1.3.3` é o campo ICP-Brasil que `NFePHP\Common\Certificate::getCnpj()` usa para extrair o CNPJ. Se o openssl da máquina não suportar `otherName` no config, gerar o PFX num ambiente que suporte e commitar a fixture estática.

**4c) `expired.pfx` — certificado expirado (gerado via OpenSSL CLI com datas no passado):**

Gerar com `-not_before`/`-not_after` explícitos no passado para garantir que o cert já nasce expirado (requer OpenSSL 1.1.1+):

```bash
openssl req -x509 -newkey rsa:2048 \
  -keyout /tmp/expired.key -out /tmp/expired.crt \
  -days 1 -nodes \
  -subj '/CN=Expired Test/C=BR' \
  -not_before 20200101000000Z \
  -not_after 20200102000000Z

openssl pkcs12 -export \
  -out tests/fixtures/certs/expired.pfx \
  -inkey /tmp/expired.key -in /tmp/expired.crt \
  -passout pass:secret

rm /tmp/expired.key /tmp/expired.crt
```

> **Nota:** Se o OpenSSL da máquina não suportar `-not_before`/`-not_after` (< 1.1.1), gerar via PHP com `phpseclib` ou em outra máquina e commitar como **fixture estática**. O cert DEVE estar expirado no momento do commit para que os testes da Task 5 passem imediatamente.

**4d) Padronizar `Helpers.php` existente:**

O `Helpers.php` legado já possui o guard `function_exists('now')`. Reescrever para padronizar estilo (será removido na Task 18):

```php
<?php

if (!function_exists('now')) {
    function now($tz = null)
    {
        return new \DateTime('now', $tz ? new \DateTimeZone($tz) : null);
    }
}
```

**4e) Criar `.gitignore` para intermediários de certificado:**

Criar `tests/fixtures/certs/.gitignore`:
```
*.key
*.crt
*.cnf
!*.pfx
```

> Previne que arquivos intermediários (`.key`, `.crt`, `.cnf`) sejam commitados acidentalmente se a geração for interrompida.

**Step 5: Instalar dependências**

```bash
composer install
```

**Step 6: Verificar que o Pest está funcional**

```bash
./vendor/bin/pest --version
```
Expected: `Pest 3.x.x`

**Step 7: Commit**

```bash
git add composer.json phpunit.xml tests/fixtures/ Helpers.php
git commit -m "chore: bootstrap reescrita — deps, estrutura de dirs e cert de teste"
```

---

## Task 2: Enums — NfseAmbiente e MotivoCancelamento

**Files:**
- Create: `src-new/Enums/NfseAmbiente.php`
- Create: `src-new/Enums/MotivoCancelamento.php`
- Create: `tests/Unit/Enums/NfseAmbienteTest.php`
- Create: `tests/Unit/Enums/MotivoCancelamentoTest.php`

**Step 1: Escrever os testes**

`tests/Unit/Enums/NfseAmbienteTest.php`:
```php
<?php

use OwnerPro\Nfsen\Enums\NfseAmbiente;

it('has producao value of 1', function () {
    expect(NfseAmbiente::PRODUCAO->value)->toBe(1);
});

it('has homologacao value of 2', function () {
    expect(NfseAmbiente::HOMOLOGACAO->value)->toBe(2);
});

it('can be created from value', function () {
    expect(NfseAmbiente::from(1))->toBe(NfseAmbiente::PRODUCAO);
    expect(NfseAmbiente::from(2))->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('fromConfig accepts integer values', function () {
    expect(NfseAmbiente::fromConfig(1))->toBe(NfseAmbiente::PRODUCAO);
    expect(NfseAmbiente::fromConfig(2))->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('fromConfig accepts string values', function () {
    expect(NfseAmbiente::fromConfig('producao'))->toBe(NfseAmbiente::PRODUCAO);
    expect(NfseAmbiente::fromConfig('homologacao'))->toBe(NfseAmbiente::HOMOLOGACAO);
});

it('fromConfig throws on unknown string value', function () {
    expect(fn () => NfseAmbiente::fromConfig('unknown'))
        ->toThrow(\InvalidArgumentException::class);
});
```

`tests/Unit/Enums/MotivoCancelamentoTest.php`:
```php
<?php

use OwnerPro\Nfsen\Enums\MotivoCancelamento;

it('has erro emissao value', function () {
    expect(MotivoCancelamento::ErroEmissao->value)->toBe('e101101');
});

it('has outros value', function () {
    expect(MotivoCancelamento::Outros->value)->toBe('e105102');
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Enums/ --no-coverage
```
Expected: FAIL — `OwnerPro\Nfsen\Enums\NfseAmbiente not found`

**Step 3: Implementar os enums**

`src-new/Enums/NfseAmbiente.php`:
```php
<?php

namespace OwnerPro\Nfsen\Enums;

enum NfseAmbiente: int
{
    case PRODUCAO    = 1;
    case HOMOLOGACAO = 2;

    public static function fromConfig(int|string $v): self
    {
        if (is_int($v) || ctype_digit((string) $v)) {
            return self::from((int) $v);
        }

        return match(strtolower((string) $v)) {
            'producao', 'production'     => self::PRODUCAO,
            'homologacao', 'homologation' => self::HOMOLOGACAO,
            default => throw new \InvalidArgumentException(
                "Ambiente NFSe inválido: '$v'. Valores aceitos: 1, 2, 'producao', 'homologacao'."
            ),
        };
    }
}
```

`src-new/Enums/MotivoCancelamento.php`:
```php
<?php

namespace OwnerPro\Nfsen\Enums;

enum MotivoCancelamento: string
{
    case ErroEmissao = 'e101101';
    case Outros      = 'e105102';
}
```

**Step 4: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Enums/ --no-coverage
```
Expected: PASS (8 testes)

**Step 5: Commit**

```bash
git add src-new/Enums/ tests/Unit/Enums/
git commit -m "feat: add NfseAmbiente and MotivoCancelamento enums"
```

---

## Task 3: Exceptions

**Files:**
- Create: `src-new/Exceptions/NfseException.php`
- Create: `src-new/Exceptions/CertificateExpiredException.php`
- Create: `src-new/Exceptions/HttpException.php`
- Create: `tests/Unit/Exceptions/ExceptionsTest.php`

**Step 1: Escrever testes**

`tests/Unit/Exceptions/ExceptionsTest.php`:
```php
<?php

use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\NfseException;

it('NfseException is a RuntimeException', function () {
    $e = new NfseException('msg');
    expect($e)->toBeInstanceOf(\RuntimeException::class);
    expect($e->getMessage())->toBe('msg');
});

it('CertificateExpiredException extends NfseException', function () {
    $e = new CertificateExpiredException('cert expired');
    expect($e)->toBeInstanceOf(NfseException::class);
});

it('HttpException carries status code', function () {
    $e = new HttpException('timeout', 408);
    expect($e)->toBeInstanceOf(NfseException::class);
    expect($e->getCode())->toBe(408);
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Exceptions/ --no-coverage
```
Expected: FAIL

**Step 3: Implementar**

`src-new/Exceptions/NfseException.php`:
```php
<?php

namespace OwnerPro\Nfsen\Exceptions;

class NfseException extends \RuntimeException {}
```

`src-new/Exceptions/CertificateExpiredException.php`:
```php
<?php

namespace OwnerPro\Nfsen\Exceptions;

class CertificateExpiredException extends NfseException {}
```

`src-new/Exceptions/HttpException.php`:
```php
<?php

namespace OwnerPro\Nfsen\Exceptions;

class HttpException extends NfseException {}
```

**Step 4: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Exceptions/ --no-coverage
```
Expected: PASS (3 testes)

**Step 5: Commit**

```bash
git add src-new/Exceptions/ tests/Unit/Exceptions/
git commit -m "feat: add NfseException, CertificateExpiredException, HttpException"
```

---

## Task 4: DTOs — NfseResponse, DpsData, DanfseResponse, EventosResponse

**Files:**
- Create: `src-new/DTOs/NfseResponse.php`
- Create: `src-new/DTOs/DpsData.php`
- Create: `src-new/DTOs/DanfseResponse.php`
- Create: `src-new/DTOs/EventosResponse.php`
- Create: `tests/Unit/DTOs/NfseResponseTest.php`
- Create: `tests/Unit/DTOs/DpsDataTest.php`
- Create: `tests/Unit/DTOs/DanfseResponseTest.php`
- Create: `tests/Unit/DTOs/EventosResponseTest.php`

**Step 1: Escrever testes**

`tests/Unit/DTOs/NfseResponseTest.php`:
```php
<?php

use OwnerPro\Nfsen\DTOs\NfseResponse;

it('stores a success response', function () {
    $response = new NfseResponse(true, 'chave123', '<xml/>', null);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('chave123');
    expect($response->xml)->toBe('<xml/>');
    expect($response->erro)->toBeNull();
});

it('stores a failure response', function () {
    $response = new NfseResponse(false, null, null, 'E001 - Erro');

    expect($response->sucesso)->toBeFalse();
    expect($response->chave)->toBeNull();
    expect($response->xml)->toBeNull();
    expect($response->erro)->toBe('E001 - Erro');
});
```

`tests/Unit/DTOs/DpsDataTest.php`:
```php
<?php

use OwnerPro\Nfsen\DTOs\DpsData;

it('stores all groups', function () {
    $infDps    = new stdClass();
    $prestador = new stdClass();
    $tomador   = new stdClass();
    $servico   = new stdClass();
    $valores   = new stdClass();

    $data = new DpsData($infDps, $prestador, $tomador, $servico, $valores);

    expect($data->infDps)->toBe($infDps);
    expect($data->prestador)->toBe($prestador);
    expect($data->tomador)->toBe($tomador);
    expect($data->servico)->toBe($servico);
    expect($data->valores)->toBe($valores);
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/DTOs/ --no-coverage
```
Expected: FAIL

**Step 3: Implementar**

`src-new/DTOs/NfseResponse.php`:
```php
<?php

namespace OwnerPro\Nfsen\DTOs;

readonly class NfseResponse
{
    public function __construct(
        public bool    $sucesso,
        public ?string $chave,
        public ?string $xml,
        public ?string $erro,
    ) {}
}
```

`src-new/DTOs/DpsData.php`:
```php
<?php

namespace OwnerPro\Nfsen\DTOs;

use stdClass;

readonly class DpsData
{
    public function __construct(
        public stdClass $infDps,
        public stdClass $prestador,
        public stdClass $tomador,
        public stdClass $servico,
        public stdClass $valores,
    ) {}
}
```

**Step 4: Implementar DanfseResponse e EventosResponse**

`src-new/DTOs/DanfseResponse.php`:
```php
<?php

namespace OwnerPro\Nfsen\DTOs;

readonly class DanfseResponse
{
    public function __construct(
        public bool    $sucesso,
        public ?string $url,
        public ?string $erro,
    ) {}
}
```

`src-new/DTOs/EventosResponse.php`:
```php
<?php

namespace OwnerPro\Nfsen\DTOs;

readonly class EventosResponse
{
    public function __construct(
        public bool    $sucesso,
        public array   $eventos,
        public ?string $erro,
    ) {}
}
```

**Step 5: Escrever testes dos novos DTOs**

`tests/Unit/DTOs/DanfseResponseTest.php`:
```php
<?php

use OwnerPro\Nfsen\DTOs\DanfseResponse;

it('stores a DANFSe success response', function () {
    $response = new DanfseResponse(true, 'https://danfse.url/CHAVE', null);

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.url/CHAVE');
    expect($response->erro)->toBeNull();
});

it('stores a DANFSe failure response', function () {
    $response = new DanfseResponse(false, null, 'NFSe não encontrada');

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toBe('NFSe não encontrada');
});
```

`tests/Unit/DTOs/EventosResponseTest.php`:
```php
<?php

use OwnerPro\Nfsen\DTOs\EventosResponse;

it('stores eventos success response', function () {
    $eventos = [['tipo' => '101101', 'desc' => 'Cancelamento']];
    $response = new EventosResponse(true, $eventos, null);

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toHaveCount(1);
    expect($response->erro)->toBeNull();
});

it('stores eventos empty response', function () {
    $response = new EventosResponse(true, [], null);

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toBeEmpty();
});
```

**Step 6: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/DTOs/ --no-coverage
```
Expected: PASS (7 testes)

**Step 7: Commit**

```bash
git add src-new/DTOs/ tests/Unit/DTOs/
git commit -m "feat: add NfseResponse, DpsData, DanfseResponse, EventosResponse DTOs"
```

---

## Task 5: CertificateManager

**Files:**
- Create: `src-new/Certificates/CertificateManager.php`
- Create: `tests/Unit/Certificates/CertificateManagerTest.php`

**Context:**
- `NFePHP\Common\Certificate::readPfx($pfxContent, $password)` cria o objeto sem escrita em disco
- `$cert->isExpired()` retorna `true` se vencido
- O teste usa `tests/fixtures/certs/fake.pfx` (senha `secret`) gerado na Task 1

**Step 1: Escrever teste**

`tests/Unit/Certificates/CertificateManagerTest.php`:
```php
<?php

use NFePHP\Common\Certificate;
use OwnerPro\Nfsen\Certificates\CertificateManager;
use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;

it('loads certificate from pfx content', function () {
    $pfxContent = file_get_contents(__DIR__ . '/../../fixtures/certs/fake.pfx');

    $manager = new CertificateManager($pfxContent, 'secret');

    expect($manager->getCertificate())->toBeInstanceOf(Certificate::class);
});

it('throws CertificateExpiredException for an expired cert', function () {
    // Usa fixture estática gerada na Task 1 (Step 4c)
    $pfxContent = file_get_contents(__DIR__ . '/../../fixtures/certs/expired.pfx');

    expect(fn () => new CertificateManager($pfxContent, 'secret'))
        ->toThrow(CertificateExpiredException::class);
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Certificates/ --no-coverage
```
Expected: FAIL — class not found

**Step 3: Implementar**

`src-new/Certificates/CertificateManager.php`:
```php
<?php

namespace OwnerPro\Nfsen\Certificates;

use NFePHP\Common\Certificate;
use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;

class CertificateManager
{
    private Certificate $certificate;

    public function __construct(string $pfxContent, string $password)
    {
        $this->certificate = Certificate::readPfx($pfxContent, $password);

        if ($this->certificate->isExpired()) {
            throw new CertificateExpiredException('Certificate is expired.');
        }
    }

    public function getCertificate(): Certificate
    {
        return $this->certificate;
    }
}
```

**Step 4: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Certificates/ --no-coverage
```
Expected: PASS (2 testes)

**Step 5: Commit**

```bash
git add src-new/Certificates/ tests/Unit/Certificates/
git commit -m "feat: add CertificateManager — loads PFX from string, validates expiry"
```

---

## Task 6: PrefeituraResolver

**Files:**
- Create: `src-new/Services/PrefeituraResolver.php`
- Create: `tests/Unit/Services/PrefeituraResolverTest.php`

**Context:**
O `storage/prefeituras.json` já existe e usa código IBGE como chave (ex.: `"3501608"`). O resolver faz merge das URLs padrão com as overrides do JSON.

URLs padrão (mesmo do `RestCurl`):
```
sefin_homologacao → https://sefin.producaorestrita.nfse.gov.br/SefinNacional
sefin_producao    → https://sefin.nfse.gov.br/sefinnacional
adn_homologacao   → https://adn.producaorestrita.nfse.gov.br
adn_producao      → https://adn.nfse.gov.br
```

Operations padrão:
```
consultar_nfse    → nfse/{chave}
consultar_dps     → dps/{chave}
consultar_eventos → nfse/{chave}/eventos/{tipoEvento}/{nSequencial}
consultar_danfse  → danfse/{chave}
emitir_nfse       → nfse
cancelar_nfse     → nfse/{chave}/eventos
```

**Step 1: Escrever testes**

`tests/Unit/Services/PrefeituraResolverTest.php`:
```php
<?php

use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Services\PrefeituraResolver;

$jsonPath = __DIR__ . '/../../../storage/prefeituras.json';

it('resolves default sefin url for unknown prefeitura in homologacao', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $url = $resolver->resolveSeFinUrl('9999999', NfseAmbiente::HOMOLOGACAO);

    expect($url)->toBe('https://sefin.producaorestrita.nfse.gov.br/SefinNacional');
});

it('resolves custom sefin url for known prefeitura', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $url = $resolver->resolveSeFinUrl('3501608', NfseAmbiente::HOMOLOGACAO);

    expect($url)->toContain('americanahomologacao');
});

it('resolves default adn url for unknown prefeitura in producao', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $url = $resolver->resolveAdnUrl('9999999', NfseAmbiente::PRODUCAO);

    expect($url)->toBe('https://adn.nfse.gov.br');
});

it('resolves operation path with substitution', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $path = $resolver->resolveOperation('9999999', 'consultar_nfse', ['chave' => 'ABC123']);

    expect($path)->toBe('nfse/ABC123');
});

it('resolves custom operation for known prefeitura', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    // 3547304 (Santa Ana de Parnaiba) tem consultar_danfse customizado
    $path = $resolver->resolveOperation('3547304', 'consultar_danfse', ['chave' => 'ABC']);

    expect($path)->toContain('ABC');
});

it('throws InvalidArgumentException for non-7-digit ibge code', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    expect(fn () => $resolver->resolveSeFinUrl('123', NfseAmbiente::HOMOLOGACAO))
        ->toThrow(\InvalidArgumentException::class, 'IBGE');
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Services/ --no-coverage
```
Expected: FAIL

**Step 3: Implementar**

`src-new/Services/PrefeituraResolver.php`:
```php
<?php

namespace OwnerPro\Nfsen\Services;

use OwnerPro\Nfsen\Enums\NfseAmbiente;

class PrefeituraResolver
{
    /** @var array<string, array> Cache estático por path — evita re-leitura em lote */
    private static array $cache = [];

    private const DEFAULT_URLS = [
        'sefin_homologacao' => 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional',
        'sefin_producao'    => 'https://sefin.nfse.gov.br/sefinnacional',
        'adn_homologacao'   => 'https://adn.producaorestrita.nfse.gov.br',
        'adn_producao'      => 'https://adn.nfse.gov.br',
    ];

    private const DEFAULT_OPERATIONS = [
        'consultar_nfse'    => 'nfse/{chave}',
        'consultar_dps'     => 'dps/{chave}',
        'consultar_eventos' => 'nfse/{chave}/eventos/{tipoEvento}/{nSequencial}',
        'consultar_danfse'  => 'danfse/{chave}',
        'emitir_nfse'       => 'nfse',
        'cancelar_nfse'     => 'nfse/{chave}/eventos',
    ];

    private array $data;

    public function __construct(private readonly string $jsonPath)
    {
        $this->data = static::$cache[$jsonPath]
            ??= json_decode(file_get_contents($jsonPath) ?: '{}', true) ?? [];
    }

    public static function clearCache(): void
    {
        static::$cache = [];
    }

    public function resolveSeFinUrl(string $codigoIbge, NfseAmbiente $ambiente): string
    {
        $this->validateIbge($codigoIbge);
        $key = $ambiente === NfseAmbiente::PRODUCAO ? 'sefin_producao' : 'sefin_homologacao';
        return $this->data[$codigoIbge]['urls'][$key] ?? self::DEFAULT_URLS[$key];
    }

    public function resolveAdnUrl(string $codigoIbge, NfseAmbiente $ambiente): string
    {
        $this->validateIbge($codigoIbge);
        $key = $ambiente === NfseAmbiente::PRODUCAO ? 'adn_producao' : 'adn_homologacao';
        return $this->data[$codigoIbge]['urls'][$key] ?? self::DEFAULT_URLS[$key];
    }

    public function resolveOperation(string $codigoIbge, string $operacao, array $params = []): string
    {
        $this->validateIbge($codigoIbge);
        $template = $this->data[$codigoIbge]['operations'][$operacao]
            ?? self::DEFAULT_OPERATIONS[$operacao];

        foreach ($params as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    private function validateIbge(string $code): void
    {
        if (!preg_match('/^\d{7}$/', $code)) {
            throw new \InvalidArgumentException("Código IBGE inválido: '$code'. Esperado: 7 dígitos numéricos.");
        }
    }
}
```

**Step 4: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Services/ --no-coverage
```
Expected: PASS (5 testes)

**Step 5: Commit**

```bash
git add src-new/Services/ tests/Unit/Services/
git commit -m "feat: add PrefeituraResolver — merge de URLs/operations com prefeituras.json"
```

---

## Task 7: XmlSigner

**Files:**
- Create: `src-new/Signing/XmlSigner.php`
- Create: `tests/Unit/Signing/XmlSignerTest.php`

**Context:**
`NFePHP\Common\Signer::sign($cert, $xml, $tagname, $mark, $algo, $canonical, $rootname)` injeta `<Signature>` no XML.
- `$tagname` = tag a ser assinada (`infDPS` para emitir, `infPedReg` para cancelar)
- `$mark` = `'Id'` (atributo XML que contém o identificador do elemento assinado)
- `$algo` = `OPENSSL_ALGO_SHA1` ou `OPENSSL_ALGO_SHA256`
- `$canonical` = `[true, false, null, null]`
- `$rootname` = tag raiz (`DPS` ou `pedRegEvento`)

**Step 1: Escrever teste**

`tests/Unit/Signing/XmlSignerTest.php`:
```php
<?php

use OwnerPro\Nfsen\Signing\XmlSigner;

// makeTestCertificate() definida em tests/helpers.php (criado na Task 8)

it('signs xml and injects Signature element', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha1');

    // XML mínimo com Id no elemento a ser assinado
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse">'
        . '<infDPS Id="DPS00000000000000000000000000000000000000001"/>'
        . '</DPS>';

    $signed = $signer->sign($xml, 'infDPS', 'DPS');

    expect($signed)->toContain('<Signature');
    expect($signed)->toContain('SignedInfo');
});

it('accepts sha256 algorithm', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha256');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse">'
        . '<infDPS Id="DPS00000000000000000000000000000000000000001"/>'
        . '</DPS>';

    $signed = $signer->sign($xml, 'infDPS', 'DPS');

    expect($signed)->toContain('<Signature');
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Signing/ --no-coverage
```
Expected: FAIL

**Step 3: Implementar**

`src-new/Signing/XmlSigner.php`:
```php
<?php

namespace OwnerPro\Nfsen\Signing;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

class XmlSigner
{
    private int $algorithm;
    private array $canonical = [true, false, null, null];

    public function __construct(
        private readonly Certificate $certificate,
        string $signingAlgorithm = 'sha1',
    ) {
        $this->algorithm = $signingAlgorithm === 'sha256'
            ? OPENSSL_ALGO_SHA256
            : OPENSSL_ALGO_SHA1;
    }

    public function sign(string $xml, string $tagname, string $rootname): string
    {
        return Signer::sign(
            $this->certificate,
            $xml,
            $tagname,
            'Id',
            $this->algorithm,
            $this->canonical,
            $rootname,
        );
    }
}
```

**Step 4: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Signing/ --no-coverage
```
Expected: PASS (2 testes)

**Step 5: Commit**

```bash
git add src-new/Signing/ tests/Unit/Signing/
git commit -m "feat: add XmlSigner — wraps NFePHP Signer com configuração de algoritmo"
```

---

