# Reescrita NFSe Nacional — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Reescrever o pacote `nfse-nacional` como um pacote Laravel idiomático, multitenante, testável com Pest e sem os bugs estruturais do código original.

**Architecture:** Pacote standalone com Service Provider e Facade. `NfseClient::for($pfx, $senha, $prefeitura)` cria instâncias isoladas por tenant. A camada HTTP usa o `Http::` do Laravel com mTLS. O XML é construído por builders especializados por grupo (prestador, tomador, serviço, valores). `sped-common` cuida do certificado e da assinatura XMLDSig.

**Tech Stack:** PHP 8.2+, Laravel 10+, Pest, nfephp-org/sped-common, orchestra/testbench

---

## Referências rápidas

- XSD dos schemas: `storage/schemes/DPS_v1.01.xsd`, `NFSe_v1.01.xsd`, `pedRegEvento_v1.01.xsd`
- Prefeituras: `storage/prefeituras.json` (formato: `{ "IBGE": { "urls": {...}, "operations": {...} } }`)
- URLs padrão Sefin: `https://sefin.nfse.gov.br/sefinnacional` (prod) / `https://sefin.producaorestrita.nfse.gov.br/SefinNacional` (homolog)
- URLs padrão ADN: `https://adn.nfse.gov.br` (prod) / `https://adn.producaorestrita.nfse.gov.br` (homolog)
- URLs padrão NFSe: `https://www.nfse.gov.br/EmissorNacional` (prod) / `https://www.producaorestrita.nfse.gov.br/EmissorNacional` (homolog)
- Assinatura: XMLDSig SHA1, tag `infDPS`, atributo `Id`, root `DPS`
- Payload emissão: JSON `{"dpsXmlGZipB64": "<base64(gzip(xml_assinado))>"}` via POST
- Payload cancelamento: JSON `{"pedidoRegistroEventoXmlGZipB64": "..."}` via POST

---

## Task 1: Scaffold do pacote

**Files:**
- Modify: `composer.json`
- Create: `src/NfseNacionalServiceProvider.php`
- Create: `src/Facades/Nfsen.php`
- Create: `config/nfse-nacional.php`
- Create: `tests/Pest.php`

**Step 1: Atualizar composer.json**

Substituir o conteúdo por:

```json
{
  "name": "ownerpro/nfsen",
  "description": "Pacote Laravel para geração de NFSe Nacional (padrão Receita Federal)",
  "license": "MIT",
  "type": "library",
  "autoload": {
    "psr-4": {
      "OwnerPro\\Nfsen\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "OwnerPro\\Nfsen\\Tests\\": "tests/"
    }
  },
  "authors": [
    {
      "name": "Fernando Friedrich",
      "email": "fernando@hadder.com.br"
    }
  ],
  "require": {
    "php": "^8.2",
    "nfephp-org/sped-common": "^5.1",
    "illuminate/http": "^10.0|^11.0",
    "illuminate/support": "^10.0|^11.0",
    "ext-dom": "*",
    "ext-zlib": "*",
    "ext-openssl": "*",
    "ext-mbstring": "*"
  },
  "require-dev": {
    "pestphp/pest": "^2.0",
    "orchestra/testbench": "^8.0|^9.0"
  },
  "extra": {
    "laravel": {
      "providers": [
        "OwnerPro\\Nfsen\\NfseNacionalServiceProvider"
      ],
      "aliases": {
        "Nfsen": "OwnerPro\\Nfsen\\Facades\\Nfsen"
      }
    }
  },
  "config": {
    "optimize-autoloader": true
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

**Step 2: Instalar dependências**

```bash
composer install
```

Expected: sem erros, `vendor/` criado.

**Step 3: Criar Service Provider**

```php
<?php
// src/NfseNacionalServiceProvider.php
namespace OwnerPro\Nfsen;

use Illuminate\Support\ServiceProvider;

class NfseNacionalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nfse-nacional.php', 'nfse-nacional');

        $this->app->bind(NfseClient::class, function ($app) {
            $config = $app['config']['nfse-nacional'];
            $pfxContent = file_get_contents($config['certificado']['path']);
            return NfseClient::for($pfxContent, $config['certificado']['senha'], $config['prefeitura'], $config['ambiente']);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/nfse-nacional.php' => config_path('nfse-nacional.php'),
        ], 'config');
    }
}
```

**Step 4: Criar Facade**

```php
<?php
// src/Facades/Nfsen.php
namespace OwnerPro\Nfsen\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \OwnerPro\Nfsen\NfseClient for(string $pfxContent, string $senha, string $prefeitura, int $ambiente = 2)
 */
class Nfsen extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \OwnerPro\Nfsen\NfseClient::class;
    }
}
```

**Step 5: Criar config**

```php
<?php
// config/nfse-nacional.php
return [
    'ambiente'   => env('NFSE_AMBIENTE', 2), // 1=produção, 2=homologação
    'prefeitura' => env('NFSE_PREFEITURA'),
    'certificado' => [
        'path'  => env('NFSE_CERT_PATH'),
        'senha' => env('NFSE_CERT_SENHA', ''),
    ],
    'timeout' => (int) env('NFSE_TIMEOUT', 30),
];
```

**Step 6: Criar Pest.php**

```php
<?php
// tests/Pest.php
uses(OwnerPro\Nfsen\Tests\TestCase::class)->in('Feature', 'Unit');
```

**Step 7: Criar TestCase base**

```php
<?php
// tests/TestCase.php
namespace OwnerPro\Nfsen\Tests;

use OwnerPro\Nfsen\NfseNacionalServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [NfseNacionalServiceProvider::class];
    }
}
```

**Step 8: Commit**

```bash
git add composer.json src/NfseNacionalServiceProvider.php src/Facades/ config/ tests/Pest.php tests/TestCase.php
git commit -m "chore: scaffold do pacote Laravel com Service Provider e Facade"
```

---

## Task 2: Infraestrutura de testes (certificado fake + fixtures)

**Files:**
- Create: `tests/fixtures/certs/` (gerado com openssl)
- Create: `tests/fixtures/responses/emitir_sucesso.json`
- Create: `tests/fixtures/responses/emitir_rejeicao.json`
- Create: `tests/fixtures/responses/consultar_nfse.json`
- Create: `tests/fixtures/responses/consultar_dps.json`
- Create: `tests/fixtures/responses/consultar_eventos.json`
- Create: `tests/fixtures/responses/cancelar_sucesso.json`
- Create: `tests/Helpers.php`

**Step 1: Gerar certificado .pfx de teste**

```bash
mkdir -p tests/fixtures/certs

# Gera chave privada + certificado auto-assinado com CNPJ no CN
openssl req -x509 -newkey rsa:2048 -keyout /tmp/test-key.pem -out /tmp/test-cert.pem \
  -days 3650 -nodes \
  -subj "/C=BR/ST=SP/L=Sao Paulo/O=Teste/OU=TI/CN=12345678000195/emailAddress=teste@teste.com"

# Empacota em .pfx
openssl pkcs12 -export \
  -out tests/fixtures/certs/fake.pfx \
  -inkey /tmp/test-key.pem \
  -in /tmp/test-cert.pem \
  -passout pass:teste123

echo "Certificado gerado com sucesso"
```

Expected: arquivo `tests/fixtures/certs/fake.pfx` criado.

**Step 2: Criar fixtures de resposta JSON**

```json
// tests/fixtures/responses/emitir_sucesso.json
{
  "idDps": "DPS35016082123456789000195000010000000012345678",
  "chaveAcesso": "99160120251201000000123456789000195000010000000012345678901",
  "nfseXmlGZipB64": "H4sIAAAAAAAAA..."
}
```

```json
// tests/fixtures/responses/emitir_rejeicao.json
{
  "codigo": "E155",
  "descricao": "DPS com dados inválidos",
  "mensagem": "O campo cLocEmi é obrigatório"
}
```

```json
// tests/fixtures/responses/consultar_nfse.json
{
  "nfseXmlGZipB64": "H4sIAAAAAAAAA..."
}
```

```json
// tests/fixtures/responses/consultar_dps.json
{
  "idDps": "DPS35016082123456789000195000010000000012345678",
  "situacao": "1",
  "chaveAcesso": "99160120251201000000123456789000195000010000000012345678901"
}
```

```json
// tests/fixtures/responses/consultar_eventos.json
{
  "eventos": []
}
```

```json
// tests/fixtures/responses/cancelar_sucesso.json
{
  "idEvento": "PRE99160120251201000000123456789000195000010000000012345678901101101",
  "situacao": "1"
}
```

**Step 3: Criar helper de teste**

```php
<?php
// tests/Helpers.php
namespace OwnerPro\Nfsen\Tests;

use stdClass;

function fakePfxContent(): string
{
    return file_get_contents(__DIR__.'/fixtures/certs/fake.pfx');
}

function fixture(string $name): array
{
    $path = __DIR__.'/fixtures/responses/'.$name.'.json';
    return json_decode(file_get_contents($path), true);
}

