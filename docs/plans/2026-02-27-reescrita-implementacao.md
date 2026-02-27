# Reescrita nfse-nacional — Plano de Implementação

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Reescrever o pacote nfse-nacional com namespace `Pulsar\NfseNacional`, integração nativa com Laravel HTTP client (mTLS via tmpfile), testes automatizados e API pública fluente.

**Architecture:** Pacote Laravel com suporte standalone. `NfseClient::for()` (via container) ou `NfseClient::forStandalone()` (sem Laravel) recebem cert PFX + prefeitura e retornam instância pronta; `emitir()`, `cancelar()` e `consultar()->nfse/dps/danfse/eventos()` orquestram builders XML, assinatura, compressão e HTTP. Código novo vive em `src-new/` (namespace `Pulsar\NfseNacional`); código legado coexiste em `src/` (namespace `Hadder\NfseNacional`) via dual autoload até Task 18 (limpeza: `src/` → `src-old/`, `src-new/` → `src/`).

> **Nota standalone:** Em modo standalone (sem Laravel bootado), os Laravel Events (`NfseEmitted`, `NfseFailed`, etc.) **não são disparados** — o `dispatchEvent()` silencia a ausência do dispatcher. Todas as demais funcionalidades (emitir, cancelar, consultar) operam normalmente.

**Tech Stack:** PHP 8.2+, Laravel 11/12 (illuminate/http, illuminate/support), nfephp-org/sped-common, Pest 3 + orchestra/testbench 9.

---

## Convenções

- Namespace: `Pulsar\NfseNacional`
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
  "name": "pulsar/nfse-nacional",
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
      "Hadder\\NfseNacional\\": "src/",
      "Pulsar\\NfseNacional\\": "src-new/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Pulsar\\NfseNacional\\Tests\\": "tests/"
    }
  },
  "config": {
    "optimize-autoloader": true
  },
  "minimum-stability": "dev",
  "prefer-stable": true,
  "extra": {
    "laravel": {
      "providers": ["Pulsar\\NfseNacional\\NfseNacionalServiceProvider"]
    }
  }
}
```

> **Nota transição:** Mantemos o autoload `Hadder\NfseNacional` e as deps legadas (`tcpdf`, `var-dumper`) durante a reescrita para não quebrar código existente em `src/`. A remoção ocorre na Task 18 (limpeza final).

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
  -subj "/CN=Fake Test/O=Pulsar/C=BR"
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
O = Pulsar
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

use Pulsar\NfseNacional\Enums\NfseAmbiente;

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

use Pulsar\NfseNacional\Enums\MotivoCancelamento;

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
Expected: FAIL — `Pulsar\NfseNacional\Enums\NfseAmbiente not found`

**Step 3: Implementar os enums**

`src-new/Enums/NfseAmbiente.php`:
```php
<?php

namespace Pulsar\NfseNacional\Enums;

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

namespace Pulsar\NfseNacional\Enums;

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

use Pulsar\NfseNacional\Exceptions\CertificateExpiredException;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Exceptions\NfseException;

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

namespace Pulsar\NfseNacional\Exceptions;

class NfseException extends \RuntimeException {}
```

`src-new/Exceptions/CertificateExpiredException.php`:
```php
<?php

namespace Pulsar\NfseNacional\Exceptions;

class CertificateExpiredException extends NfseException {}
```

`src-new/Exceptions/HttpException.php`:
```php
<?php

namespace Pulsar\NfseNacional\Exceptions;

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

use Pulsar\NfseNacional\DTOs\NfseResponse;

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

use Pulsar\NfseNacional\DTOs\DpsData;

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

namespace Pulsar\NfseNacional\DTOs;

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

namespace Pulsar\NfseNacional\DTOs;

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

namespace Pulsar\NfseNacional\DTOs;

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

namespace Pulsar\NfseNacional\DTOs;

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

use Pulsar\NfseNacional\DTOs\DanfseResponse;

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

use Pulsar\NfseNacional\DTOs\EventosResponse;

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
use Pulsar\NfseNacional\Certificates\CertificateManager;
use Pulsar\NfseNacional\Exceptions\CertificateExpiredException;

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

namespace Pulsar\NfseNacional\Certificates;

use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Exceptions\CertificateExpiredException;

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

use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Services\PrefeituraResolver;

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

namespace Pulsar\NfseNacional\Services;

use Pulsar\NfseNacional\Enums\NfseAmbiente;

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

use Pulsar\NfseNacional\Signing\XmlSigner;

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

namespace Pulsar\NfseNacional\Signing;

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

## Task 8: DpsBuilder — cabeçalho infDPS + PrestadorBuilder

**Files:**
- Create: `src-new/Xml/Builders/PrestadorBuilder.php`
- Create: `src-new/Xml/DpsBuilder.php` (parcial — só header + prest)
- Create: `tests/Unit/Xml/PrestadorBuilderTest.php`
- Create: `tests/Unit/Xml/DpsBuilderHeaderTest.php`
- Create: `tests/Pest.php` (bootstrap mínimo — será expandido na Task 12)
- Create: `tests/datasets.php` (dataset compartilhado de DpsData)

**Context:**
- `DpsBuilder::build(DpsData)` retorna XML string sem declaração XML (`<?xml...?>`)
- Usa `DOMDocument` padrão do PHP
- A validação XSD completa vem na Task 10 depois de todos os sub-builders estarem prontos
- Os `stdClass` de entrada têm propriedades em **minúsculas**

Estrutura do cabeçalho `<infDPS>`:
```xml
<infDPS Id="DPS{cLocEmi(7)}{tipoInscricao(1)}{inscricao(14)}{serie(5)}{nDPS(15)}">
  <tpAmb>{tpamb}</tpAmb>
  <dhEmi>{dhemi}</dhEmi>
  <verAplic>{veraplic}</verAplic>
  <serie>{serie}</serie>
  <nDPS>{ndps}</nDPS>
  <dCompet>{dcompet}</dCompet>
  <tpEmit>{tpemit}</tpEmit>
  <cLocEmi>{clocemi}</cLocEmi>
</infDPS>
```

**Step 1: Criar bootstrap de testes compartilhados**

`tests/Pest.php` (mínimo — será expandido na Task 12 para incluir `uses()`):
```php
<?php

require_once __DIR__ . '/datasets.php';
require_once __DIR__ . '/helpers.php';
```

`tests/helpers.php` — helpers compartilhados (consolidados aqui em vez de inline em cada teste):
```php
<?php

use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Certificates\CertificateManager;

function makePfxContent(): string
{
    return file_get_contents(__DIR__ . '/fixtures/certs/fake.pfx');
}

function makeTestCertificate(): Certificate
{
    return (new CertificateManager(makePfxContent(), 'secret'))->getCertificate();
}
```

`tests/datasets.php` — dataset compartilhado de DpsData usado nas Tasks 8, 9, 10 e 15:
```php
<?php

use Pulsar\NfseNacional\DTOs\DpsData;

dataset('dpsData', [
    'basico' => function (): DpsData {
        $infDps           = new stdClass();
        $infDps->tpamb    = 2;
        $infDps->dhemi    = '2026-02-27T10:00:00-03:00';
        $infDps->veraplic = '1.0';
        $infDps->serie    = 'E';
        $infDps->ndps     = 1;
        $infDps->dcompet  = '2026-02';
        $infDps->tpemit   = 1;
        $infDps->clocemi  = '3501608';

        $prestador        = new stdClass();
        $prestador->cnpj  = '12345678000195';
        $prestador->xnome = 'Empresa';
        $regTrib             = new stdClass();
        $regTrib->opsimpnac  = 1;
        $regTrib->regesptrib = 0;
        $prestador->regtrib  = $regTrib;

        $tomador  = new stdClass();
        $servico  = new stdClass();

        $locPrest                    = new stdClass();
        $locPrest->clocprestacao     = '3501608';
        $servico->locprest           = $locPrest;

        $cServ               = new stdClass();
        $cServ->ctribnac     = '01.01.01.000';
        $cServ->xdescserv    = 'Serviço de Teste';
        $servico->cserv      = $cServ;

        $valores = new stdClass();

        return new DpsData($infDps, $prestador, $tomador, $servico, $valores);
    },
]);
```

> **Nota Task 10:** Se a validação XSD falhar por campos obrigatórios (ex.: `valores`), atualizar o closure `'basico'` neste dataset com os campos faltantes.

**Step 2: Escrever testes**

`tests/Unit/Xml/PrestadorBuilderTest.php`:
```php
<?php

use Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder;

it('builds prest element with CNPJ', function () {
    $builder = new PrestadorBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass();
    $prest->cnpj = '12345678000195';
    $prest->xnome = 'Empresa Teste';
    $regTrib = new stdClass();
    $regTrib->opsimpnac = 1;
    $regTrib->regesptrib = 0;
    $prest->regtrib = $regTrib;

    $element = $builder->build($doc, $prest);
    $doc->appendChild($element);

    expect($doc->saveXML($element))->toContain('<CNPJ>12345678000195</CNPJ>');
    expect($doc->saveXML($element))->toContain('<xNome>Empresa Teste</xNome>');
    expect($doc->saveXML($element))->toContain('<regTrib>');
    expect($doc->saveXML($element))->toContain('<opSimpNac>1</opSimpNac>');
});

it('builds prest element with CPF when no CNPJ', function () {
    $builder = new PrestadorBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $prest = new stdClass();
    $prest->cpf = '12345678901';
    $prest->xnome = 'Pessoa Física';
    $regTrib = new stdClass();
    $regTrib->opsimpnac = 0;
    $regTrib->regesptrib = 0;
    $prest->regtrib = $regTrib;

    $element = $builder->build($doc, $prest);

    expect($doc->saveXML($element))->toContain('<CPF>12345678901</CPF>');
});
```

`tests/Unit/Xml/DpsBuilderHeaderTest.php`:
```php
<?php

use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('builds xml with DPS root element', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<DPS ');
    expect($xml)->toContain('versao=');
    expect($xml)->toContain('xmlns="http://www.sped.fazenda.gov.br/nfse"');
})->with('dpsData');

it('builds xml with infDPS Id', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<infDPS Id="DPS');
})->with('dpsData');

it('includes tpAmb in infDPS', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<tpAmb>2</tpAmb>');
})->with('dpsData');
```

**Step 3: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/ --no-coverage
```
Expected: FAIL

**Step 4: Implementar PrestadorBuilder**

`src-new/Xml/Builders/PrestadorBuilder.php`:
```php
<?php

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

