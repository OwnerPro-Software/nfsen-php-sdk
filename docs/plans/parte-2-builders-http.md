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


> **Parte 2 de 3** — Tasks 8–14 (XML Builders + HTTP): DpsBuilder, sub-builders, XSD, EventoBuilder, HttpClient, ConsultaBuilder, Events.

> **Pré-requisitos completos (Parte 1):** Enums, Exceptions, DTOs, CertificateManager, PrefeituraResolver, XmlSigner já existem em `src-new/`. Fixtures de cert (`fake.pfx`, `fake-icpbr.pfx`, `expired.pfx`) estão em `tests/fixtures/certs/`.

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