function makeDpsData(): stdClass
{
    $std = new stdClass();
    $std->infDPS = new stdClass();
    $std->infDPS->tpAmb    = 2;
    $std->infDPS->dhEmi    = '2025-12-01T10:00:00-03:00';
    $std->infDPS->verAplic = 'TesteApp_v1.0';
    $std->infDPS->serie    = 1;
    $std->infDPS->nDPS     = 1;
    $std->infDPS->dCompet  = '2025-12-01';
    $std->infDPS->tpEmit   = 1;
    $std->infDPS->cLocEmi  = '3516200';

    $std->infDPS->prest         = new stdClass();
    $std->infDPS->prest->CNPJ   = '12345678000195';
    $std->infDPS->prest->xNome  = 'Empresa Teste LTDA';
    $std->infDPS->prest->end    = new stdClass();
    $std->infDPS->prest->end->endNac       = new stdClass();
    $std->infDPS->prest->end->endNac->cMun = '3516200';
    $std->infDPS->prest->end->endNac->CEP  = '01310100';
    $std->infDPS->prest->end->xLgr   = 'Av. Paulista';
    $std->infDPS->prest->end->nro     = '1000';
    $std->infDPS->prest->end->xBairro = 'Bela Vista';
    $std->infDPS->prest->regTrib              = new stdClass();
    $std->infDPS->prest->regTrib->opSimpNac   = 1;
    $std->infDPS->prest->regTrib->regEspTrib  = 0;

    $std->infDPS->serv                        = new stdClass();
    $std->infDPS->serv->locPrest              = new stdClass();
    $std->infDPS->serv->locPrest->cLocPrestacao = '3516200';
    $std->infDPS->serv->cServ                 = new stdClass();
    $std->infDPS->serv->cServ->cTribNac       = '010101';
    $std->infDPS->serv->cServ->xDescServ      = 'Desenvolvimento de Software';

    $std->infDPS->valores                         = new stdClass();
    $std->infDPS->valores->vServPrest             = new stdClass();
    $std->infDPS->valores->vServPrest->vServ      = '1000.00';
    $std->infDPS->valores->trib                   = new stdClass();
    $std->infDPS->valores->trib->tribMun          = new stdClass();
    $std->infDPS->valores->trib->tribMun->tribISSQN = 1;
    $std->infDPS->valores->trib->tribMun->pAliq   = '2.00';
    $std->infDPS->valores->trib->totTrib          = new stdClass();

    return $std;
}
```

**Step 4: Commit**

```bash
git add tests/fixtures/ tests/Helpers.php
git commit -m "test: adiciona fixtures e helpers de teste"
```

---

## Task 3: Exceções

**Files:**
- Create: `src/Exceptions/NfseException.php`
- Create: `src/Exceptions/CertificateExpiredException.php`
- Create: `src/Exceptions/HttpException.php`

**Step 1: Criar exceções**

```php
<?php
// src/Exceptions/NfseException.php
namespace OwnerPro\Nfsen\Exceptions;

use RuntimeException;

class NfseException extends RuntimeException {}
```

```php
<?php
// src/Exceptions/CertificateExpiredException.php
namespace OwnerPro\Nfsen\Exceptions;

class CertificateExpiredException extends NfseException
{
    public static function expired(): self
    {
        return new self('O certificado digital está expirado.');
    }
}
```

```php
<?php
// src/Exceptions/HttpException.php
namespace OwnerPro\Nfsen\Exceptions;

class HttpException extends NfseException
{
    public static function requestFailed(int $status, string $body): self
    {
        return new self("Requisição à API NFSe falhou com status {$status}: {$body}");
    }
}
```

**Step 2: Commit**

```bash
git add src/Exceptions/
git commit -m "feat: adiciona hierarquia de exceções"
```

---

## Task 4: NfseResponse DTO

**Files:**
- Create: `src/DTOs/NfseResponse.php`
- Create: `tests/Unit/DTOs/NfseResponseTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/DTOs/NfseResponseTest.php
use OwnerPro\Nfsen\DTOs\NfseResponse;

it('cria response de sucesso', function () {
    $response = NfseResponse::sucesso('chave123', '<xml>...</xml>');

    expect($response->sucesso)->toBeTrue()
        ->and($response->chave)->toBe('chave123')
        ->and($response->xml)->toBe('<xml>...</xml>')
        ->and($response->erro)->toBeNull();
});