class PrestadorBuilder
{
    public function build(DOMDocument $doc, stdClass $prest): DOMElement
    {
        $el = $doc->createElement('prest');

        if (isset($prest->cnpj)) {
            $el->appendChild($doc->createElement('CNPJ', $prest->cnpj));
        }
        if (isset($prest->cpf)) {
            $el->appendChild($doc->createElement('CPF', $prest->cpf));
        }
        if (isset($prest->nif)) {
            $el->appendChild($doc->createElement('NIF', $prest->nif));
        }
        if (isset($prest->cnaonif)) {
            $el->appendChild($doc->createElement('cNaoNIF', $prest->cnaonif));
        }
        if (isset($prest->caepf)) {
            $el->appendChild($doc->createElement('CAEPF', $prest->caepf));
        }
        if (isset($prest->im)) {
            $el->appendChild($doc->createElement('IM', $prest->im));
        }
        if (isset($prest->xnome)) {
            $el->appendChild($doc->createElement('xNome', $prest->xnome));
        }
        if (isset($prest->end)) {
            $el->appendChild($this->buildEnd($doc, $prest->end));
        }
        if (isset($prest->fone)) {
            $el->appendChild($doc->createElement('fone', $prest->fone));
        }
        if (isset($prest->email)) {
            $el->appendChild($doc->createElement('email', $prest->email));
        }

        $regTrib = $doc->createElement('regTrib');
        $regTrib->appendChild($doc->createElement('opSimpNac', $prest->regtrib->opsimpnac));
        if (isset($prest->regtrib->regaptribsn)) {
            $regTrib->appendChild($doc->createElement('regApTribSN', $prest->regtrib->regaptribsn));
        }
        $regTrib->appendChild($doc->createElement('regEspTrib', $prest->regtrib->regesptrib));
        $el->appendChild($regTrib);

        return $el;
    }

    private function buildEnd(DOMDocument $doc, stdClass $end): DOMElement
    {
        $el = $doc->createElement('end');
        if (isset($end->endnac)) {
            $endNac = $doc->createElement('endNac');
            $endNac->appendChild($doc->createElement('cMun', $end->endnac->cmun));
            $endNac->appendChild($doc->createElement('CEP', $end->endnac->cep));
            $el->appendChild($endNac);
        } elseif (isset($end->endext)) {
            $endExt = $doc->createElement('endExt');
            $endExt->appendChild($doc->createElement('cPais', $end->endext->cpais));
            $endExt->appendChild($doc->createElement('cEndPost', $end->endext->cendpost));
            $endExt->appendChild($doc->createElement('xCidade', $end->endext->xcidade));
            $endExt->appendChild($doc->createElement('xEstProvReg', $end->endext->xestprovreg));
            $el->appendChild($endExt);
        }
        $el->appendChild($doc->createElement('xLgr', $end->xlgr));
        $el->appendChild($doc->createElement('nro', $end->nro));
        if (isset($end->xcpl)) {
            $el->appendChild($doc->createElement('xCpl', $end->xcpl));
        }
        $el->appendChild($doc->createElement('xBairro', $end->xbairro));
        return $el;
    }
}
```

**Step 5: Implementar DpsBuilder (parcial — cabeçalho + prest)**

`src-new/Xml/DpsBuilder.php`:
```php
<?php

namespace Pulsar\NfseNacional\Xml;

use DOMDocument;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder;
use Pulsar\NfseNacional\Xml\Builders\TomadorBuilder;
use Pulsar\NfseNacional\Xml\Builders\ServicoBuilder;
use Pulsar\NfseNacional\Xml\Builders\ValoresBuilder;

class DpsBuilder
{
    private const VERSION = '1.01';
    private const XMLNS   = 'http://www.sped.fazenda.gov.br/nfse';

    public function __construct(private readonly string $schemesPath) {}

    public function build(DpsData $data): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = false;

        $dps = $doc->createElement('DPS');
        $dps->setAttribute('versao', self::VERSION);
        $dps->setAttribute('xmlns', self::XMLNS);

        $infDps = $doc->createElement('infDPS');
        $infDps->setAttribute('Id', $this->generateId($data));

        $d = $data->infDps;
        $infDps->appendChild($doc->createElement('tpAmb',    $d->tpamb));
        $infDps->appendChild($doc->createElement('dhEmi',    $d->dhemi));
        $infDps->appendChild($doc->createElement('verAplic', $d->veraplic));
        $infDps->appendChild($doc->createElement('serie',    $d->serie));
        $infDps->appendChild($doc->createElement('nDPS',     $d->ndps));
        $infDps->appendChild($doc->createElement('dCompet',  $d->dcompet));
        $infDps->appendChild($doc->createElement('tpEmit',   $d->tpemit));
        if (isset($d->cmotivoemisti)) {
            $infDps->appendChild($doc->createElement('cMotivoEmisTI', $d->cmotivoemisti));
        }
        if (isset($d->chnfserej)) {
            $infDps->appendChild($doc->createElement('chNFSeRej', $d->chnfserej));
        }
        $infDps->appendChild($doc->createElement('cLocEmi',  $d->clocemi));

        // prest (optional)
        if (!empty((array) $data->prestador)) {
            $infDps->appendChild((new PrestadorBuilder())->build($doc, $data->prestador));
        }

        // toma (optional) — implementado na Task 9
        // serv / valores — implementados na Task 9

        $dps->appendChild($infDps);
        $doc->appendChild($dps);

        // Retorna sem declaração <?xml...?> — NfseClient adiciona ela uma vez antes de gzencode
        return $doc->saveXML($doc->documentElement);
    }

    private function generateId(DpsData $data): string
    {
        $d  = $data->infDps;
        $p  = $data->prestador;
        $id = 'DPS';
        $id .= substr($d->clocemi, 0, 7);
        $id .= isset($p->cnpj) ? '2' : '1';
        $inscricao = $p->cnpj ?? $p->cpf ?? '';
        $id .= str_pad($inscricao, 14, '0', STR_PAD_LEFT);
        // NOTA: O legado usa STR_PAD_LEFT com '0' (serie 'E' → '0000E').
        // Verificar contra a spec oficial do NFSe Nacional antes de implementar.
        // Se a spec exigir padding diferente para séries alfanuméricas, ajustar aqui.
        $id .= str_pad($d->serie, 5, '0', STR_PAD_LEFT);
        $id .= str_pad($d->ndps, 15, '0', STR_PAD_LEFT);
        return $id;
    }
}
```

**Step 6: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Xml/ --no-coverage
```
Expected: PASS (5 testes)

**Step 7: Commit**

```bash
git add src-new/Xml/ tests/Unit/Xml/ tests/Pest.php tests/datasets.php tests/helpers.php
git commit -m "feat: add PrestadorBuilder and DpsBuilder (header + prest); bootstrap Pest + datasets + helpers"
```

---

## Task 9: TomadorBuilder + ServicoBuilder + ValoresBuilder

**Files:**
- Create: `src-new/Xml/Builders/TomadorBuilder.php`
- Create: `src-new/Xml/Builders/ServicoBuilder.php`
- Create: `src-new/Xml/Builders/ValoresBuilder.php`
- Modify: `src-new/Xml/DpsBuilder.php` (integrar os três builders)
- Create: `tests/Unit/Xml/TomadorBuilderTest.php`
- Create: `tests/Unit/Xml/ServicoBuilderTest.php`
- Create: `tests/Unit/Xml/ValoresBuilderTest.php`

**Step 1: Escrever testes**

`tests/Unit/Xml/TomadorBuilderTest.php`:
```php
<?php

use Pulsar\NfseNacional\Xml\Builders\TomadorBuilder;

it('builds toma element with CNPJ', function () {
    $builder = new TomadorBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass();
    $toma->cnpj  = '98765432000111';
    $toma->xnome = 'Tomador Ltda';

    $element = $builder->build($doc, $toma);

    expect($doc->saveXML($element))->toContain('<CNPJ>98765432000111</CNPJ>');
    expect($doc->saveXML($element))->toContain('<xNome>Tomador Ltda</xNome>');
});
```

`tests/Unit/Xml/ServicoBuilderTest.php`:
```php
<?php

use Pulsar\NfseNacional\Xml\Builders\ServicoBuilder;

it('builds serv element with locPrest and cServ', function () {
    $builder = new ServicoBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $serv     = new stdClass();
    $locPrest = new stdClass();
    $locPrest->clocprestacao = '3501608';
    $serv->locprest = $locPrest;

    $cServ = new stdClass();
    $cServ->ctribnac  = '01.01.01.000';
    $cServ->xdescserv = 'Serviço X';
    $serv->cserv = $cServ;

    $element = $builder->build($doc, $serv);
    $xml     = $doc->saveXML($element);

    expect($xml)->toContain('<locPrest>');
    expect($xml)->toContain('<cLocPrestacao>3501608</cLocPrestacao>');
    expect($xml)->toContain('<cServ>');
    expect($xml)->toContain('<cTribNac>01.01.01.000</cTribNac>');
    expect($xml)->toContain('<xDescServ>Serviço X</xDescServ>');
});
```

`tests/Unit/Xml/ValoresBuilderTest.php`:
```php
<?php

use Pulsar\NfseNacional\Xml\Builders\ValoresBuilder;

it('builds valores element with vServPrest', function () {
    $builder = new ValoresBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass();
    $valores->vservprest = new stdClass();
    $valores->vservprest->vtrib  = '100.00';
    $valores->vservprest->vdeduct = null;

    $element = $builder->build($doc, $valores);
    $xml     = $doc->saveXML($element);

    expect($xml)->toContain('<valores>');
    expect($xml)->toContain('<vServPrest>');
    expect($xml)->toContain('<vTrib>100.00</vTrib>');
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/TomadorBuilderTest.php tests/Unit/Xml/ServicoBuilderTest.php tests/Unit/Xml/ValoresBuilderTest.php --no-coverage
```
Expected: FAIL

**Step 3: Implementar TomadorBuilder**

`src-new/Xml/Builders/TomadorBuilder.php`:
```php
<?php

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

class TomadorBuilder
{
    public function build(DOMDocument $doc, stdClass $toma): DOMElement
    {
        // Validação de campos obrigatórios delegada ao XSD (DpsBuilder::buildAndValidate)
        $el = $doc->createElement('toma');

        if (isset($toma->cnpj))    $el->appendChild($doc->createElement('CNPJ', $toma->cnpj));
        if (isset($toma->cpf))     $el->appendChild($doc->createElement('CPF', $toma->cpf));
        if (isset($toma->nif))     $el->appendChild($doc->createElement('NIF', $toma->nif));
        if (isset($toma->cnaonif)) $el->appendChild($doc->createElement('cNaoNIF', $toma->cnaonif));
        if (isset($toma->caepf))   $el->appendChild($doc->createElement('CAEPF', $toma->caepf));
        if (isset($toma->im))      $el->appendChild($doc->createElement('IM', $toma->im));

        $el->appendChild($doc->createElement('xNome', $toma->xnome));

        if (isset($toma->end)) {
            $endEl = $doc->createElement('end');
            if (isset($toma->end->endnac)) {
                $endNac = $doc->createElement('endNac');
                $endNac->appendChild($doc->createElement('cMun', $toma->end->endnac->cmun));
                $endNac->appendChild($doc->createElement('CEP', $toma->end->endnac->cep));
                $endEl->appendChild($endNac);
            } elseif (isset($toma->end->endext)) {
                $endExt = $doc->createElement('endExt');
                $endExt->appendChild($doc->createElement('cPais', $toma->end->endext->cpais));
                $endExt->appendChild($doc->createElement('cEndPost', $toma->end->endext->cendpost));
                $endExt->appendChild($doc->createElement('xCidade', $toma->end->endext->xcidade));
                $endExt->appendChild($doc->createElement('xEstProvReg', $toma->end->endext->xestprovreg));
                $endEl->appendChild($endExt);
            }
            $endEl->appendChild($doc->createElement('xLgr', $toma->end->xlgr));
            $endEl->appendChild($doc->createElement('nro', $toma->end->nro));
            if (isset($toma->end->xcpl)) {
                $endEl->appendChild($doc->createElement('xCpl', $toma->end->xcpl));
            }
            $endEl->appendChild($doc->createElement('xBairro', $toma->end->xbairro));
            $el->appendChild($endEl);
        }
        if (isset($toma->fone))  $el->appendChild($doc->createElement('fone', $toma->fone));
        if (isset($toma->email)) $el->appendChild($doc->createElement('email', $toma->email));

        return $el;
    }
}
```

**Step 4: Implementar ServicoBuilder**

`src-new/Xml/Builders/ServicoBuilder.php`:
```php
<?php

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

class ServicoBuilder
{
    public function build(DOMDocument $doc, stdClass $serv): DOMElement
    {
        $el = $doc->createElement('serv');

        // locPrest (obrigatório)
        $locPrest = $doc->createElement('locPrest');
        $locPrest->appendChild($doc->createElement('cLocPrestacao', $serv->locprest->clocprestacao));
        if (isset($serv->locprest->cpaisprestacao)) {
            $locPrest->appendChild($doc->createElement('cPaisPrestacao', $serv->locprest->cpaisprestacao));
        }
        $el->appendChild($locPrest);

        // cServ (obrigatório)
        $cServ = $doc->createElement('cServ');
        $cServ->appendChild($doc->createElement('cTribNac', $serv->cserv->ctribnac));
        if (isset($serv->cserv->ctribmun)) {
            $cServ->appendChild($doc->createElement('cTribMun', $serv->cserv->ctribmun));
        }
        $cServ->appendChild($doc->createElement('xDescServ', $serv->cserv->xdescserv));
        if (isset($serv->cserv->cnbs)) {
            $cServ->appendChild($doc->createElement('cNBS', $serv->cserv->cnbs));
        }
        if (isset($serv->cserv->cintcontrib)) {
            $cServ->appendChild($doc->createElement('cIntContrib', $serv->cserv->cintcontrib));
        }
        $el->appendChild($cServ);

        // comExt (opcional)
        if (isset($serv->comext)) {
            $comExt = $doc->createElement('comExt');
            $comExt->appendChild($doc->createElement('mdPrestacao', $serv->comext->mdprestacao));
            $comExt->appendChild($doc->createElement('vincPrest', $serv->comext->vincprest));
            $comExt->appendChild($doc->createElement('tpMoeda', $serv->comext->tpmoeda));
            $comExt->appendChild($doc->createElement('vServMoeda', $serv->comext->vservmoeda));
            $comExt->appendChild($doc->createElement('mecAFComexP', $serv->comext->mecafcomexp));
            $comExt->appendChild($doc->createElement('mecAFComexT', $serv->comext->mecafcomext));
            $comExt->appendChild($doc->createElement('movTempBens', $serv->comext->movtempbens));
            if (isset($serv->comext->ndi)) $comExt->appendChild($doc->createElement('nDI', $serv->comext->ndi));
            if (isset($serv->comext->nre)) $comExt->appendChild($doc->createElement('nRE', $serv->comext->nre));
            $comExt->appendChild($doc->createElement('mdic', $serv->comext->mdic));
            $el->appendChild($comExt);
        }

        // obra (opcional)
        if (isset($serv->obra)) {
            $obra = $doc->createElement('obra');
            if (isset($serv->obra->inscimobfisc)) $obra->appendChild($doc->createElement('inscImobFisc', $serv->obra->inscimobfisc));
            if (isset($serv->obra->cobra)) $obra->appendChild($doc->createElement('cObra', $serv->obra->cobra));
            if (isset($serv->obra->ccib)) $obra->appendChild($doc->createElement('cCIB', $serv->obra->ccib));
            if (isset($serv->obra->end)) {
                $endObra = $doc->createElement('end');
                if (isset($serv->obra->end->cep)) $endObra->appendChild($doc->createElement('CEP', $serv->obra->end->cep));
                if (isset($serv->obra->end->cmun)) $endObra->appendChild($doc->createElement('cMun', $serv->obra->end->cmun));
                if (isset($serv->obra->end->xlgr)) $endObra->appendChild($doc->createElement('xLgr', $serv->obra->end->xlgr));
                if (isset($serv->obra->end->nro)) $endObra->appendChild($doc->createElement('nro', $serv->obra->end->nro));
                if (isset($serv->obra->end->xcpl)) $endObra->appendChild($doc->createElement('xCpl', $serv->obra->end->xcpl));
                if (isset($serv->obra->end->xbairro)) $endObra->appendChild($doc->createElement('xBairro', $serv->obra->end->xbairro));
                $obra->appendChild($endObra);
            }
            $el->appendChild($obra);
        }

        return $el;
    }
}
```

**Step 5: Implementar ValoresBuilder**

`src-new/Xml/Builders/ValoresBuilder.php`:
```php
<?php

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

class ValoresBuilder
{
    public function build(DOMDocument $doc, stdClass $valores): DOMElement
    {
        $el = $doc->createElement('valores');

        if (isset($valores->vservprest)) {
            $vServ = $doc->createElement('vServPrest');
            if (isset($valores->vservprest->vtrib)) {
                $vServ->appendChild($doc->createElement('vTrib', $valores->vservprest->vtrib));
            }
            if (isset($valores->vservprest->vdeduct)) {
                $vServ->appendChild($doc->createElement('vDeduct', $valores->vservprest->vdeduct));
            }
            $el->appendChild($vServ);
        }

        if (isset($valores->trib)) {
            $el->appendChild($this->buildTrib($doc, $valores->trib));
        }

        return $el;
    }

    private function buildTrib(DOMDocument $doc, stdClass $trib): DOMElement
    {
        $el = $doc->createElement('trib');

        if (isset($trib->tribmun)) {
            $tribMun = $doc->createElement('tribMun');
            if (isset($trib->tribmun->vtrib)) {
                $tribMun->appendChild($doc->createElement('vTrib', $trib->tribmun->vtrib));
            }
            if (isset($trib->tribmun->tribissvexig)) {
                $tribMun->appendChild($doc->createElement('tribISSVExig', $trib->tribmun->tribissvexig));
            }
            if (isset($trib->tribmun->xexig)) {
                $tribMun->appendChild($doc->createElement('xExig', $trib->tribmun->xexig));
            }
            $el->appendChild($tribMun);
        }

        if (isset($trib->gtribfed)) {
            $gTribFed = $doc->createElement('gTribFed');
            if (isset($trib->gtribfed->piscofins)) {
                $pisCofins = $doc->createElement('pisCofins');
                if (isset($trib->gtribfed->piscofins->cstpis)) {
                    $pisCofins->appendChild($doc->createElement('cstPis', $trib->gtribfed->piscofins->cstpis));
                }
                $gTribFed->appendChild($pisCofins);
            }
            $el->appendChild($gTribFed);
        }

        if (isset($trib->totaltrib)) {
            $totTrib = $doc->createElement('totalTrib');
            if (isset($trib->totaltrib->vtottrib)) {
                $totTrib->appendChild($doc->createElement('vTotTrib', $trib->totaltrib->vtottrib));
            }
            if (isset($trib->totaltrib->pstottrib)) {
                $totTrib->appendChild($doc->createElement('psTotTrib', $trib->totaltrib->pstottrib));
            }
            $el->appendChild($totTrib);
        }

        return $el;
    }
}
```

**Step 6: Integrar builders no DpsBuilder**

Modificar `src-new/Xml/DpsBuilder.php` — substituir os comentários `// toma / serv / valores` pela chamada real:

```php
// Após o bloco de prest, antes de $dps->appendChild($infDps):

// toma (optional)
if (!empty((array) $data->tomador)) {
    $infDps->appendChild((new TomadorBuilder())->build($doc, $data->tomador));
}

// serv (obrigatório)
$infDps->appendChild((new ServicoBuilder())->build($doc, $data->servico));

// valores (obrigatório quando houver dados)
if (!empty((array) $data->valores)) {
    $infDps->appendChild((new ValoresBuilder())->build($doc, $data->valores));
}
```

**Step 7: Rodar todos os testes de XML**

```bash
./vendor/bin/pest tests/Unit/Xml/ --no-coverage
```
Expected: PASS (todos)

**Step 8: Commit**

```bash
git add src-new/Xml/ tests/Unit/Xml/
git commit -m "feat: add TomadorBuilder, ServicoBuilder, ValoresBuilder — DpsBuilder completo"
```

---

## Task 10: DpsBuilder — validação XSD + teste de operação vazia

**Files:**
- Modify: `src-new/Xml/DpsBuilder.php` (adicionar `buildAndValidate()` separado)
- Create: `tests/Unit/Xml/DpsBuilderXsdTest.php`
- Modify: `tests/Unit/Services/PrefeituraResolverTest.php` (adicionar teste de operação vazia)