it('cria response de erro', function () {
    $response = NfseResponse::erro('E155', 'DPS inválida');

    expect($response->sucesso)->toBeFalse()
        ->and($response->chave)->toBeNull()
        ->and($response->xml)->toBeNull()
        ->and($response->erro)->toBe('[E155] DPS inválida');
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/DTOs/NfseResponseTest.php
```

Expected: FAIL — `NfseResponse not found`

**Step 3: Implementar**

```php
<?php
// src/DTOs/NfseResponse.php
namespace OwnerPro\Nfsen\DTOs;

final class NfseResponse
{
    public function __construct(
        public readonly bool    $sucesso,
        public readonly ?string $chave,
        public readonly ?string $xml,
        public readonly ?string $erro,
    ) {}

    public static function sucesso(string $chave, ?string $xml = null): self
    {
        return new self(sucesso: true, chave: $chave, xml: $xml, erro: null);
    }

    public static function erro(string $codigo, string $mensagem): self
    {
        return new self(sucesso: false, chave: null, xml: null, erro: "[{$codigo}] {$mensagem}");
    }

    public static function fromApiResponse(array $data): self
    {
        if (isset($data['codigo'])) {
            return self::erro($data['codigo'], $data['mensagem'] ?? $data['descricao'] ?? 'Erro desconhecido');
        }

        $xml = null;
        if (isset($data['nfseXmlGZipB64'])) {
            $xml = gzdecode(base64_decode($data['nfseXmlGZipB64'])) ?: null;
        }

        return new self(
            sucesso: true,
            chave: $data['chaveAcesso'] ?? null,
            xml: $xml,
            erro: null,
        );
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/DTOs/NfseResponseTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/DTOs/ tests/Unit/DTOs/
git commit -m "feat: adiciona NfseResponse DTO"
```

---

## Task 5: PrefeituraResolver

**Files:**
- Create: `src/Config/PrefeituraResolver.php`
- Create: `tests/Unit/Config/PrefeituraResolverTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/Config/PrefeituraResolverTest.php
use OwnerPro\Nfsen\Config\PrefeituraResolver;

it('retorna URLs padrão quando prefeitura não existe no JSON', function () {
    $resolver = new PrefeituraResolver('9999999', 1);

    expect($resolver->urlEmissao())->toBe('https://sefin.nfse.gov.br/sefinnacional')
        ->and($resolver->urlDanfse())->toBe('https://adn.nfse.gov.br')
        ->and($resolver->urlPortal())->toBe('https://www.nfse.gov.br/EmissorNacional');
});

it('aplica override de URL para prefeitura conhecida', function () {
    $resolver = new PrefeituraResolver('3501608', 2); // homologação

    expect($resolver->urlEmissao())->toBe('https://americanahomologacao.nfe.com.br/api/adn/dps/recepcao');
});

it('retorna path da operação', function () {
    $resolver = new PrefeituraResolver('9999999', 1);

    expect($resolver->operacao('emitir_nfse'))->toBe('nfse')
        ->and($resolver->operacao('consultar_nfse'))->toBe('nfse/{chave}');
});

it('lança exceção para JSON inválido', function () {
    expect(fn () => new PrefeituraResolver('teste', 1, '/caminho/invalido.json'))
        ->toThrow(\RuntimeException::class);
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/Config/PrefeituraResolverTest.php
```

Expected: FAIL

**Step 3: Implementar**

```php
<?php
// src/Config/PrefeituraResolver.php
namespace OwnerPro\Nfsen\Config;

use RuntimeException;

final class PrefeituraResolver
{
    private const DEFAULT_URLS = [
        'sefin_homologacao' => 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional',
        'sefin_producao'    => 'https://sefin.nfse.gov.br/sefinnacional',
        'adn_homologacao'   => 'https://adn.producaorestrita.nfse.gov.br',
        'adn_producao'      => 'https://adn.nfse.gov.br',
        'nfse_homologacao'  => 'https://www.producaorestrita.nfse.gov.br/EmissorNacional',
        'nfse_producao'     => 'https://www.nfse.gov.br/EmissorNacional',
    ];

    private const DEFAULT_OPERATIONS = [
        'consultar_nfse'                     => 'nfse/{chave}',
        'consultar_dps'                      => 'dps/{chave}',
        'consultar_eventos'                  => 'nfse/{chave}/eventos/{tipoEvento}/{nSequencial}',
        'consultar_danfse'                   => 'danfse/{chave}',
        'consultar_danfse_nfse_certificado'  => 'Certificado',
        'consultar_danfse_nfse_download'     => 'Notas/Download/DANFSe/{chave}',
        'emitir_nfse'                        => 'nfse',
        'cancelar_nfse'                      => 'nfse/{chave}/eventos',
    ];

    private array $urls;
    private array $operations;

    public function __construct(
        private readonly string $prefeitura,
        private readonly int    $ambiente,
        private readonly string $jsonPath = '',
    ) {
        $path = $this->jsonPath ?: __DIR__.'/../../storage/prefeituras.json';
        $json = json_decode(file_get_contents($path) ?: '', true);

        if (!is_array($json)) {
            throw new RuntimeException("JSON inválido em {$path}");
        }

        $override = $json[$prefeitura] ?? [];

        $this->urls       = array_merge(self::DEFAULT_URLS,       $override['urls']       ?? []);
        $this->operations = array_merge(self::DEFAULT_OPERATIONS, $override['operations'] ?? []);
    }

    public function urlEmissao(): string
    {
        return $this->ambiente === 1
            ? $this->urls['sefin_producao']
            : $this->urls['sefin_homologacao'];
    }

    public function urlDanfse(): string
    {
        return $this->ambiente === 1
            ? $this->urls['adn_producao']
            : $this->urls['adn_homologacao'];
    }

    public function urlPortal(): string
    {
        return $this->ambiente === 1
            ? $this->urls['nfse_producao']
            : $this->urls['nfse_homologacao'];
    }

    public function operacao(string $nome): string
    {
        return $this->operations[$nome] ?? '';
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/Config/PrefeituraResolverTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Config/ tests/Unit/Config/
git commit -m "feat: adiciona PrefeituraResolver com suporte a override por município"
```

---

## Task 6: CertificateManager

**Files:**
- Create: `src/Certificates/CertificateManager.php`
- Create: `tests/Unit/Certificates/CertificateManagerTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/Certificates/CertificateManagerTest.php
use OwnerPro\Nfsen\Certificates\CertificateManager;
use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;
use function OwnerPro\Nfsen\Tests\fakePfxContent;

require_once __DIR__.'/../../Helpers.php';

it('carrega certificado e expõe paths dos .pem temporários', function () {
    $manager = new CertificateManager(fakePfxContent(), 'teste123');

    expect($manager->certPemPath())->toBeFile()
        ->and($manager->keyPemPath())->toBeFile();
});

it('os arquivos temporários ficam em /tmp escopados pelo CNPJ', function () {
    $manager = new CertificateManager(fakePfxContent(), 'teste123');

    expect($manager->certPemPath())->toContain('/tmp/nfse/');
});

it('remove arquivos temporários ao destruir', function () {
    $manager = new CertificateManager(fakePfxContent(), 'teste123');
    $certPath = $manager->certPemPath();
    $keyPath  = $manager->keyPemPath();

    unset($manager);

    expect($certPath)->not->toBeFile()
        ->and($keyPath)->not->toBeFile();
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/Certificates/CertificateManagerTest.php
```

Expected: FAIL

**Step 3: Implementar**

```php
<?php
// src/Certificates/CertificateManager.php
namespace OwnerPro\Nfsen\Certificates;

use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;
use NFePHP\Common\Certificate;

final class CertificateManager
{
    private Certificate $certificate;
    private string $certPath;
    private string $keyPath;
    private string $dir;

    public function __construct(string $pfxContent, string $senha)
    {
        $this->certificate = Certificate::readPfx($pfxContent, $senha);

        if ($this->certificate->isExpired()) {
            throw CertificateExpiredException::expired();
        }

        $this->dir = $this->resolveDir();
        $this->writePemFiles();
    }

    public function certPemPath(): string
    {
        return $this->certPath;
    }

    public function keyPemPath(): string
    {
        return $this->keyPath;
    }

    public function certificate(): Certificate
    {
        return $this->certificate;
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    private function resolveDir(): string
    {
        $id = $this->certificate->getCnpj() ?: $this->certificate->getCpf();
        $dir = sys_get_temp_dir().'/nfse/'.preg_replace('/\D/', '', $id).'/';

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    private function writePemFiles(): void
    {
        $rand = bin2hex(random_bytes(8));

        $this->certPath = $this->dir.'cert_'.$rand.'.pem';
        $this->keyPath  = $this->dir.'key_'.$rand.'.pem';

        // cert+key combinados (para CURLOPT_SSLCERT com mTLS)
        file_put_contents(
            $this->certPath,
            $this->certificate->privateKey.(string)$this->certificate
        );

        // chave privada isolada (para CURLOPT_SSLKEY)
        file_put_contents($this->keyPath, $this->certificate->privateKey);

        chmod($this->certPath, 0600);
        chmod($this->keyPath, 0600);
    }

    private function cleanup(): void
    {
        foreach ([$this->certPath, $this->keyPath] as $path) {
            if (isset($path) && is_file($path)) {
                unlink($path);
            }
        }
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/Certificates/CertificateManagerTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Certificates/ tests/Unit/Certificates/
git commit -m "feat: adiciona CertificateManager com limpeza via __destruct"
```

---

## Task 7: XmlSigner

**Files:**
- Create: `src/Signing/XmlSigner.php`
- Create: `tests/Unit/Signing/XmlSignerTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/Signing/XmlSignerTest.php
use OwnerPro\Nfsen\Certificates\CertificateManager;
use OwnerPro\Nfsen\Signing\XmlSigner;
use function OwnerPro\Nfsen\Tests\fakePfxContent;

require_once __DIR__.'/../../Helpers.php';

it('injeta elemento Signature no XML', function () {
    $manager = new CertificateManager(fakePfxContent(), 'teste123');
    $signer  = new XmlSigner($manager->certificate());

    $xml = '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse"><infDPS Id="DPS12345"><tpAmb>2</tpAmb></infDPS></DPS>';

    $signed = $signer->sign($xml, 'infDPS', 'DPS');

    expect($signed)->toContain('<Signature')
        ->and($signed)->toContain('<SignatureValue')
        ->and($signed)->toContain('<DigestValue');
});

it('XML assinado é válido e parseável', function () {
    $manager = new CertificateManager(fakePfxContent(), 'teste123');
    $signer  = new XmlSigner($manager->certificate());

    $xml    = '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse"><infDPS Id="DPS12345"><tpAmb>2</tpAmb></infDPS></DPS>';
    $signed = $signer->sign($xml, 'infDPS', 'DPS');

    $dom = new DOMDocument();
    expect($dom->loadXML($signed))->toBeTrue();
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/Signing/XmlSignerTest.php
```

Expected: FAIL

**Step 3: Implementar**

```php
<?php
// src/Signing/XmlSigner.php
namespace OwnerPro\Nfsen\Signing;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

final class XmlSigner
{
    // Canonical params exigidos pela Receita Federal para NFSe
    private array $canonical = [true, false, null, null];

    public function __construct(private readonly Certificate $certificate) {}

    /**
     * Assina o XML com XMLDSig SHA1, conforme padrão NFSe Nacional.
     *
     * @param string $xml     XML sem declaração <?xml?>
     * @param string $tagname Tag que será assinada (ex: 'infDPS', 'infPedReg')
     * @param string $rootname Tag raiz do documento (ex: 'DPS', 'pedRegEvento')
     * @return string XML assinado sem declaração <?xml?>
     */
    public function sign(string $xml, string $tagname, string $rootname): string
    {
        return Signer::sign(
            $this->certificate,
            $xml,
            $tagname,
            'Id',
            OPENSSL_ALGO_SHA1,
            $this->canonical,
            $rootname,
        );
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/Signing/XmlSignerTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Signing/ tests/Unit/Signing/
git commit -m "feat: adiciona XmlSigner (wrapper do sped-common Signer)"
```

---

## Task 8: Builders de XML — infraestrutura base

**Files:**
- Create: `src/Xml/Concerns/BuildsEndereco.php`
- Create: `src/Xml/DpsBuilder.php` (esqueleto)

O trait `BuildsEndereco` evita duplicação entre PrestadorBuilder e TomadorBuilder — endereço nacional/exterior segue o mesmo padrão XML nos dois casos.

**Step 1: Criar trait de endereço**

```php
<?php
// src/Xml/Concerns/BuildsEndereco.php
namespace OwnerPro\Nfsen\Xml\Concerns;

use DOMElement;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

trait BuildsEndereco
{
    private function appendEndereco(Dom $dom, DOMElement $pai, stdClass $end): void
    {
        $endNode = $dom->createElement('end');
        $pai->appendChild($endNode);

        if (isset($end->endNac)) {
            $endNacNode = $dom->createElement('endNac');
            $endNode->appendChild($endNacNode);
            $dom->addChild($endNacNode, 'cMun', $end->endNac->cMun ?? $end->endNac->cmun, true);
            $dom->addChild($endNacNode, 'CEP',  $end->endNac->CEP  ?? $end->endNac->cep,  true);
        } elseif (isset($end->endExt)) {
            $endExtNode = $dom->createElement('endExt');
            $endNode->appendChild($endExtNode);
            $dom->addChild($endExtNode, 'cPais',      $end->endExt->cPais      ?? $end->endExt->cpais,      true);
            $dom->addChild($endExtNode, 'cEndPost',   $end->endExt->cEndPost   ?? $end->endExt->cendpost,   true);
            $dom->addChild($endExtNode, 'xCidade',    $end->endExt->xCidade    ?? $end->endExt->xcidade,    true);
            $dom->addChild($endExtNode, 'xEstProvReg',$end->endExt->xEstProvReg ?? $end->endExt->xestprovreg, true);
        }

        $dom->addChild($endNode, 'xLgr',   $end->xLgr   ?? $end->xlgr,   true);
        $dom->addChild($endNode, 'nro',    $end->nro,                     true);

        if (isset($end->xCpl) || isset($end->xcpl)) {
            $dom->addChild($endNode, 'xCpl', $end->xCpl ?? $end->xcpl);
        }

        $dom->addChild($endNode, 'xBairro', $end->xBairro ?? $end->xbairro, true);
    }
}
```

**Step 2: Criar DpsBuilder (esqueleto)**

```php
<?php
// src/Xml/DpsBuilder.php
namespace OwnerPro\Nfsen\Xml;

use DOMDocument;
use OwnerPro\Nfsen\Xml\Builders\PrestadorBuilder;
use OwnerPro\Nfsen\Xml\Builders\TomadorBuilder;
use OwnerPro\Nfsen\Xml\Builders\ServicoBuilder;
use OwnerPro\Nfsen\Xml\Builders\ValoresBuilder;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

final class DpsBuilder
{
    private Dom $dom;

    public function build(stdClass $data): string
    {
        $std = $this->normalizeKeys($data);

        $this->dom = new Dom('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;

        $version = $std->version ?? '1.01';

        $dps = $this->dom->createElement('DPS');
        $dps->setAttribute('versao', $version);
        $dps->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');

        $infDps = $this->dom->createElement('infDPS');
        $infDps->setAttribute('Id', $this->generateId($std));
        $dps->appendChild($infDps);

        $this->appendCabecalho($infDps, $std->infDPS ?? $std->infdps);

        if (isset(($std->infDPS ?? $std->infdps)->prest)) {
            (new PrestadorBuilder($this->dom))->append($infDps, ($std->infDPS ?? $std->infdps)->prest);
        }

        if (isset(($std->infDPS ?? $std->infdps)->toma)) {
            (new TomadorBuilder($this->dom))->append($infDps, ($std->infDPS ?? $std->infdps)->toma);
        }

        (new ServicoBuilder($this->dom))->append($infDps, ($std->infDPS ?? $std->infdps)->serv);

        (new ValoresBuilder($this->dom))->append($infDps, ($std->infDPS ?? $std->infdps)->valores);

        $this->dom->appendChild($dps);

        // Strip <?xml?> declaration — será adicionada depois da assinatura
        return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->dom->saveXML());
    }

    private function appendCabecalho(\DOMElement $infDps, stdClass $inf): void
    {
        $this->dom->addChild($infDps, 'tpAmb',    $inf->tpAmb    ?? $inf->tpamb,    true);
        $this->dom->addChild($infDps, 'dhEmi',    $inf->dhEmi    ?? $inf->dhemi,    true);
        $this->dom->addChild($infDps, 'verAplic', $inf->verAplic ?? $inf->veraplic, true);
        $this->dom->addChild($infDps, 'serie',    $inf->serie,                      true);
        $this->dom->addChild($infDps, 'nDPS',     $inf->nDPS     ?? $inf->ndps,     true);
        $this->dom->addChild($infDps, 'dCompet',  $inf->dCompet  ?? $inf->dcompet,  true);
        $this->dom->addChild($infDps, 'tpEmit',   $inf->tpEmit   ?? $inf->tpemit,   true);

        if (isset($inf->cMotivoEmisTI) || isset($inf->cmotivoemisti)) {
            $this->dom->addChild($infDps, 'cMotivoEmisTI', $inf->cMotivoEmisTI ?? $inf->cmotivoemisti);
        }
        if (isset($inf->chNFSeRej) || isset($inf->chnfserej)) {
            $this->dom->addChild($infDps, 'chNFSeRej', $inf->chNFSeRej ?? $inf->chnfserej);
        }

        $this->dom->addChild($infDps, 'cLocEmi', $inf->cLocEmi ?? $inf->clocemi, true);

        if (isset($inf->subst)) {
            $subst = $this->dom->createElement('subst');
            $infDps->appendChild($subst);
            $this->dom->addChild($subst, 'chSubstda', $inf->subst->chSubstda ?? $inf->subst->chsubstda, true);
            $this->dom->addChild($subst, 'cMotivo',   $inf->subst->cMotivo   ?? $inf->subst->cmotivo,   true);
            $this->dom->addChild($subst, 'xMotivo',   $inf->subst->xMotivo   ?? $inf->subst->xmotivo,   true);
        }
    }

    private function generateId(stdClass $std): string
    {
        $inf     = $std->infDPS ?? $std->infdps;
        $cLocEmi = $inf->cLocEmi ?? $inf->clocemi;
        $prest   = $inf->prest;
        $hasCnpj = isset($prest->CNPJ) || isset($prest->cnpj);
        $tipo    = $hasCnpj ? 2 : 1;
        $inscricao = $prest->CNPJ ?? $prest->cnpj ?? $prest->CPF ?? $prest->cpf;
        $serie   = $inf->serie;
        $ndps    = $inf->nDPS ?? $inf->ndps;

        return 'DPS'
            .substr($cLocEmi, 0, 7)
            .$tipo
            .str_pad((string)$inscricao, 14, '0', STR_PAD_LEFT)
            .str_pad((string)$serie, 5, '0', STR_PAD_LEFT)
            .str_pad((string)$ndps, 15, '0', STR_PAD_LEFT);
    }

    private function normalizeKeys(stdClass $data): stdClass
    {
        // Aceita tanto camelCase quanto lowercase no input
        return $data;
    }
}
```

**Step 3: Commit**

```bash
git add src/Xml/
git commit -m "feat: adiciona DpsBuilder (esqueleto) e trait BuildsEndereco"
```

---

## Task 9: PrestadorBuilder

**Files:**
- Create: `src/Xml/Builders/PrestadorBuilder.php`
- Create: `tests/Unit/Xml/PrestadorBuilderTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/Xml/PrestadorBuilderTest.php
use OwnerPro\Nfsen\Xml\Builders\PrestadorBuilder;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

function makeDom(): Dom
{
    $dom = new Dom('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    return $dom;
}

it('adiciona CNPJ ao elemento prest', function () {
    $dom = makeDom();
    $pai = $dom->createElement('infDPS');
    $dom->appendChild($pai);

    $prest = new stdClass();
    $prest->CNPJ   = '12345678000195';
    $prest->xNome  = 'Empresa Teste';
    $prest->end    = new stdClass();
    $prest->end->endNac       = new stdClass();
    $prest->end->endNac->cMun = '3516200';
    $prest->end->endNac->CEP  = '01310100';
    $prest->end->xLgr   = 'Av. Paulista';
    $prest->end->nro    = '1000';
    $prest->end->xBairro = 'Bela Vista';
    $prest->regTrib             = new stdClass();
    $prest->regTrib->opSimpNac  = 1;
    $prest->regTrib->regEspTrib = 0;

    (new PrestadorBuilder($dom))->append($pai, $prest);

    $xml = $dom->saveXML();

    expect($xml)->toContain('<CNPJ>12345678000195</CNPJ>')
        ->and($xml)->toContain('<xNome>Empresa Teste</xNome>')
        ->and($xml)->toContain('<cMun>3516200</cMun>')
        ->and($xml)->toContain('<opSimpNac>1</opSimpNac>');
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/PrestadorBuilderTest.php
```

Expected: FAIL

**Step 3: Implementar**

```php
<?php
// src/Xml/Builders/PrestadorBuilder.php
namespace OwnerPro\Nfsen\Xml\Builders;

use DOMElement;
use OwnerPro\Nfsen\Xml\Concerns\BuildsEndereco;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

final class PrestadorBuilder
{
    use BuildsEndereco;

    public function __construct(private readonly Dom $dom) {}

    public function append(DOMElement $pai, stdClass $prest): void
    {
        $node = $this->dom->createElement('prest');
        $pai->appendChild($node);

        if (isset($prest->CNPJ) || isset($prest->cnpj)) {
            $this->dom->addChild($node, 'CNPJ', $prest->CNPJ ?? $prest->cnpj, true);
        } elseif (isset($prest->CPF) || isset($prest->cpf)) {
            $this->dom->addChild($node, 'CPF', $prest->CPF ?? $prest->cpf, true);
        }

        if (isset($prest->NIF) || isset($prest->nif)) {
            $this->dom->addChild($node, 'NIF', $prest->NIF ?? $prest->nif);
        }
        if (isset($prest->cNaoNIF) || isset($prest->cnaonif)) {
            $this->dom->addChild($node, 'cNaoNIF', $prest->cNaoNIF ?? $prest->cnaonif);
        }
        if (isset($prest->CAEPF) || isset($prest->caepf)) {
            $this->dom->addChild($node, 'CAEPF', $prest->CAEPF ?? $prest->caepf);
        }
        if (isset($prest->IM) || isset($prest->im)) {
            $this->dom->addChild($node, 'IM', $prest->IM ?? $prest->im);
        }
        if (isset($prest->xNome) || isset($prest->xnome)) {
            $this->dom->addChild($node, 'xNome', $prest->xNome ?? $prest->xnome);
        }

        if (isset($prest->end)) {
            $this->appendEndereco($this->dom, $node, $prest->end);
        }

        if (isset($prest->fone)) {
            $this->dom->addChild($node, 'fone', $prest->fone);
        }
        if (isset($prest->email)) {
            $this->dom->addChild($node, 'email', $prest->email);
        }

        if (isset($prest->regTrib)) {
            $this->appendRegTrib($node, $prest->regTrib);
        }
    }

    private function appendRegTrib(DOMElement $pai, stdClass $reg): void
    {
        $node = $this->dom->createElement('regTrib');
        $pai->appendChild($node);
        $this->dom->addChild($node, 'opSimpNac',  $reg->opSimpNac  ?? $reg->opsimpnac,  true);
        if (isset($reg->regApTribSN) || isset($reg->regaptribsn)) {
            $this->dom->addChild($node, 'regApTribSN', $reg->regApTribSN ?? $reg->regaptribsn);
        }
        $this->dom->addChild($node, 'regEspTrib', $reg->regEspTrib ?? $reg->regesptrib, true);
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/Xml/PrestadorBuilderTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Xml/Builders/PrestadorBuilder.php tests/Unit/Xml/PrestadorBuilderTest.php
git commit -m "feat: adiciona PrestadorBuilder"
```

---

## Task 10: TomadorBuilder

**Files:**
- Create: `src/Xml/Builders/TomadorBuilder.php`
- Create: `tests/Unit/Xml/TomadorBuilderTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/Xml/TomadorBuilderTest.php
use OwnerPro\Nfsen\Xml\Builders\TomadorBuilder;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

it('adiciona tomador com CPF ao XML', function () {
    $dom = new Dom('1.0', 'UTF-8');
    $pai = $dom->createElement('infDPS');
    $dom->appendChild($pai);

    $toma = new stdClass();
    $toma->CPF   = '12345678901';
    $toma->xNome = 'João da Silva';

    (new TomadorBuilder($dom))->append($pai, $toma);

    $xml = $dom->saveXML();

    expect($xml)->toContain('<CPF>12345678901</CPF>')
        ->and($xml)->toContain('<xNome>João da Silva</xNome>');
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/TomadorBuilderTest.php
```

**Step 3: Implementar**

```php
<?php
// src/Xml/Builders/TomadorBuilder.php
namespace OwnerPro\Nfsen\Xml\Builders;

use DOMElement;
use OwnerPro\Nfsen\Xml\Concerns\BuildsEndereco;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

final class TomadorBuilder
{
    use BuildsEndereco;

    public function __construct(private readonly Dom $dom) {}

    public function append(DOMElement $pai, stdClass $toma): void
    {
        $node = $this->dom->createElement('toma');
        $pai->appendChild($node);

        if (isset($toma->CNPJ) || isset($toma->cnpj)) {
            $this->dom->addChild($node, 'CNPJ', $toma->CNPJ ?? $toma->cnpj, true);
        } elseif (isset($toma->CPF) || isset($toma->cpf)) {
            $this->dom->addChild($node, 'CPF', $toma->CPF ?? $toma->cpf, true);
        } elseif (isset($toma->NIF) || isset($toma->nif)) {
            $this->dom->addChild($node, 'NIF', $toma->NIF ?? $toma->nif, true);
        } elseif (isset($toma->cNaoNIF) || isset($toma->cnaonif)) {
            $this->dom->addChild($node, 'cNaoNIF', $toma->cNaoNIF ?? $toma->cnaonif, true);
        }

        if (isset($toma->CAEPF) || isset($toma->caepf)) {
            $this->dom->addChild($node, 'CAEPF', $toma->CAEPF ?? $toma->caepf);
        }
        if (isset($toma->IM) || isset($toma->im)) {
            $this->dom->addChild($node, 'IM', $toma->IM ?? $toma->im);
        }

        $this->dom->addChild($node, 'xNome', $toma->xNome ?? $toma->xnome, true);

        if (isset($toma->end)) {
            $this->appendEndereco($this->dom, $node, $toma->end);
        }

        if (isset($toma->fone)) {
            $this->dom->addChild($node, 'fone', $toma->fone);
        }
        if (isset($toma->email)) {
            $this->dom->addChild($node, 'email', $toma->email);
        }
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/Xml/TomadorBuilderTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Xml/Builders/TomadorBuilder.php tests/Unit/Xml/TomadorBuilderTest.php
git commit -m "feat: adiciona TomadorBuilder"
```

---

## Task 11: ServicoBuilder

**Files:**
- Create: `src/Xml/Builders/ServicoBuilder.php`
- Create: `tests/Unit/Xml/ServicoBuilderTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/Xml/ServicoBuilderTest.php
use OwnerPro\Nfsen\Xml\Builders\ServicoBuilder;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

it('adiciona grupo serv com locPrest e cServ', function () {
    $dom = new Dom('1.0', 'UTF-8');
    $pai = $dom->createElement('infDPS');
    $dom->appendChild($pai);

    $serv = new stdClass();
    $serv->locPrest = new stdClass();
    $serv->locPrest->cLocPrestacao = '3516200';
    $serv->cServ = new stdClass();
    $serv->cServ->cTribNac  = '010101';
    $serv->cServ->xDescServ = 'Desenvolvimento de Software';

    (new ServicoBuilder($dom))->append($pai, $serv);

    $xml = $dom->saveXML();

    expect($xml)->toContain('<cLocPrestacao>3516200</cLocPrestacao>')
        ->and($xml)->toContain('<cTribNac>010101</cTribNac>')
        ->and($xml)->toContain('<xDescServ>Desenvolvimento de Software</xDescServ>');
});

it('adiciona grupo obra quando presente', function () {
    $dom = new Dom('1.0', 'UTF-8');
    $pai = $dom->createElement('infDPS');
    $dom->appendChild($pai);

    $serv = new stdClass();
    $serv->locPrest = new stdClass();
    $serv->locPrest->cLocPrestacao = '3516200';
    $serv->cServ = new stdClass();
    $serv->cServ->cTribNac  = '010101';
    $serv->cServ->xDescServ = 'Construção';
    $serv->obra = new stdClass();
    $serv->obra->cObra = 'OBRA123';

    (new ServicoBuilder($dom))->append($pai, $serv);

    expect($dom->saveXML())->toContain('<cObra>OBRA123</cObra>');
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/ServicoBuilderTest.php
```

**Step 3: Implementar**

```php
<?php
// src/Xml/Builders/ServicoBuilder.php
namespace OwnerPro\Nfsen\Xml\Builders;

use DOMElement;
use OwnerPro\Nfsen\Xml\Concerns\BuildsEndereco;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

final class ServicoBuilder
{
    use BuildsEndereco;

    public function __construct(private readonly Dom $dom) {}

    public function append(DOMElement $pai, stdClass $serv): void
    {
        $node = $this->dom->createElement('serv');
        $pai->appendChild($node);

        $this->appendLocPrest($node, $serv->locPrest ?? $serv->locprest);
        $this->appendCServ($node, $serv->cServ ?? $serv->cserv);

        if (isset($serv->comExt) || isset($serv->comext)) {
            $this->appendComExt($node, $serv->comExt ?? $serv->comext);
        }

        if (isset($serv->obra)) {
            $this->appendObra($node, $serv->obra);
        }

        if (isset($serv->atvEvento) || isset($serv->atvevento)) {
            $this->appendAtvEvento($node, $serv->atvEvento ?? $serv->atvevento);
        }

        if (isset($serv->infoCompl) || isset($serv->infocompl)) {
            $this->appendInfoCompl($node, $serv->infoCompl ?? $serv->infocompl);
        }
    }

    private function appendLocPrest(DOMElement $pai, stdClass $loc): void
    {
        $node = $this->dom->createElement('locPrest');
        $pai->appendChild($node);
        $this->dom->addChild($node, 'cLocPrestacao', $loc->cLocPrestacao ?? $loc->clocprestacao, true);
        if (isset($loc->cPaisPrestacao) || isset($loc->cpaisprestacao)) {
            $this->dom->addChild($node, 'cPaisPrestacao', $loc->cPaisPrestacao ?? $loc->cpaisprestacao);
        }
    }

    private function appendCServ(DOMElement $pai, stdClass $cserv): void
    {
        $node = $this->dom->createElement('cServ');
        $pai->appendChild($node);
        $this->dom->addChild($node, 'cTribNac',  $cserv->cTribNac  ?? $cserv->ctribnac,  true);
        if (isset($cserv->cTribMun) || isset($cserv->ctribmun)) {
            $this->dom->addChild($node, 'cTribMun', $cserv->cTribMun ?? $cserv->ctribmun);
        }
        $this->dom->addChild($node, 'xDescServ', $cserv->xDescServ ?? $cserv->xdescserv, true);
        if (isset($cserv->cNBS) || isset($cserv->cnbs)) {
            $this->dom->addChild($node, 'cNBS', $cserv->cNBS ?? $cserv->cnbs);
        }
        if (isset($cserv->cIntContrib) || isset($cserv->cintcontrib)) {
            $this->dom->addChild($node, 'cIntContrib', $cserv->cIntContrib ?? $cserv->cintcontrib);
        }
    }

    private function appendComExt(DOMElement $pai, stdClass $com): void
    {
        $node = $this->dom->createElement('comExt');
        $pai->appendChild($node);
        $this->dom->addChild($node, 'mdPrestacao', $com->mdPrestacao ?? $com->mdprestacao);
        $this->dom->addChild($node, 'vincPrest',   $com->vincPrest   ?? $com->vincprest);
        $this->dom->addChild($node, 'tpMoeda',     $com->tpMoeda     ?? $com->tpmoeda);
        $this->dom->addChild($node, 'vServMoeda',  $com->vServMoeda  ?? $com->vservmoeda);
        $this->dom->addChild($node, 'mecAFComexP', $com->mecAFComexP ?? $com->mecafcomexp);
        $this->dom->addChild($node, 'mecAFComexT', $com->mecAFComexT ?? $com->mecafcomext);
        $this->dom->addChild($node, 'movTempBens', $com->movTempBens ?? $com->movtempbens);
        if (isset($com->nDI) || isset($com->ndi)) {
            $this->dom->addChild($node, 'nDI', $com->nDI ?? $com->ndi);
        }
        if (isset($com->nRE) || isset($com->nre)) {
            $this->dom->addChild($node, 'nRE', $com->nRE ?? $com->nre);
        }
        $this->dom->addChild($node, 'mdic', $com->mdic);
    }

    private function appendObra(DOMElement $pai, stdClass $obra): void
    {
        $node = $this->dom->createElement('obra');
        $pai->appendChild($node);
        if (isset($obra->inscImobFisc) || isset($obra->inscimobfisc)) {
            $this->dom->addChild($node, 'inscImobFisc', $obra->inscImobFisc ?? $obra->inscimobfisc);
        }
        if (isset($obra->cObra) || isset($obra->cobra)) {
            $this->dom->addChild($node, 'cObra', $obra->cObra ?? $obra->cobra);
        }
        if (isset($obra->cCIB) || isset($obra->ccib)) {
            $this->dom->addChild($node, 'cCIB', $obra->cCIB ?? $obra->ccib);
        }
        if (isset($obra->end)) {
            $this->appendEnderecoObra($node, $obra->end);
        }
    }

    private function appendEnderecoObra(DOMElement $pai, stdClass $end): void
    {
        $node = $this->dom->createElement('end');
        $pai->appendChild($node);
        if (isset($end->CEP) || isset($end->cep)) {
            $this->dom->addChild($node, 'CEP', $end->CEP ?? $end->cep);
        }
        if (isset($end->xLgr) || isset($end->xlgr)) {
            $this->dom->addChild($node, 'xLgr', $end->xLgr ?? $end->xlgr);
        }
        if (isset($end->nro)) {
            $this->dom->addChild($node, 'nro', $end->nro);
        }
        if (isset($end->xCpl) || isset($end->xcpl)) {
            $this->dom->addChild($node, 'xCpl', $end->xCpl ?? $end->xcpl);
        }
        if (isset($end->xBairro) || isset($end->xbairro)) {
            $this->dom->addChild($node, 'xBairro', $end->xBairro ?? $end->xbairro);
        }
    }

    private function appendAtvEvento(DOMElement $pai, stdClass $atv): void
    {
        $node = $this->dom->createElement('atvEvento');
        $pai->appendChild($node);
        if (isset($atv->xNome) || isset($atv->xnome)) {
            $this->dom->addChild($node, 'xNome', $atv->xNome ?? $atv->xnome);
        }
        if (isset($atv->dtIni) || isset($atv->dtini)) {
            $this->dom->addChild($node, 'dtIni', $atv->dtIni ?? $atv->dtini);
        }
        if (isset($atv->dtFim) || isset($atv->dtfim)) {
            $this->dom->addChild($node, 'dtFim', $atv->dtFim ?? $atv->dtfim);
        }
        if (isset($atv->end)) {
            $this->appendEnderecoObra($node, $atv->end);
        }
    }

    private function appendInfoCompl(DOMElement $pai, stdClass $info): void
    {
        $node = $this->dom->createElement('infoCompl');
        $pai->appendChild($node);
        if (isset($info->idDocTec) || isset($info->iddoctec)) {
            $this->dom->addChild($node, 'idDocTec', $info->idDocTec ?? $info->iddoctec);
        }
        if (isset($info->docRef) || isset($info->docref)) {
            $this->dom->addChild($node, 'docRef', $info->docRef ?? $info->docref);
        }
        if (isset($info->xPed) || isset($info->xped)) {
            $this->dom->addChild($node, 'xPed', $info->xPed ?? $info->xped);
        }
        if (isset($info->gItemPed) || isset($info->gitemped)) {
            $gItem = $this->dom->createElement('gItemPed');
            $node->appendChild($gItem);
            $gItemData = $info->gItemPed ?? $info->gitemped;
            $this->dom->addChild($gItem, 'xItemPed', $gItemData->xItemPed ?? $gItemData->xitemped);
        }
        if (isset($info->xInfComp) || isset($info->xinfcomp)) {
            $this->dom->addChild($node, 'xInfComp', $info->xInfComp ?? $info->xinfcomp);
        }
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/Xml/ServicoBuilderTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Xml/Builders/ServicoBuilder.php tests/Unit/Xml/ServicoBuilderTest.php
git commit -m "feat: adiciona ServicoBuilder com suporte a obra, comExt e atvEvento"
```

---

## Task 12: ValoresBuilder

**Files:**
- Create: `src/Xml/Builders/ValoresBuilder.php`
- Create: `tests/Unit/Xml/ValoresBuilderTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/Xml/ValoresBuilderTest.php
use OwnerPro\Nfsen\Xml\Builders\ValoresBuilder;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

it('adiciona grupo valores com vServ e tribMun', function () {
    $dom = new Dom('1.0', 'UTF-8');
    $pai = $dom->createElement('infDPS');
    $dom->appendChild($pai);

    $valores = new stdClass();
    $valores->vServPrest = new stdClass();
    $valores->vServPrest->vServ = '1000.00';
    $valores->trib = new stdClass();
    $valores->trib->tribMun = new stdClass();
    $valores->trib->tribMun->tribISSQN = 1;
    $valores->trib->tribMun->pAliq = '2.00';
    $valores->trib->totTrib = new stdClass();

    (new ValoresBuilder($dom))->append($pai, $valores);

    $xml = $dom->saveXML();

    expect($xml)->toContain('<vServ>1000.00</vServ>')
        ->and($xml)->toContain('<tribISSQN>1</tribISSQN>')
        ->and($xml)->toContain('<pAliq>2.00</pAliq>');
});

it('não adiciona vDescCondIncond quando zerado', function () {
    $dom = new Dom('1.0', 'UTF-8');
    $pai = $dom->createElement('infDPS');
    $dom->appendChild($pai);

    $valores = new stdClass();
    $valores->vServPrest = new stdClass();
    $valores->vServPrest->vServ = '500.00';
    $valores->vDescCondIncond = new stdClass();
    $valores->vDescCondIncond->vDescIncond = '0.00';
    $valores->vDescCondIncond->vDescCond   = null;
    $valores->trib = new stdClass();
    $valores->trib->tribMun = new stdClass();
    $valores->trib->tribMun->tribISSQN = 1;
    $valores->trib->totTrib = new stdClass();

    (new ValoresBuilder($dom))->append($pai, $valores);

    expect($dom->saveXML())->not->toContain('vDescCondIncond');
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/ValoresBuilderTest.php
```

**Step 3: Implementar**

```php
<?php
// src/Xml/Builders/ValoresBuilder.php
namespace OwnerPro\Nfsen\Xml\Builders;

use DOMElement;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

final class ValoresBuilder
{
    public function __construct(private readonly Dom $dom) {}

    public function append(DOMElement $pai, stdClass $valores): void
    {
        $node = $this->dom->createElement('valores');
        $pai->appendChild($node);

        $this->appendVServPrest($node, $valores->vServPrest ?? $valores->vservprest);

        $descData = $valores->vDescCondIncond ?? $valores->vdesccondincond ?? null;
        if ($descData !== null) {
            $this->appendDescontos($node, $descData);
        }

        $this->appendTrib($node, $valores->trib);
    }

    private function appendVServPrest(DOMElement $pai, stdClass $vsp): void
    {
        $node = $this->dom->createElement('vServPrest');
        $pai->appendChild($node);
        if (isset($vsp->vReceb) || isset($vsp->vreceb)) {
            $this->dom->addChild($node, 'vReceb', $vsp->vReceb ?? $vsp->vreceb);
        }
        $this->dom->addChild($node, 'vServ', $vsp->vServ ?? $vsp->vserv, true);
    }

    private function appendDescontos(DOMElement $pai, stdClass $desc): void
    {
        $vIncond = $desc->vDescIncond ?? $desc->vdescincond ?? null;
        $vCond   = $desc->vDescCond   ?? $desc->vdesccond   ?? null;

        $temIncond = ($vIncond !== null && $vIncond !== '' && $vIncond !== '0.00');
        $temCond   = ($vCond   !== null && $vCond   !== '' && $vCond   !== '0.00');

        if (!$temIncond && !$temCond) {
            return;
        }

        $node = $this->dom->createElement('vDescCondIncond');
        $pai->appendChild($node);
        $this->dom->addChild($node, 'vDescIncond', $vIncond);
        $this->dom->addChild($node, 'vDescCond',   $vCond);
    }

    private function appendTrib(DOMElement $pai, stdClass $trib): void
    {
        $node = $this->dom->createElement('trib');
        $pai->appendChild($node);

        $this->appendTribMun($node, $trib->tribMun ?? $trib->tribmun);

        if (isset($trib->tribFed) || isset($trib->tribfed)) {
            $this->appendTribFed($node, $trib->tribFed ?? $trib->tribfed);
        }

        $totTrib = $this->dom->createElement('totTrib');
        $node->appendChild($totTrib);

        $totData = $trib->totTrib ?? $trib->tottrib ?? null;
        if ($totData !== null && (isset($totData->vTotTrib) || isset($totData->vtottrib))) {
            $vTotNode = $this->dom->createElement('vTotTrib');
            $totTrib->appendChild($vTotNode);
            $vt = $totData->vTotTrib ?? $totData->vtottrib;
            if (isset($vt->vTotTribFed) || isset($vt->vtottribfed)) {
                $this->dom->addChild($vTotNode, 'vTotTribFed', $vt->vTotTribFed ?? $vt->vtottribfed);
            }
            if (isset($vt->vTotTribEst) || isset($vt->vtottribest)) {
                $this->dom->addChild($vTotNode, 'vTotTribEst', $vt->vTotTribEst ?? $vt->vtottribest);
            }
            if (isset($vt->vTotTribMun) || isset($vt->vtottribmun)) {
                $this->dom->addChild($vTotNode, 'vTotTribMun', $vt->vTotTribMun ?? $vt->vtottribmun);
            }
        }
    }

    private function appendTribMun(DOMElement $pai, stdClass $mun): void
    {
        $node = $this->dom->createElement('tribMun');
        $pai->appendChild($node);
        $this->dom->addChild($node, 'tribISSQN', $mun->tribISSQN ?? $mun->tribissqn, true);

        $trib = $mun->tribISSQN ?? $mun->tribissqn;
        if ($trib == 2 && (isset($mun->tpImunidade) || isset($mun->tpimunidade))) {
            $this->dom->addChild($node, 'tpImunidade', $mun->tpImunidade ?? $mun->tpimunidade);
        }
        if ($trib == 3 && (isset($mun->cPaisResult) || isset($mun->cpaisresult))) {
            $this->dom->addChild($node, 'cPaisResult', $mun->cPaisResult ?? $mun->cpaisresult, true);
        }
        if (isset($mun->tpRetISSQN) || isset($mun->tpretissqn)) {
            $this->dom->addChild($node, 'tpRetISSQN', $mun->tpRetISSQN ?? $mun->tpretissqn);
        }
        if (isset($mun->pAliq) || isset($mun->paliq)) {
            $this->dom->addChild($node, 'pAliq', $mun->pAliq ?? $mun->paliq);
        }
    }

    private function appendTribFed(DOMElement $pai, stdClass $fed): void
    {
        $node = $this->dom->createElement('tribFed');
        $pai->appendChild($node);

        if (isset($fed->piscofins)) {
            $pcNode = $this->dom->createElement('piscofins');
            $node->appendChild($pcNode);
            $pc = $fed->piscofins;
            $this->dom->addChild($pcNode, 'CST', $pc->CST ?? $pc->cst, true);
            foreach (['vBCPisCofins'=>'vbcpiscofins','pAliqPis'=>'paliqpis','pAliqCofins'=>'paliqcofins','vPis'=>'vpis','vCofins'=>'vcofins','tpRetPisCofins'=>'tpretpiscofins'] as $tag => $lower) {
                if (isset($pc->$tag) || isset($pc->$lower)) {
                    $this->dom->addChild($pcNode, $tag, $pc->$tag ?? $pc->$lower);
                }
            }
        }

        foreach (['vRetCP'=>'vretcp','vRetIRRF'=>'vretirrf','vRetCSLL'=>'vretcsll'] as $tag => $lower) {
            if (isset($fed->$tag) || isset($fed->$lower)) {
                $this->dom->addChild($node, $tag, $fed->$tag ?? $fed->$lower);
            }
        }
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/Xml/ValoresBuilderTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Xml/Builders/ValoresBuilder.php tests/Unit/Xml/ValoresBuilderTest.php
git commit -m "feat: adiciona ValoresBuilder com tribMun, tribFed e descontos"
```

---

## Task 13: DpsBuilder — integração e validação XSD

**Files:**
- Modify: `src/Xml/DpsBuilder.php` (já existe)
- Create: `tests/Unit/Xml/DpsBuilderTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/Xml/DpsBuilderTest.php
use OwnerPro\Nfsen\Xml\DpsBuilder;
use function OwnerPro\Nfsen\Tests\makeDpsData;

require_once __DIR__.'/../../Helpers.php';

it('gera XML com tag DPS e namespace correto', function () {
    $xml = (new DpsBuilder())->build(makeDpsData());

    expect($xml)->toContain('<DPS')
        ->and($xml)->toContain('xmlns="http://www.sped.fazenda.gov.br/nfse"')
        ->and($xml)->toContain('<infDPS Id="DPS');
});

it('gera Id da DPS com formato correto', function () {
    $xml = (new DpsBuilder())->build(makeDpsData());

    // DPS + cLocEmi(7) + tipo(1) + CNPJ(14) + serie(5) + nDPS(15) = 42 chars
    preg_match('/Id="(DPS[^"]+)"/', $xml, $matches);
    expect(strlen($matches[1]))->toBe(42);
});

it('valida XML gerado contra XSD DPS_v1.01', function () {
    $xml = (new DpsBuilder())->build(makeDpsData());

    $dom = new DOMDocument();
    $dom->loadXML('<?xml version="1.0" encoding="UTF-8"?>'.$xml);

    $xsdPath = __DIR__.'/../../../storage/schemes/DPS_v1.01.xsd';
    $valid = $dom->schemaValidate($xsdPath);

    if (!$valid) {
        $errors = libxml_get_errors();
        $msgs = array_map(fn($e) => $e->message, $errors);
        libxml_clear_errors();
        throw new \Exception("XSD inválido: ".implode('; ', $msgs));
    }

    expect($valid)->toBeTrue();
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/DpsBuilderTest.php
```

Expected: FAIL (classes ainda não conectadas)

**Step 3: Verificar que os builders individuais estão corretamente importados no DpsBuilder**

O `DpsBuilder.php` criado na Task 8 já usa `PrestadorBuilder`, `TomadorBuilder`, `ServicoBuilder` e `ValoresBuilder`. Confirmar que os imports estão corretos e rodar os testes.

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/Xml/DpsBuilderTest.php
```

Expected: PASS (se falhar na validação XSD, ajustar campos obrigatórios no `makeDpsData()` do Helpers.php)

**Step 5: Commit**

```bash
git add src/Xml/DpsBuilder.php tests/Unit/Xml/DpsBuilderTest.php tests/Helpers.php
git commit -m "feat: DpsBuilder completo com validação XSD"
```

---

## Task 14: EventoBuilder (cancelamento)

**Files:**
- Create: `src/Xml/EventoBuilder.php`
- Create: `tests/Unit/Xml/EventoBuilderTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Unit/Xml/EventoBuilderTest.php
use OwnerPro\Nfsen\Xml\EventoBuilder;
use stdClass;

it('gera XML de pedRegEvento com evento e101101', function () {
    $std = new stdClass();
    $std->infPedReg = new stdClass();
    $std->infPedReg->chNFSe   = '99160120251201000000123456789000195000010000000012345678901';
    $std->infPedReg->e101101  = new stdClass();
    $std->infPedReg->e101101->xDesc   = 'Cancelamento';
    $std->infPedReg->e101101->cMotivo = 9;
    $std->infPedReg->e101101->xMotivo = 'Erro nos dados';

    $xml = (new EventoBuilder())->build($std);

    expect($xml)->toContain('<pedRegEvento')
        ->and($xml)->toContain('<chNFSe>')
        ->and($xml)->toContain('<e101101>')
        ->and($xml)->toContain('<cMotivo>9</cMotivo>');
});

it('gera Id no formato PRE + chave(50) + codigo(6)', function () {
    $std = new stdClass();
    $std->infPedReg = new stdClass();
    $std->infPedReg->chNFSe  = '99160120251201000000123456789000195000010000000012345678901';
    $std->infPedReg->e101101 = new stdClass();
    $std->infPedReg->e101101->xDesc   = 'Cancelamento';
    $std->infPedReg->e101101->cMotivo = 9;
    $std->infPedReg->e101101->xMotivo = 'Erro';

    $xml = (new EventoBuilder())->build($std);

    preg_match('/Id="(PRE[^"]+)"/', $xml, $matches);
    expect($matches[1])->toStartWith('PRE')
        ->and(strlen($matches[1]))->toBe(59); // PRE(3) + chave(50) + codigo(6)
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Unit/Xml/EventoBuilderTest.php
```

**Step 3: Implementar**

```php
<?php
// src/Xml/EventoBuilder.php
namespace OwnerPro\Nfsen\Xml;

use NFePHP\Common\DOMImproved as Dom;
use stdClass;

final class EventoBuilder
{
    public function build(stdClass $data): string
    {
        $std = $data;
        $inf = $std->infPedReg;

        $dom = new Dom('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $version = $std->version ?? '1.01';

        $evento = $dom->createElement('pedRegEvento');
        $evento->setAttribute('versao', $version);
        $evento->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');

        $infNode = $dom->createElement('infPedReg');
        $infNode->setAttribute('Id', $this->generateId($inf));
        $evento->appendChild($infNode);

        $dom->addChild($infNode, 'chNFSe', $inf->chNFSe ?? $inf->chnfse, true);

        if (isset($inf->CNPJAutor) || isset($inf->cnpjautor)) {
            $dom->addChild($infNode, 'CNPJAutor', $inf->CNPJAutor ?? $inf->cnpjautor);
        }
        if (isset($inf->CPFAutor) || isset($inf->cpfautor)) {
            $dom->addChild($infNode, 'CPFAutor', $inf->CPFAutor ?? $inf->cpfautor);
        }

        if (isset($inf->e101101)) {
            $eNode = $dom->createElement('e101101');
            $infNode->appendChild($eNode);
            $dom->addChild($eNode, 'xDesc',   $inf->e101101->xDesc   ?? $inf->e101101->xdesc,   true);
            $dom->addChild($eNode, 'cMotivo', $inf->e101101->cMotivo ?? $inf->e101101->cmotivo, true);
            $dom->addChild($eNode, 'xMotivo', $inf->e101101->xMotivo ?? $inf->e101101->xmotivo, true);
        } elseif (isset($inf->e105102)) {
            $eNode = $dom->createElement('e105102');
            $infNode->appendChild($eNode);
            $dom->addChild($eNode, 'xDesc',      $inf->e105102->xDesc      ?? $inf->e105102->xdesc,      true);
            $dom->addChild($eNode, 'cMotivo',    $inf->e105102->cMotivo    ?? $inf->e105102->cmotivo,    true);
            $dom->addChild($eNode, 'xMotivo',    $inf->e105102->xMotivo    ?? $inf->e105102->xmotivo,    true);
            $dom->addChild($eNode, 'chNFSeSubst', $inf->e105102->chNFSeSubst ?? $inf->e105102->chnfsesubst, true);
        }

        $dom->appendChild($evento);

        return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $dom->saveXML());
    }

    private function generateId(stdClass $inf): string
    {
        $chave  = $inf->chNFSe ?? $inf->chnfse;
        $codigo = '000000';

        if (isset($inf->e101101)) {
            $codigo = '101101';
        } elseif (isset($inf->e105102)) {
            $codigo = '105102';
        }

        return 'PRE'.$chave.$codigo;
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Unit/Xml/EventoBuilderTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Xml/EventoBuilder.php tests/Unit/Xml/EventoBuilderTest.php
git commit -m "feat: adiciona EventoBuilder para cancelamento (e101101/e105102)"
```

---

## Task 15: NfseHttpClient

**Files:**
- Create: `src/Http/NfseHttpClient.php`
- Create: `tests/Feature/Http/NfseHttpClientTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Feature/Http/NfseHttpClientTest.php
use OwnerPro\Nfsen\Certificates\CertificateManager;
use OwnerPro\Nfsen\Config\PrefeituraResolver;
use OwnerPro\Nfsen\Http\NfseHttpClient;
use Illuminate\Support\Facades\Http;
use function OwnerPro\Nfsen\Tests\fakePfxContent;
use function OwnerPro\Nfsen\Tests\fixture;

require_once __DIR__.'/../../Helpers.php';

it('faz GET e retorna array decodificado', function () {
    Http::fake([
        '*' => Http::response(fixture('consultar_dps'), 200),
    ]);

    $resolver = new PrefeituraResolver('9999999', 2);
    $cert     = new CertificateManager(fakePfxContent(), 'teste123');
    $client   = new NfseHttpClient($resolver, $cert, 30);

    $result = $client->get('dps/chave123');

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('idDps');
});

it('faz POST e retorna array decodificado', function () {
    Http::fake([
        '*' => Http::response(fixture('emitir_sucesso'), 200),
    ]);

    $resolver = new PrefeituraResolver('9999999', 2);
    $cert     = new CertificateManager(fakePfxContent(), 'teste123');
    $client   = new NfseHttpClient($resolver, $cert, 30);

    $result = $client->post('nfse', json_encode(['dpsXmlGZipB64' => 'dados']));

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('chaveAcesso');
});

it('lança HttpException em erro 500', function () {
    Http::fake([
        '*' => Http::response('Internal Server Error', 500),
    ]);

    $resolver = new PrefeituraResolver('9999999', 2);
    $cert     = new CertificateManager(fakePfxContent(), 'teste123');
    $client   = new NfseHttpClient($resolver, $cert, 30);

    expect(fn () => $client->get('nfse/chave'))
        ->toThrow(\OwnerPro\Nfsen\Exceptions\HttpException::class);
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Feature/Http/NfseHttpClientTest.php
```

**Step 3: Implementar**

```php
<?php
// src/Http/NfseHttpClient.php
namespace OwnerPro\Nfsen\Http;

use OwnerPro\Nfsen\Certificates\CertificateManager;
use OwnerPro\Nfsen\Config\PrefeituraResolver;
use OwnerPro\Nfsen\Exceptions\HttpException;
use Illuminate\Support\Facades\Http;

final class NfseHttpClient
{
    public function __construct(
        private readonly PrefeituraResolver $resolver,
        private readonly CertificateManager $certManager,
        private readonly int $timeout = 30,
    ) {}

    public function get(string $path, int $origem = 1): array|string
    {
        $url      = $this->buildUrl($path, $origem);
        $response = Http::timeout($this->timeout)
            ->withOptions($this->curlOptions())
            ->get($url);

        if ($response->serverError()) {
            throw HttpException::requestFailed($response->status(), $response->body());
        }

        // PDF retorna binário
        if (str_contains($response->header('Content-Type') ?? '', 'application/pdf')) {
            return $response->body();
        }

        return $response->json() ?? [];
    }

    public function post(string $path, string $jsonBody, int $origem = 1): array
    {
        $url      = $this->buildUrl($path, $origem);
        $response = Http::timeout($this->timeout)
            ->withOptions($this->curlOptions())
            ->withBody($jsonBody, 'application/json')
            ->post($url);

        if ($response->serverError()) {
            throw HttpException::requestFailed($response->status(), $response->body());
        }

        return $response->json() ?? [];
    }

    private function buildUrl(string $path, int $origem): string
    {
        $base = match ($origem) {
            2       => $this->resolver->urlDanfse(),
            3       => $this->resolver->urlPortal(),
            default => $this->resolver->urlEmissao(),
        };

        return $path ? rtrim($base, '/').'/'.$path : $base;
    }

    private function curlOptions(): array
    {
        return [
            CURLOPT_SSLCERT    => $this->certManager->certPemPath(),
            CURLOPT_SSLKEY     => $this->certManager->keyPemPath(),
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => 1,
        ];
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Feature/Http/NfseHttpClientTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Http/ tests/Feature/Http/
git commit -m "feat: adiciona NfseHttpClient com mTLS via Laravel Http"
```

---

## Task 16: NfseClient — orquestrador principal

**Files:**
- Create: `src/NfseClient.php`
- Create: `tests/Feature/NfseClientTest.php`

**Step 1: Escrever o teste**

```php
<?php
// tests/Feature/NfseClientTest.php
use OwnerPro\Nfsen\DTOs\NfseResponse;
use OwnerPro\Nfsen\NfseClient;
use Illuminate\Support\Facades\Http;
use function OwnerPro\Nfsen\Tests\fakePfxContent;
use function OwnerPro\Nfsen\Tests\fixture;
use function OwnerPro\Nfsen\Tests\makeDpsData;

require_once __DIR__.'/../Helpers.php';

it('emite DPS e retorna NfseResponse de sucesso', function () {
    Http::fake([
        '*' => Http::response(fixture('emitir_sucesso'), 200),
    ]);

    $client   = NfseClient::for(fakePfxContent(), 'teste123', '9999999', 2);
    $resposta = $client->emitir(makeDpsData());

    expect($resposta)->toBeInstanceOf(NfseResponse::class)
        ->and($resposta->sucesso)->toBeTrue()
        ->and($resposta->chave)->not->toBeEmpty();
});

it('emite DPS e retorna NfseResponse de rejeição', function () {
    Http::fake([
        '*' => Http::response(fixture('emitir_rejeicao'), 200),
    ]);

    $client   = NfseClient::for(fakePfxContent(), 'teste123', '9999999', 2);
    $resposta = $client->emitir(makeDpsData());

    expect($resposta->sucesso)->toBeFalse()
        ->and($resposta->erro)->toContain('E155');
});

it('consulta NFSe por chave', function () {
    Http::fake([
        '*' => Http::response(fixture('consultar_nfse'), 200),
    ]);

    $client   = NfseClient::for(fakePfxContent(), 'teste123', '9999999', 2);
    $resposta = $client->consultarNfse('chave123');

    expect($resposta)->toBeInstanceOf(NfseResponse::class);
});

it('cancela NFSe', function () {
    Http::fake([
        '*' => Http::response(fixture('cancelar_sucesso'), 200),
    ]);

    $std = new stdClass();
    $std->infPedReg = new stdClass();
    $std->infPedReg->chNFSe  = '99160120251201000000123456789000195000010000000012345678901';
    $std->infPedReg->e101101 = new stdClass();
    $std->infPedReg->e101101->xDesc   = 'Cancelamento';
    $std->infPedReg->e101101->cMotivo = 9;
    $std->infPedReg->e101101->xMotivo = 'Erro nos dados';

    $client   = NfseClient::for(fakePfxContent(), 'teste123', '9999999', 2);
    $resposta = $client->cancelar($std);

    expect($resposta)->toBeInstanceOf(NfseResponse::class)
        ->and($resposta->sucesso)->toBeTrue();
});
```

**Step 2: Rodar e verificar falha**

```bash
./vendor/bin/pest tests/Feature/NfseClientTest.php
```

**Step 3: Implementar**

```php
<?php
// src/NfseClient.php
namespace OwnerPro\Nfsen;

use OwnerPro\Nfsen\Certificates\CertificateManager;
use OwnerPro\Nfsen\Config\PrefeituraResolver;
use OwnerPro\Nfsen\DTOs\NfseResponse;
use OwnerPro\Nfsen\Http\NfseHttpClient;
use OwnerPro\Nfsen\Signing\XmlSigner;
use OwnerPro\Nfsen\Xml\DpsBuilder;
use OwnerPro\Nfsen\Xml\EventoBuilder;
use stdClass;

final class NfseClient
{
    private NfseHttpClient $http;
    private XmlSigner      $signer;

    public function __construct(
        private readonly CertificateManager  $certManager,
        private readonly PrefeituraResolver  $resolver,
        private readonly int                 $timeout = 30,
    ) {
        $this->http   = new NfseHttpClient($resolver, $certManager, $timeout);
        $this->signer = new XmlSigner($certManager->certificate());
    }

    public static function for(
        string $pfxContent,
        string $senha,
        string $prefeitura,
        int    $ambiente = 2,
        int    $timeout  = 30,
    ): self {
        $cert     = new CertificateManager($pfxContent, $senha);
        $resolver = new PrefeituraResolver($prefeitura, $ambiente);
        return new self($cert, $resolver, $timeout);
    }

    public function emitir(stdClass $dpsData): NfseResponse
    {
        $xml  = (new DpsBuilder())->build($dpsData);
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>'.$this->signer->sign($xml, 'infDPS', 'DPS');
        $body = json_encode(['dpsXmlGZipB64' => base64_encode(gzencode($xml))]);

        $result = $this->http->post($this->resolver->operacao('emitir_nfse'), $body);

        return NfseResponse::fromApiResponse($result);
    }

    public function consultarNfse(string $chave): NfseResponse
    {
        $path   = str_replace('{chave}', $chave, $this->resolver->operacao('consultar_nfse'));
        $result = $this->http->get($path);
        return NfseResponse::fromApiResponse($result);
    }

    public function consultarDps(string $chave): array
    {
        $path = str_replace('{chave}', $chave, $this->resolver->operacao('consultar_dps'));
        return $this->http->get($path);
    }

    public function consultarEventos(string $chave, ?string $tipoEvento = null, ?string $nSequencial = null): array
    {
        $path = str_replace('{chave}', $chave, $this->resolver->operacao('consultar_eventos'));

        if ($tipoEvento === null) {
            $path = str_replace('/{tipoEvento}/{nSequencial}', '', $path);
        } else {
            $path = str_replace('{tipoEvento}', $tipoEvento, $path);
            $path = $nSequencial !== null
                ? str_replace('{nSequencial}', $nSequencial, $path)
                : str_replace('/{nSequencial}', '', $path);
        }

        return $this->http->get($path);
    }

    public function consultarDanfse(string $chave): string|array
    {
        $path = str_replace('{chave}', $chave, $this->resolver->operacao('consultar_danfse'));
        return $this->http->get($path, 2);
    }

    public function cancelar(stdClass $std): NfseResponse
    {
        $xml  = (new EventoBuilder())->build($std);
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>'.$this->signer->sign($xml, 'infPedReg', 'pedRegEvento');
        $body = json_encode(['pedidoRegistroEventoXmlGZipB64' => base64_encode(gzencode($xml))]);

        $chave = $std->infPedReg->chNFSe ?? $std->infPedReg->chnfse;
        $path  = str_replace('{chave}', $chave, $this->resolver->operacao('cancelar_nfse'));

        $result = $this->http->post($path, $body);

        return NfseResponse::fromApiResponse($result);
    }
}
```

**Step 4: Rodar e verificar passou**

```bash
./vendor/bin/pest tests/Feature/NfseClientTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/NfseClient.php tests/Feature/NfseClientTest.php
git commit -m "feat: adiciona NfseClient com todos os métodos públicos"
```

---

## Task 17: Rodar suite completa e finalizar

**Step 1: Rodar todos os testes**

```bash
./vendor/bin/pest --coverage
```

Expected: todos os testes passando.

**Step 2: Verificar que os arquivos antigos não são mais necessários**

Os arquivos a seguir do projeto original podem ser removidos depois que o novo código estiver validado:
- `src/Tools.php`
- `src/Dps.php`
- `src/DpsInterface.php`
- `src/RestCurl.php`
- `src/Common/RestBase.php`
- `Helpers.php` (raiz — definição de `now()`)
- `exemples/` (substituídos pelos testes)

**Não remover ainda** — aguardar validação em ambiente real.

**Step 3: Commit final**

```bash
git add -A
git commit -m "feat: reescrita completa do pacote nfse-nacional

- Substitui cURL manual por Laravel Http com mTLS correto (SSL verificado)
- Quebra Dps.php monolítico em builders especializados por grupo XML
- Adiciona CertificateManager com limpeza via __destruct
- Adiciona PrefeituraResolver com suporte a override por código IBGE
- Adiciona NfseResponse DTO tipado
- Adiciona suite de testes Pest com fixtures"
```