**Context:**
`DOMDocument::schemaValidate($xsdPath)` lança warnings PHP, não exceptions. Use `libxml_use_internal_errors(true)` para capturar. O XSD principal é `storage/schemes/DPS_v1.01.xsd`.

> **Decisão de design:** `build()` **não** chama `validateXsd()` internamente. A validação fica no método separado `buildAndValidate()`. Isso evita overhead de XSD em emissões em lote e mantém `build()` rápido. Testes usam `buildAndValidate()`.

**Step 0: Pré-mapear campos obrigatórios do XSD**

Antes de escrever testes, consultar o XSD em `storage/schemes/DPS_v1.01.xsd` para identificar **todos** os campos obrigatórios em `<serv>` e `<valores>`. Atualizar o closure `'basico'` em `tests/datasets.php` com esses campos **antes** de rodar o teste XSD, evitando loop de tentativa e erro.

**Step 1: Adicionar teste de operação vazia no PrefeituraResolver**

Em `tests/Unit/Services/PrefeituraResolverTest.php`, adicionar:
```php
it('returns empty string for empty operation override', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    // Americana (3501608) tem emitir_nfse: "" — URL já é completa
    $path = $resolver->resolveOperation('3501608', 'emitir_nfse');

    expect($path)->toBe('');
});
```

**Step 2: Escrever teste de validação XSD**

`tests/Unit/Xml/DpsBuilderXsdTest.php`:
```php
<?php

use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('produces xml that validates against DPS_v1.01.xsd', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->buildAndValidate($data);

    // Se chegou aqui sem exception, o XML é válido
    expect($xml)->toContain('<DPS ');
})->with('dpsData');

it('build() does not validate XSD (fast path)', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    // build() retorna XML sem validar — não lança exceção mesmo se inválido
    expect($xml)->toBeString();
})->with('dpsData');
```

**Step 3: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/DpsBuilderXsdTest.php --no-coverage
```
Expected: FAIL (método `buildAndValidate` não existe)

**Step 4: Adicionar `buildAndValidate()` no DpsBuilder**

Em `src-new/Xml/DpsBuilder.php`, adicionar método público que chama `build()` + validação:

```php
public function buildAndValidate(DpsData $data): string
{
    $xml = $this->build($data);
    $this->validateXsd($xml);
    return $xml;
}

private function validateXsd(string $xmlFragment): void
{
    $xsdPath = $this->schemesPath . '/DPS_v1.01.xsd';
    if (!file_exists($xsdPath)) {
        return;
    }
    // Adiciona declaração XML temporariamente para validação XSD
    $xmlWithDecl = '<?xml version="1.0" encoding="UTF-8"?>' . $xmlFragment;
    $doc = new DOMDocument();
    $doc->loadXML($xmlWithDecl);
    libxml_use_internal_errors(true);
    $valid  = $doc->schemaValidate($xsdPath);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    if (!$valid) {
        $messages = array_map(fn ($e) => trim($e->message), $errors);
        throw new \Pulsar\NfseNacional\Exceptions\NfseException(
            'XML inválido: ' . implode('; ', $messages)
        );
    }
}
```

> **Importante:** `build()` retorna XML **sem** validação XSD. `buildAndValidate()` é a versão que valida. O `NfseClient::emitir()` (Task 15) usa `build()` por padrão — o dev pode optar por `buildAndValidate()` para debug.

**Step 5: Ajustar dataset 'basico' até o XSD passar**

Se `buildAndValidate()` lançar exceção, ler a mensagem do XSD e adicionar os campos faltantes no closure `'basico'` de `tests/datasets.php`.

**Step 6: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Xml/ tests/Unit/Services/ --no-coverage
```
Expected: PASS

**Step 7: Commit**

```bash
git add src-new/Xml/ tests/Unit/Xml/ tests/Unit/Services/
git commit -m "feat: DpsBuilder::buildAndValidate() valida XML contra DPS_v1.01.xsd; teste de op vazia"
```

---

## Task 11: EventoBuilder

**Files:**
- Create: `src-new/Xml/Builders/EventoBuilder.php`
- Create: `tests/Unit/Xml/EventoBuilderTest.php`

**Context:**
O XML de cancelamento tem estrutura:
```xml
<pedRegEvento versao="1.01" xmlns="http://www.sped.fazenda.gov.br/nfse">
  <infPedReg Id="PRE{chNFSe}{codigoEvento}">
    <tpAmb>2</tpAmb>
    <verAplic>1.0</verAplic>
    <dhEvento>2026-02-27T10:00:00-03:00</dhEvento>
    <CNPJAutor>12345678000195</CNPJAutor>
    <chNFSe>ABC123</chNFSe>
    <e101101>           <!-- ou e105102 -->
      <xDesc>Erro de emissão</xDesc>
      <cMotivo>e101101</cMotivo>
      <xMotivo>Descrição do motivo</xMotivo>
    </e101101>
  </infPedReg>
</pedRegEvento>
```

**Step 1: Escrever teste**

`tests/Unit/Xml/EventoBuilderTest.php`:
```php
<?php

use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Xml\Builders\EventoBuilder;

it('builds evento xml for e101101', function () {
    $builder = new EventoBuilder();

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-02-27T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: 'CHAVE50CARACTERES1234567890123456789012345678901',
        motivo: MotivoCancelamento::ErroEmissao,
        descricao: 'Erro ao emitir',
    );

    expect($xml)->toContain('<pedRegEvento');
    expect($xml)->toContain('<infPedReg Id="PRE');
    expect($xml)->toContain('<chNFSe>');
    expect($xml)->toContain('<e101101>');
    expect($xml)->toContain('<xDesc>Cancelamento de NFS-e</xDesc>');
    expect($xml)->toContain('<cMotivo>e101101</cMotivo>');
    expect($xml)->toContain('<xMotivo>Erro ao emitir</xMotivo>');
});

it('builds evento xml for e105102', function () {
    $builder = new EventoBuilder();

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-02-27T10:00:00-03:00',
        cnpjAutor: null,
        cpfAutor: '12345678901',
        chNFSe: 'CHAVE50CARACTERES1234567890123456789012345678901',
        motivo: MotivoCancelamento::Outros,
        descricao: 'Motivo diverso',
    );

    expect($xml)->toContain('<e105102>');
    expect($xml)->toContain('<CPFAutor>12345678901</CPFAutor>');
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/EventoBuilderTest.php --no-coverage
```
Expected: FAIL

**Step 3: Implementar**

`src-new/Xml/Builders/EventoBuilder.php`:
```php
<?php

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;

class EventoBuilder
{
    private const VERSION = '1.01';
    private const XMLNS   = 'http://www.sped.fazenda.gov.br/nfse';

    public function build(
        int $tpAmb,
        string $verAplic,
        string $dhEvento,
        ?string $cnpjAutor,
        ?string $cpfAutor,
        string $chNFSe,
        MotivoCancelamento $motivo,
        string $descricao,
    ): string {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = false;

        $root = $doc->createElement('pedRegEvento');
        $root->setAttribute('versao', self::VERSION);
        $root->setAttribute('xmlns', self::XMLNS);

        $infPedReg = $doc->createElement('infPedReg');
        $infPedReg->setAttribute('Id', $this->generateId($chNFSe, $motivo));

        $infPedReg->appendChild($doc->createElement('tpAmb', $tpAmb));
        $infPedReg->appendChild($doc->createElement('verAplic', $verAplic));
        $infPedReg->appendChild($doc->createElement('dhEvento', $dhEvento));

        if ($cnpjAutor !== null) {
            $infPedReg->appendChild($doc->createElement('CNPJAutor', $cnpjAutor));
        } elseif ($cpfAutor !== null) {
            $infPedReg->appendChild($doc->createElement('CPFAutor', $cpfAutor));
        }

        $infPedReg->appendChild($doc->createElement('chNFSe', $chNFSe));

        $xDesc = match($motivo) {
            MotivoCancelamento::ErroEmissao => 'Cancelamento de NFS-e',
            MotivoCancelamento::Outros      => 'Cancelamento de NFS-e por Substituicao',
        };

        $motivoEl = $doc->createElement($motivo->value);
        $motivoEl->appendChild($doc->createElement('xDesc', $xDesc));
        $motivoEl->appendChild($doc->createElement('cMotivo', $motivo->value));
        $motivoEl->appendChild($doc->createElement('xMotivo', $descricao));
        $infPedReg->appendChild($motivoEl);

        $root->appendChild($infPedReg);
        $doc->appendChild($root);

        // Retorna sem declaração <?xml...?> — NfseClient::cancelar() adiciona uma vez
        return $doc->saveXML($doc->documentElement);
    }

    private function generateId(string $chNFSe, MotivoCancelamento $motivo): string
    {
        $codigo = $motivo === MotivoCancelamento::ErroEmissao ? '101101' : '105102';
        return 'PRE' . $chNFSe . $codigo;
    }
}
```

**Step 4: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Xml/EventoBuilderTest.php --no-coverage
```
Expected: PASS

**Step 5: Commit**

```bash
git add src-new/Xml/Builders/EventoBuilder.php tests/Unit/Xml/EventoBuilderTest.php
git commit -m "feat: add EventoBuilder — XML de cancelamento pedRegEvento"
```

---

## Task 12: NfseHttpClient

**Files:**
- Create: `src-new/Http/NfseHttpClient.php`
- Create: `src-new/NfseNacionalServiceProvider.php` (stub mínimo — expandido na Task 16)
- Create: `tests/Unit/Http/NfseHttpClientTest.php`
- Create: `tests/TestCase.php`
- Modify: `tests/Pest.php` (adicionar `uses()`)

**Context:**
- Usa `Illuminate\Support\Facades\Http` com `Http::fake()` no teste
- mTLS via `tmpfile()`: cria arquivo anônimo, escreve PEM, obtém path via `stream_get_meta_data`, fecha no `finally`
- SSL verificado: `verify => true`
- Resposta de emissão: `{'nfseXmlGZipB64': '...'}` ou `{'erro': '...', 'erros': [...]}`
- Resposta de consulta NFSe: `{'nfseXmlGZipB64': '...'}` — precisa de `base64_decode` + `gzdecode`

**Step 1: Criar stub do ServiceProvider**

`src-new/NfseNacionalServiceProvider.php` (stub mínimo — necessário para o TestCase do Testbench; expandido com bindings reais na Task 16):
```php
<?php

namespace Pulsar\NfseNacional;

use Illuminate\Support\ServiceProvider;

class NfseNacionalServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
```

**Step 2: Escrever testes**

`tests/Unit/Http/NfseHttpClientTest.php`:
```php
<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Http\NfseHttpClient;

// makeTestCertificate() definida em tests/helpers.php (criado na Task 8)

it('posts json payload to given url', function () {
    Http::fake(['*' => Http::response(['sucesso' => true], 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $response = $client->post('https://example.com/nfse', ['key' => 'value']);

    Http::assertSent(fn (Request $req) =>
        $req->url() === 'https://example.com/nfse' &&
        $req->isJson()
    );

    expect($response)->toBe(['sucesso' => true]);
});

it('performs GET request', function () {
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $response = $client->get('https://example.com/nfse/CHAVE123');

    expect($response)->toHaveKey('nfseXmlGZipB64');
});

it('throws HttpException on 5xx response', function () {
    Http::fake(['*' => Http::response(['message' => 'Server Error'], 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('throws HttpException on 4xx response', function () {
    Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('certificate PEM output is valid for mTLS', function () {
    $cert = makeTestCertificate();

    // Verifica que (string)$cert retorna PEM do certificado público válido
    $certPem = (string) $cert;
    expect($certPem)->toContain('-----BEGIN CERTIFICATE-----');
    $parsed = openssl_x509_parse($certPem);
    expect($parsed)->not->toBeFalse();

    // Verifica que privateKey retorna PEM da chave privada válida
    $keyPem = (string) $cert->privateKey;
    expect($keyPem)->toContain('-----BEGIN');
    $key = openssl_pkey_get_private($keyPem);
    expect($key)->not->toBeFalse();
});
```

> **Nota:** Para `Http::fake()` funcionar, os testes precisam de um app Laravel. Configure o `TestCase` com `orchestra/testbench`. O `NfseNacionalServiceProvider` já foi criado no Step 1.

**Step 3: Criar TestCase e atualizar Pest.php**

Criar `tests/TestCase.php`:
```php
<?php

namespace Pulsar\NfseNacional\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Pulsar\NfseNacional\NfseNacionalServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [NfseNacionalServiceProvider::class];
    }
}
```

Atualizar `tests/Pest.php` (adicionar `uses()` ao bootstrap criado na Task 8):
```php
<?php

uses(Pulsar\NfseNacional\Tests\TestCase::class)->in('Unit/Http', 'Feature');

require_once __DIR__ . '/datasets.php';
```

```bash
./vendor/bin/pest tests/Unit/Http/ --no-coverage
```
Expected: FAIL (classe não existe)

**Step 5: Implementar**

`src-new/Http/NfseHttpClient.php`:
```php
<?php

namespace Pulsar\NfseNacional\Http;

use Illuminate\Support\Facades\Http;
use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Exceptions\HttpException;

class NfseHttpClient
{
    public function __construct(
        private readonly Certificate $certificate,
        private readonly int $timeout = 30,
        private readonly bool $sslVerify = true,
    ) {}

    public function post(string $url, array $payload): array
    {
        return $this->request('post', $url, $payload);
    }

    public function get(string $url): array
    {
        return $this->request('get', $url, []);
    }

    private function request(string $method, string $url, array $payload): array
    {
        $certHandle = tmpfile();
        $keyHandle  = tmpfile();

        try {
            fwrite($certHandle, (string) $this->certificate);
            // privateKey é objeto PrivateKey — cast explícito para obter PEM string
            fwrite($keyHandle, (string) $this->certificate->privateKey);

            $certPath = stream_get_meta_data($certHandle)['uri'];
            $keyPath  = stream_get_meta_data($keyHandle)['uri'];

            $pending = Http::timeout($this->timeout)
                ->acceptJson()
                ->withOptions([
                    'verify'  => $this->sslVerify,
                    'cert'    => $certPath,
                    'ssl_key' => $keyPath,
                ]);

            $response = $method === 'post'
                ? $pending->post($url, $payload)
                : $pending->get($url);

            if ($response->serverError() || $response->clientError()) {
                throw new HttpException(
                    'HTTP error: ' . $response->status(),
                    $response->status()
                );
            }

            return $response->json() ?? [];
        } finally {
            fclose($certHandle);
            fclose($keyHandle);
        }
    }
}
```

**Step 6: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Http/ --no-coverage
```
Expected: PASS

**Step 7: Commit**

```bash
git add src-new/Http/ src-new/NfseNacionalServiceProvider.php tests/Unit/Http/ tests/TestCase.php tests/Pest.php
git commit -m "feat: add NfseHttpClient — mTLS via tmpfile, Laravel Http client; stub ServiceProvider"
```

---

## Task 13: ConsultaBuilder

**Files:**
- Create: `src-new/Contracts/NfseClientContract.php`
- Create: `src-new/Consulta/ConsultaBuilder.php`
- Create: `tests/Unit/Consulta/ConsultaBuilderTest.php`

**Context:**
`ConsultaBuilder` recebe um `NfseClient` configurado (ambiente, prefeitura, http client) e expõe `nfse()`, `dps()`, `danfse()`, `eventos()`. O ConsultaBuilder é tipado via `NfseClientContract` para desacoplar da implementação concreta. Recebe também `PrefeituraResolver` + código IBGE para resolver paths customizados por prefeitura (consistente com `emitir`/`cancelar`).

**Step 1: Criar NfseClientContract**

`src-new/Contracts/NfseClientContract.php`:
```php
<?php

namespace Pulsar\NfseNacional\Contracts;

use Pulsar\NfseNacional\DTOs\NfseResponse;

interface NfseClientContract
{
    public function executeGet(string $url): NfseResponse;

    /** Retorna JSON cru da API — com dispatch de events e tratamento de erros padronizado. */
    public function executeGetRaw(string $url): array;
}
```

**Step 2: Escrever testes**

`tests/Unit/Consulta/ConsultaBuilderTest.php`:
```php
<?php

use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Services\PrefeituraResolver;

class FakeNfseClientForConsulta implements NfseClientContract
{
    public array $calls = [];

    public function executeGet(string $url): NfseResponse
    {
        $this->calls[] = $url;
        return new NfseResponse(true, 'chave123', '<xml/>', null);
    }

    public function executeGetRaw(string $url): array
    {
        $this->calls[] = $url;
        return ['sucesso' => true];
    }
}

function makeConsultaBuilder(FakeNfseClientForConsulta $fakeClient): ConsultaBuilder
{
    $resolver = new PrefeituraResolver(__DIR__ . '/../../../storage/prefeituras.json');
    return new ConsultaBuilder($fakeClient, 'https://sefin.base', '', $resolver, '9999999');
}

it('calls executeGet with nfse url for nfse query', function () {
    $fakeClient = new FakeNfseClientForConsulta();
    $builder    = makeConsultaBuilder($fakeClient);

    $response = $builder->nfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($fakeClient->calls[0])->toContain('nfse/CHAVE123');
});

it('calls executeGet with dps url', function () {
    $fakeClient = new FakeNfseClientForConsulta();
    $builder    = makeConsultaBuilder($fakeClient);

    $builder->dps('CHAVE456');

    expect($fakeClient->calls[0])->toContain('dps/CHAVE456');
});
```

> **Nota:** Testes de `danfse()` e `eventos()` que retornam `DanfseResponse`/`EventosResponse` são feature tests na Task 17 (usam `Http::fake()` para simular respostas HTTP reais).

**Step 3: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Consulta/ --no-coverage
```
Expected: FAIL

**Step 4: Implementar ConsultaBuilder**

`src-new/Consulta/ConsultaBuilder.php`:
```php
<?php

namespace Pulsar\NfseNacional\Consulta;

use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\DanfseResponse;
use Pulsar\NfseNacional\DTOs\EventosResponse;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Services\PrefeituraResolver;

final class ConsultaBuilder
{
    public function __construct(
        private readonly NfseClientContract $client,
        private readonly string $seFinBaseUrl,
        private readonly string $adnBaseUrl,
        private readonly PrefeituraResolver $resolver,
        private readonly string $codigoIbge,
    ) {}

    public function nfse(string $chave): NfseResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_nfse', ['chave' => $chave]);
        return $this->client->executeGet($this->buildUrl($this->seFinBaseUrl, $path));
    }

    public function dps(string $chave): NfseResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_dps', ['chave' => $chave]);
        return $this->client->executeGet($this->buildUrl($this->seFinBaseUrl, $path));
    }

    public function danfse(string $chave): DanfseResponse
    {
        $baseUrl = $this->adnBaseUrl ?: $this->seFinBaseUrl;
        $path    = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_danfse', ['chave' => $chave]);

        $result = $this->client->executeGetRaw($this->buildUrl($baseUrl, $path));

        if (isset($result['erros']) || isset($result['erro'])) {
            $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
            return new DanfseResponse(false, null, $erro);
        }

        return new DanfseResponse(true, $result['danfseUrl'] ?? null, null);
    }

    public function eventos(string $chave, int $tipoEvento = 101101, int $nSequencial = 1): EventosResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_eventos', [
            'chave'       => $chave,
            'tipoEvento'  => $tipoEvento,
            'nSequencial' => $nSequencial,
        ]);

        $result = $this->client->executeGetRaw($this->buildUrl($this->seFinBaseUrl, $path));

        if (isset($result['erros']) || isset($result['erro'])) {
            $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
            return new EventosResponse(false, [], $erro);
        }

        return new EventosResponse(true, $result['eventos'] ?? [], null);
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return $baseUrl;
        }
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
```

**Step 5: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Consulta/ --no-coverage
```
Expected: PASS

**Step 6: Commit**

```bash
git add src-new/Contracts/ src-new/Consulta/ tests/Unit/Consulta/
git commit -m "feat: add NfseClientContract + ConsultaBuilder — fluent nfse/dps/danfse/eventos"
```

---

## Task 14: Events

**Files:**
- Create: `src-new/Events/NfseRequested.php`
- Create: `src-new/Events/NfseEmitted.php`
- Create: `src-new/Events/NfseCancelled.php`
- Create: `src-new/Events/NfseQueried.php`
- Create: `src-new/Events/NfseFailed.php`
- Create: `src-new/Events/NfseRejected.php`
- Create: `tests/Unit/Events/EventsTest.php`

**Step 1: Escrever teste**

`tests/Unit/Events/EventsTest.php`:
```php
<?php

use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseFailed;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;

it('NfseRequested carries operacao', function () {
    $event = new NfseRequested('emitir', ['payload']);
    expect($event->operacao)->toBe('emitir');
});

it('NfseEmitted carries chave', function () {
    $event = new NfseEmitted('CHAVE123');
    expect($event->chave)->toBe('CHAVE123');
});

it('NfseCancelled carries chave', function () {
    $event = new NfseCancelled('CHAVE123');
    expect($event->chave)->toBe('CHAVE123');
});

it('NfseQueried carries operacao', function () {
    $event = new NfseQueried('nfse');
    expect($event->operacao)->toBe('nfse');
});

it('NfseFailed carries operacao and message', function () {
    $event = new NfseFailed('emitir', 'Connection timeout');
    expect($event->message)->toBe('Connection timeout');
});

it('NfseRejected carries operacao and codigo', function () {
    $event = new NfseRejected('emitir', 'E001');
    expect($event->codigoErro)->toBe('E001');
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Unit/Events/ --no-coverage
```
Expected: FAIL

**Step 3: Implementar**

`src-new/Events/NfseRequested.php`:
```php
<?php

namespace Pulsar\NfseNacional\Events;

class NfseRequested
{
    public function __construct(
        public readonly string $operacao,
        public readonly array  $metadata = [],
    ) {}
}
```

`src-new/Events/NfseEmitted.php`:
```php
<?php

namespace Pulsar\NfseNacional\Events;

class NfseEmitted
{
    public function __construct(
        public readonly string $chave,
    ) {}
}
```

`src-new/Events/NfseCancelled.php`:
```php
<?php

namespace Pulsar\NfseNacional\Events;

class NfseCancelled
{
    public function __construct(
        public readonly string $chave,
    ) {}
}
```

`src-new/Events/NfseQueried.php`:
```php
<?php

namespace Pulsar\NfseNacional\Events;

class NfseQueried
{
    public function __construct(
        public readonly string $operacao,
    ) {}
}
```

`src-new/Events/NfseFailed.php`:
```php
<?php

namespace Pulsar\NfseNacional\Events;

class NfseFailed
{
    public function __construct(
        public readonly string $operacao,
        public readonly string $message,
    ) {}
}
```

`src-new/Events/NfseRejected.php`:
```php
<?php

namespace Pulsar\NfseNacional\Events;

class NfseRejected
{
    public function __construct(
        public readonly string $operacao,
        public readonly string $codigoErro,
    ) {}
}
```

**Step 4: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Unit/Events/ --no-coverage
```
Expected: PASS

**Step 5: Commit**

```bash
git add src-new/Events/ tests/Unit/Events/
git commit -m "feat: add NfseRequested, NfseEmitted, NfseCancelled, NfseQueried, NfseFailed, NfseRejected events"
```

---

## Task 15: ServiceProvider, Facade e Config

> **Nota reordenação:** Esta task (ServiceProvider) foi movida para antes do NfseClient (Task 16) para que os feature tests da Task 16 possam testar o path via container. A numeração foi trocada em relação ao plano original.

Ir para a seção "ServiceProvider, Facade e Config" abaixo (originalmente Task 16, agora Task 15).

---

## ~~Task 15 (original)~~ Task 16: NfseClient — emitir e consultar

**Files:**
- Create: `src-new/NfseClient.php`
- Create: `tests/fixtures/responses/emitir_sucesso.json`
- Create: `tests/fixtures/responses/emitir_rejeicao.json`
- Create: `tests/fixtures/responses/consultar_nfse.json`
- Create: `tests/fixtures/responses/consultar_dps.json`
- Create: `tests/fixtures/responses/consultar_danfse.json`
- Create: `tests/fixtures/responses/consultar_eventos.json`
- Create: `tests/Feature/NfseClientEmitirTest.php`

**Step 1: Criar fixtures de resposta**

`tests/fixtures/responses/emitir_sucesso.json`:
```json
{
  "chNFSe": "35016082026022700000000000000000000000000000000001",
  "nProtNFSe": "135016080000001"
}
```

`tests/fixtures/responses/emitir_rejeicao.json`:
```json
{
  "erros": [
    {"codigo": "E001", "descricao": "CNPJ do prestador inválido"}
  ]
}
```

`tests/fixtures/responses/consultar_nfse.json`:
```json
{
  "nfseXmlGZipB64": "__PLACEHOLDER__"
}
```
> Substituir `__PLACEHOLDER__` em tempo de teste ou criar o valor correto:
```bash
php -r "echo base64_encode(gzencode('<NFSe xmlns=\"http://www.sped.fazenda.gov.br/nfse\"/>'));"
```
Copiar o output e substituir `__PLACEHOLDER__`.

`tests/fixtures/responses/consultar_dps.json`:
```json
{
  "dpsXmlGZipB64": ""
}
```

`tests/fixtures/responses/consultar_danfse.json`:
```json
{
  "danfseUrl": "https://danfse.exemplo.com/CHAVE123"
}
```

`tests/fixtures/responses/consultar_eventos.json`:
```json
{
  "eventos": []
}
```

**Step 2: Escrever testes de feature**

`tests/Feature/NfseClientEmitirTest.php`:
```php
<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\NfseClient;

// makePfxContent() definida em tests/helpers.php (criado na Task 8)

it('emitir returns success NfseResponse', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_sucesso.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->not->toBeNull();
})->with('dpsData');

it('emitir returns rejection NfseResponse on erro field', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->not->toBeNull();
})->with('dpsData');

it('consultar()->nfse returns success NfseResponse', function () {
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->nfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toContain('<NFSe');
});

it('consultar()->dps returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(['dps' => 'dados'], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->dps('CHAVE123');

    expect($response->sucesso)->toBeTrue();
});

it('throws InvalidArgumentException for invalid IBGE code', function () {
    expect(fn () => NfseClient::for(makePfxContent(), 'secret', '123'))
        ->toThrow(\InvalidArgumentException::class, 'IBGE');
});

it('forStandalone creates client without Laravel container', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_STANDALONE'], 200)]);

    $client = NfseClient::forStandalone(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_STANDALONE');
})->with('dpsData');

it('emitir returns rejection with erros array', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('CNPJ');
})->with('dpsData');
```

**Step 3: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Feature/ --no-coverage
```
Expected: FAIL

**Step 4: Implementar NfseClient**

> **Nota multi-tenant:** `NfseClient::for()` sempre sobrescreve a configuração do container com os parâmetros passados. Isso é intencional para suportar multi-tenant (cada tenant com seu cert/prefeitura). Para single-tenant usando cert/prefeitura do `config/nfse-nacional.php`, usar `app(NfseClient::class)` direto sem `for()`.

`src-new/NfseClient.php`:
```php
<?php

namespace Pulsar\NfseNacional;

use Illuminate\Container\Container;
use Pulsar\NfseNacional\Certificates\CertificateManager;
use Pulsar\NfseNacional\Consulta\ConsultaBuilder;
use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseFailed;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Http\NfseHttpClient;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Signing\XmlSigner;
use Pulsar\NfseNacional\Xml\Builders\EventoBuilder;
use Pulsar\NfseNacional\Xml\DpsBuilder;

class NfseClient implements NfseClientContract
{
    private ?CertificateManager $certManager = null;
    private ?string $prefeitura = null;
    private ?NfseHttpClient $httpClient = null;

    public function __construct(
        private readonly NfseAmbiente $ambiente,
        private readonly int $timeout,
        private readonly string $signingAlgorithm,
        private readonly bool $sslVerify,
        private readonly PrefeituraResolver $prefeituraResolver,
        private readonly DpsBuilder $dpsBuilder,
    ) {}

    /**
     * Factory via Laravel container — usa config do ServiceProvider como base.
     * Se o container Laravel não estiver disponível, faz fallback para forStandalone().
     */
    public static function for(string $pfxContent, string $senha, string $prefeitura): static
    {
        if (class_exists(\Illuminate\Container\Container::class)
            && \Illuminate\Container\Container::getInstance()->bound(static::class)
        ) {
            return app(static::class)->configure($pfxContent, $senha, $prefeitura);
        }

        return static::forStandalone($pfxContent, $senha, $prefeitura);
    }

    /**
     * Factory standalone — não depende do container Laravel.
     */
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
    ): static {
        $jsonPath    = $prefeiturasJsonPath ?? __DIR__ . '/../storage/prefeituras.json';
        $schemasPath = $schemesPath ?? __DIR__ . '/../storage/schemes';

        $instance = new static(
            ambiente:           $ambiente,
            timeout:            $timeout,
            signingAlgorithm:   $signingAlgorithm,
            sslVerify:          $sslVerify,
            prefeituraResolver: new PrefeituraResolver($jsonPath),
            dpsBuilder:         new DpsBuilder($schemasPath),
        );

        return $instance->configure($pfxContent, $senha, $prefeitura);
    }

    public function configure(string $pfxContent, string $senha, string $prefeitura): static
    {
        // Validate IBGE early
        $this->prefeituraResolver->resolveSeFinUrl($prefeitura, $this->ambiente);

        $this->certManager = new CertificateManager($pfxContent, $senha);
        $this->prefeitura  = $prefeitura;
        $this->httpClient  = new NfseHttpClient($this->certManager->getCertificate(), $this->timeout, $this->sslVerify);

        return $this;
    }

    private function ensureConfigured(): void
    {
        if ($this->certManager === null || $this->prefeitura === null || $this->httpClient === null) {
            throw new \Pulsar\NfseNacional\Exceptions\NfseException(
                'NfseClient não configurado. Use NfseClient::for() ou configure certificado/prefeitura no config/nfse-nacional.php.'
            );
        }
    }

    /**
     * Dispatch de events opcional — silencia se não houver dispatcher (modo standalone).
     * Em modo standalone sem Laravel, events não são disparados.
     */
    private function dispatchEvent(object $event): void
    {
        if (function_exists('event')) {
            try {
                event($event);
            } catch (\Throwable) {
                // No event dispatcher available (standalone mode)
            }
        }
    }

    public function emitir(DpsData $data): NfseResponse
    {
        $this->ensureConfigured();
        $operacao = 'emitir';
        $this->dispatchEvent(new NfseRequested($operacao, []));

        try {
            // DpsBuilder retorna sem <?xml...?> (saveXML($doc->documentElement))
            $xml     = $this->dpsBuilder->build($data);
            $signer  = new XmlSigner($this->certManager->getCertificate(), $this->signingAlgorithm);
            // Signer retorna só o elemento assinado — adiciona declaração XML uma única vez
            $signed  = '<?xml version="1.0" encoding="UTF-8"?>' . $signer->sign($xml, 'infDPS', 'DPS');
            $payload = ['dpsXmlGZipB64' => base64_encode(gzencode($signed))];

            $seFinUrl   = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
            $opPath     = $this->prefeituraResolver->resolveOperation($this->prefeitura, 'emitir_nfse');
            $url        = rtrim($seFinUrl, '/') . ($opPath ? '/' . ltrim($opPath, '/') : '');

            $result = $this->httpClient->post($url, $payload);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Rejeição sem descrição';
                $codigo = $result['erros'][0]['codigo'] ?? 'UNKNOWN';
                $this->dispatchEvent(new NfseRejected($operacao, $codigo));
                return new NfseResponse(false, null, null, $erro);
            }

            $chave = $result['chNFSe'] ?? null;
            $this->dispatchEvent(new NfseEmitted($chave ?? ''));

            return new NfseResponse(true, $chave, null, null);
        } catch (HttpException $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Cancelar NFSe. O CNPJ/CPF do autor é extraído do certificado via OID ICP-Brasil.
     * Para certs sem OID ICP-Brasil, getCnpj()/getCpf() retornam string vazia.
     * Testes de cancelar devem usar fake-icpbr.pfx (com OID) para validar extração.
     */
    public function cancelar(string $chave, MotivoCancelamento $motivo, string $descricao): NfseResponse
    {
        $this->ensureConfigured();
        $operacao = 'cancelar';
        $this->dispatchEvent(new NfseRequested($operacao, compact('chave')));

        try {
            $cert = $this->certManager->getCertificate();
            $cnpj = $cert->getCnpj() ?: null;
            $cpf  = $cert->getCpf() ?: null;

            $xml = (new EventoBuilder())->build(
                tpAmb:     $this->ambiente->value,
                verAplic:  '1.0',
                dhEvento:  date('c'),
                cnpjAutor: $cnpj,
                cpfAutor:  $cpf,
                chNFSe:    $chave,
                motivo:    $motivo,
                descricao: $descricao,
            );

            $signer  = new XmlSigner($cert, $this->signingAlgorithm);
            // EventoBuilder retorna sem <?xml...?> — adiciona declaração uma única vez
            $signed  = '<?xml version="1.0" encoding="UTF-8"?>' . $signer->sign($xml, 'infPedReg', 'pedRegEvento');
            $payload = ['pedidoRegistroEventoXmlGZipB64' => base64_encode(gzencode($signed))];

            $seFinUrl  = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
            $opPath    = $this->prefeituraResolver->resolveOperation(
                $this->prefeitura, 'cancelar_nfse', ['chave' => $chave]
            );
            $url = rtrim($seFinUrl, '/') . ($opPath ? '/' . ltrim($opPath, '/') : '');

            $result = $this->httpClient->post($url, $payload);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro   = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Rejeição';
                $codigo = $result['erros'][0]['codigo'] ?? 'UNKNOWN';
                $this->dispatchEvent(new NfseRejected($operacao, $codigo));
                return new NfseResponse(false, null, null, $erro);
            }

            $this->dispatchEvent(new NfseCancelled($chave));
            return new NfseResponse(true, $chave, null, null);
        } catch (HttpException $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    public function consultar(): ConsultaBuilder
    {
        $this->ensureConfigured();
        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
        $adnUrl   = $this->prefeituraResolver->resolveAdnUrl($this->prefeitura, $this->ambiente);
        return new ConsultaBuilder(
            $this, $seFinUrl, $adnUrl,
            $this->prefeituraResolver, $this->prefeitura,
        );
    }

    public function executeGet(string $url): NfseResponse
    {
        $this->ensureConfigured();
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao, compact('url')));

        try {
            $result = $this->httpClient->get($url);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? 'UNKNOWN'));
                return new NfseResponse(false, null, null, $erro);
            }

            // NFSe/DPS XML: descomprime se vier gzip+base64
            $xml = null;
            $gzipB64 = $result['nfseXmlGZipB64'] ?? $result['dpsXmlGZipB64'] ?? null;
            if ($gzipB64) {
                $xml = gzdecode(base64_decode($gzipB64)) ?: null;
            }

            $this->dispatchEvent(new NfseQueried('nfse'));
            return new NfseResponse(true, null, $xml, null);
        } catch (HttpException $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }

    public function executeGetRaw(string $url): array
    {
        $this->ensureConfigured();
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao, compact('url')));

        try {
            $result = $this->httpClient->get($url);

            if (isset($result['erros']) || isset($result['erro'])) {
                $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? 'UNKNOWN'));
                throw new \Pulsar\NfseNacional\Exceptions\NfseException($erro);
            }

            $this->dispatchEvent(new NfseQueried($operacao));
            return $result;
        } catch (HttpException $e) {
            $this->dispatchEvent(new NfseFailed($operacao, $e->getMessage()));
            throw $e;
        }
    }
}
```

**Step 5: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Feature/ --no-coverage
```
Expected: PASS (6 testes)

**Step 6: Commit**

```bash
git add src-new/NfseClient.php tests/Feature/ tests/fixtures/responses/
git commit -m "feat: add NfseClient — emitir, cancelar, consultar com events"
```

---

## ~~Task 16 (original)~~ Task 15: ServiceProvider, Facade e Config

> **Executar ANTES da Task 16 (NfseClient).** O ServiceProvider precisa existir para que os feature tests usem o path via container.

**Files:**
- Create: `src-new/NfseNacionalServiceProvider.php`
- Create: `src-new/Facades/NfseNacional.php`
- Create: `config/nfse-nacional.php`
- Create: `tests/Feature/ServiceProviderTest.php`

**Step 1: Escrever teste**

`tests/Feature/ServiceProviderTest.php`:
```php
<?php

use Pulsar\NfseNacional\Facades\NfseNacional;
use Pulsar\NfseNacional\NfseClient;

it('resolves NfseClient from container', function () {
    $client = app(NfseClient::class);
    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('NfseNacional facade resolves NfseClient', function () {
    expect(NfseNacional::getFacadeRoot())->toBeInstanceOf(NfseClient::class);
});

it('config nfse-nacional is published', function () {
    expect(config('nfse-nacional.ambiente'))->not->toBeNull();
});
```

**Step 2: Rodar para confirmar falha**

```bash
./vendor/bin/pest tests/Feature/ServiceProviderTest.php --no-coverage
```
Expected: FAIL

**Step 3: Criar config**

`config/nfse-nacional.php`:
```php
<?php

use Pulsar\NfseNacional\Enums\NfseAmbiente;

return [
    'ambiente'          => env('NFSE_AMBIENTE', NfseAmbiente::HOMOLOGACAO->value),
    'prefeitura'        => env('NFSE_PREFEITURA', null),
    'certificado' => [
        'path'  => env('NFSE_CERT_PATH'),
        'senha' => env('NFSE_CERT_SENHA'),
    ],
    'timeout'           => env('NFSE_TIMEOUT', 30),
    'signing_algorithm' => env('NFSE_SIGNING_ALGORITHM', 'sha1'),
    'ssl_verify'        => env('NFSE_SSL_VERIFY', true),
];
```

**Step 4: Expandir ServiceProvider** (substitui o stub criado na Task 12)

`src-new/NfseNacionalServiceProvider.php`:
```php
<?php

namespace Pulsar\NfseNacional;

use Illuminate\Support\ServiceProvider;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Xml\DpsBuilder;

class NfseNacionalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nfse-nacional.php', 'nfse-nacional');

        $this->app->bind(NfseClient::class, function ($app) {
            $config   = $app['config']['nfse-nacional'];
            $jsonPath = __DIR__ . '/../storage/prefeituras.json';

            $client = new NfseClient(
                // fromConfig() aceita int|string: '1', '2', 'producao', 'homologacao'
                ambiente:           NfseAmbiente::fromConfig($config['ambiente']),
                timeout:            (int) $config['timeout'],
                signingAlgorithm:   $config['signing_algorithm'],
                sslVerify:          (bool) $config['ssl_verify'],
                prefeituraResolver: new PrefeituraResolver($jsonPath),
                dpsBuilder:         new DpsBuilder(__DIR__ . '/../storage/schemes'),
            );

            // Auto-configurar se cert + prefeitura estão no config
            $certPath    = $config['certificado']['path'] ?? null;
            $certSenha   = $config['certificado']['senha'] ?? null;
            $prefeitura  = $config['prefeitura'] ?? null;

            if ($certPath && $certSenha && $prefeitura && file_exists($certPath)) {
                $client->configure(file_get_contents($certPath), $certSenha, $prefeitura);
            }

            return $client;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/nfse-nacional.php' => config_path('nfse-nacional.php'),
            ], 'nfse-nacional-config');
        }
    }
}
```

**Step 5: Criar Facade**

`src-new/Facades/NfseNacional.php`:
```php
<?php

namespace Pulsar\NfseNacional\Facades;

use Illuminate\Support\Facades\Facade;
use Pulsar\NfseNacional\NfseClient;

/**
 * @method static NfseClient for(string $pfxContent, string $senha, string $prefeitura)
 */
class NfseNacional extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NfseClient::class;
    }
}
```

**Step 6: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Feature/ServiceProviderTest.php --no-coverage
```
Expected: PASS

**Step 7: Rodar toda a suite**

```bash
./vendor/bin/pest --no-coverage
```
Expected: PASS (todos os testes)

**Step 8: Commit**

```bash
git add src-new/NfseNacionalServiceProvider.php src-new/Facades/ config/
git commit -m "feat: add ServiceProvider, Facade e config — NfseClient ligado ao container Laravel"
```

---

## Task 17: Feature test — cancelar, consultar, events

**Files:**
- Create: `tests/fixtures/responses/cancelar_sucesso.json`
- Create: `tests/fixtures/responses/cancelar_rejeicao.json`
- Create: `tests/Feature/NfseClientCancelarTest.php`
- Create: `tests/Feature/NfseClientConsultarTest.php`
- Create: `tests/Feature/EventsDispatchTest.php`

**Step 0: Verificar que helpers.php já está carregado**

`tests/helpers.php` já foi criado na Task 8 com `makePfxContent()` e `makeTestCertificate()`. Verificar que todos os feature tests existentes ainda passam:

```bash
./vendor/bin/pest tests/Feature/ --no-coverage
```
Expected: PASS

**Step 1: Criar fixtures**

`tests/fixtures/responses/cancelar_sucesso.json`:
```json
{
  "chNFSe": "35016082026022700000000000000000000000000000000001",
  "dhRegistro": "2026-02-27T10:00:00-03:00"
}
```

`tests/fixtures/responses/cancelar_rejeicao.json`:
```json
{
  "erros": [
    {"codigo": "E201", "descricao": "NFSe não encontrada para cancelamento"}
  ]
}
```

**Step 2: Escrever testes**

`tests/Feature/NfseClientCancelarTest.php`:
```php
<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\NfseClient;

it('cancelar returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    // Usa fake-icpbr.pfx para que getCnpj() extraia o CNPJ via OID ICP-Brasil
    $pfx      = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client   = NfseClient::for($pfx, 'secret', '3501608');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeTrue();
});

it('cancelar returns rejection NfseResponse on erro field', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_rejeicao.json'), true),
        200
    )]);

    $pfx      = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client   = NfseClient::for($pfx, 'secret', '3501608');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('não encontrada');
});

it('cancelar works with cert without ICP-Brasil OID', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    // Usa fake.pfx (sem OID) — getCnpj() retorna vazio, XML terá CNPJAutor/CPFAutor ausentes
    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeTrue();
});
```

`tests/Feature/NfseClientConsultarTest.php`:
```php
<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\NfseClient;

it('consultar()->danfse returns DanfseResponse with url', function () {
    Http::fake(['*' => Http::response(['danfseUrl' => 'https://danfse.url/CHAVE123'], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->danfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.url/CHAVE123');
});

it('consultar()->eventos returns EventosResponse', function () {
    Http::fake(['*' => Http::response(['eventos' => [['tipo' => '101101']]], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toHaveCount(1);
});

it('consultar()->eventos returns empty array when no events', function () {
    Http::fake(['*' => Http::response(['eventos' => []], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toBeEmpty();
});
```

`tests/Feature/EventsDispatchTest.php`:
```php
<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\NfseClient;

it('dispatches NfseRequested and NfseEmitted on successful emitir', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE123'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $client->emitir($data);

    Event::assertDispatched(NfseRequested::class);
    Event::assertDispatched(NfseEmitted::class);
})->with('dpsData');

it('dispatches NfseCancelled on successful cancelar', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE123'], 200)]);

    $pfx    = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client = NfseClient::for($pfx, 'secret', '3501608');
    $client->cancelar('CHAVE50CARACTERES1234567890123456789012345678901', MotivoCancelamento::ErroEmissao, 'Erro');

    Event::assertDispatched(NfseCancelled::class);
});

it('dispatches NfseQueried on successful consultar', function () {
    Event::fake();
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $client->consultar()->nfse('CHAVE123');

    Event::assertDispatched(NfseQueried::class);
});
```

**Step 3: Rodar para confirmar falha (makePfxContent não está no escopo desses testes)**

`tests/helpers.php` já existe (criado na Task 8 com `makePfxContent()` e `makeTestCertificate()`). `tests/Pest.php` já faz `require_once` de helpers.php desde a Task 8. Nenhuma alteração necessária aqui.

**Step 4: Rodar para confirmar sucesso**

```bash
./vendor/bin/pest tests/Feature/ --no-coverage
```
Expected: PASS

**Step 5: Rodar a suite completa**

```bash
./vendor/bin/pest --no-coverage
```
Expected: PASS (todos os testes)

**Step 6: Commit**

```bash
git add tests/
git commit -m "test: feature tests para cancelar e dispatch de events"
```

---

## Task 18: CHANGELOG e limpeza final

**Files:**
- Create: `CHANGELOG.md` ✅ (já criado)
- Delete: `Helpers.php` ✅ (já deletado)
- Modify: `storage/prefeituras.json` ✅ (já limpo)
- Rename: `src/` → `src-old/`, `src-new/` → `src/`
- Modify: `composer.json` (remover autoload `Hadder\NfseNacional`, apontar `Pulsar` para `src/`, remover `symfony/var-dumper` e `tecnickcom/tcpdf`)
- Update: `CHANGELOG.md` (adicionar itens de remoção de namespace legado e deps)

**Step 1: Renomear diretórios — src/ → src-old/, src-new/ → src/**

```bash
mv src/ src-old/
mv src-new/ src/
```

> O namespace `Pulsar\NfseNacional` passa a viver em `src/` — layout padrão de pacote. O código legado fica em `src-old/` para referência.

**Step 2: Atualizar autoload no composer.json**

Remover `Hadder\NfseNacional` do PSR-4 e apontar `Pulsar\NfseNacional` para `src/`:

```json
"autoload": {
  "psr-4": {
    "Pulsar\\NfseNacional\\": "src/"
  }
}
```

**Step 3: Remover dependências legadas do composer.json**

Remover `tecnickcom/tcpdf` e `symfony/var-dumper` do `require`.

**Step 4: Atualizar CHANGELOG.md**

Adicionar na seção `### Removed`:
- Namespace legado `Hadder\NfseNacional` (autoload removido)
- Dependências `symfony/var-dumper` e `tecnickcom/tcpdf`

**Step 5: Rodar `composer update` e suite completa**

```bash
composer update
./vendor/bin/pest --no-coverage
```

**Step 6: Commit**

```bash
git add -A
git commit -m "chore: rename src-new→src, remoção de autoload/deps legadas"
```

---

### Steps já executados (commit `08efe56`):

- ~~Remover Helpers.php~~ ✅
- ~~Limpar chaves por nome no prefeituras.json~~ ✅
- ~~Criar CHANGELOG.md~~ ✅

```markdown
# Changelog

## [Unreleased]

### Breaking Changes
- Requisito mínimo de PHP alterado de 8.1 para **8.2**
- Namespace alterado de `Hadder\NfseNacional` para `Pulsar\NfseNacional`
- Identificação de prefeituras exclusivamente por **código IBGE** (7 dígitos); suporte a nome legado (`americana-sp`) removido
- API pública completamente nova: `NfseClient::for($pfx, $senha, $ibge)->emitir($dpsData)`

### Added
- `NfseClient::for()` — instância configurada por tenant via container Laravel (com fallback automático para standalone)
- `NfseClient::forStandalone()` — instância sem dependência do container Laravel
- Fluent consulta: `consultar()->nfse/dps/danfse/eventos($chave)`
- DTOs tipados: `DpsData`, `NfseResponse`, `DanfseResponse`, `EventosResponse`
- Laravel Events: `NfseRequested`, `NfseEmitted`, `NfseCancelled`, `NfseQueried`, `NfseFailed`, `NfseRejected`
- mTLS via `tmpfile()` — sem escrita nomeada em disco, sem CNPJ no path
- SSL habilitado corretamente (`verify: true`)
- Validação XSD do DPS via `DpsBuilder::buildAndValidate()`

### Removed
- Namespace legado `Hadder\NfseNacional` (autoload removido)
- Dependências `symfony/var-dumper` e `tecnickcom/tcpdf`
- Arquivo `Helpers.php` com `now()` global (substituído por `illuminate/support`)
- Suporte a identificação de prefeitura por nome (chaves por nome removidas do JSON)
- Chaves duplicadas por nome no `prefeituras.json` (mantido apenas IBGE 7 dígitos)
```

**Step 7: Rodar `composer update` para limpar deps removidas**

```bash
composer update --no-dev
composer install
```

**Step 8: Rodar suite completa uma última vez**

```bash
./vendor/bin/pest --no-coverage
```
Expected: PASS (todos os testes)

**Step 9: Commit final**

```bash
git add -A
git commit -m "chore: CHANGELOG, rename src-new→src, remoção de autoload/deps legadas, Helpers.php e limpeza de prefeituras.json"
```

---

## Pendência: EmissorNacional (investigar antes de implementar)

O código legado possui URLs `nfse_homologacao`/`nfse_producao` (apontando para `EmissorNacional`) e operations `consultar_danfse_nfse_certificado`/`consultar_danfse_nfse_download` que fazem login com certificado via cookie/redirect (origem=3 no `RestCurl`) para download de DANFSe em PDF. Essas funcionalidades **não estão cobertas** neste plano.

**Ação:** Investigar se o fluxo EmissorNacional ainda é necessário ou se as APIs SeFin/ADN já cobrem todos os casos de uso. Se necessário, criar uma Task 19 futura para implementar.

---

## Resumo das tarefas

| # | Tarefa | Arquivos-chave |
|---|--------|----------------|
| 1 | Bootstrap | composer.json (dual autoload), phpunit.xml, fake.pfx, fake-icpbr.pfx, expired.pfx |
| 2 | Enums | NfseAmbiente (+ fromConfig com throw), MotivoCancelamento |
| 3 | Exceptions | NfseException, CertificateExpiredException, HttpException |
| 4 | DTOs | NfseResponse, DpsData, DanfseResponse, EventosResponse |
| 5 | CertificateManager | CertificateManager.php (expired.pfx fixture estática) |
| 6 | PrefeituraResolver | PrefeituraResolver.php |
| 7 | XmlSigner | XmlSigner.php |
| 8 | DpsBuilder cabeçalho + PrestadorBuilder | DpsBuilder.php, PrestadorBuilder.php, Pest.php, datasets.php |
| 9 | TomadorBuilder + ServicoBuilder + ValoresBuilder | Integração DpsBuilder (validação delegada ao XSD) |
| 10 | DpsBuilder XSD validation | DpsBuilder::buildAndValidate() separado de build(); teste op vazia |
| 11 | EventoBuilder | EventoBuilder.php |
| 12 | NfseHttpClient | NfseHttpClient.php, ServiceProvider stub, TestCase.php |
| 13 | NfseClientContract + ConsultaBuilder | Resolver obrigatório, DanfseResponse/EventosResponse tipados |
| 14 | Events | 6 classes: NfseRequested, NfseEmitted, NfseCancelled, NfseQueried, NfseFailed, NfseRejected |
| 15 | NfseClient (emitir + consultar) | for() com fallback standalone, forStandalone(), eventos tipados |
| 16 | ServiceProvider + Facade + Config | Auto-config via config, guard contra uso sem configure() |
| 17 | Feature tests cancelar + consultar + events | cancelar rejeição, danfse, eventos, dispatch events |
| 18 | CHANGELOG + limpeza | CHANGELOG, Helpers.php, prefeituras.json, rename src-new→src, remover autoload Hadder + deps legadas |
