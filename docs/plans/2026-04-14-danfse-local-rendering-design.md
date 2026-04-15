# Renderização local de DANFSE a partir do XML (design)

**Data:** 2026-04-14
**Status:** Aprovado — pronto para planejamento de implementação

## Motivação

O endpoint ADN (`/danfse/{chave}`) que renderiza o PDF oficial do DANFSE está fora do ar há vários dias. Hoje o SDK depende 100% desse endpoint (`NfseConsulter::danfse()` em `src/Operations/NfseConsulter.php:68`). Precisamos de uma forma de gerar o PDF **localmente**, a partir do XML da NFS-e autorizada que o próprio SDK já retorna em `NfseResponse::$xml`.

O objetivo é entregar um DANFSE **visualmente equivalente** ao PDF oficial da ADN, a partir do XML, sem depender do endpoint nacional.

## Escopo

### Dentro

- Novo ponto de entrada `NfsenClient::danfe(?DanfseConfig $config = null): RendersDanfse` que devolve um renderer capaz de gerar PDF ou HTML do DANFSE.
- Fork interno da lib [`andrevabo/danfse-nacional`](https://github.com/andrevabo/danfse-nacional) (MIT), adaptando código e template ao padrão deste repositório (hexagonal, DTOs tipados, testes Pest).
- Novos contracts (driving + driven), Operation, adapters e value objects de domínio.
- Extensão dos enums existentes com `label()` + `labelOf(?string)` para gerar os textos legíveis no PDF.
- Suporte a customização via `DanfseConfig` (logo do emitente) e `MunicipalityBranding` (identificação do município emissor).
- Marca d'água "SEM VALIDADE JURÍDICA" em homologação.
- QR code obrigatório apontando para `https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave={CHAVE}`.

### Fora

- Não altera `NfseConsulter::danfse(string $chave)` existente — continua batendo no ADN. Os dois coexistem; o cliente decide qual usar.
- Não implementa fallback automático entre ADN e renderização local (decisão fica com o consumidor).
- Não adiciona suporte a emissão/assinatura (isso já existe no SDK).
- Não cria uma lib PHP independente — tudo fica dentro deste pacote.

## Arquitetura

Segue hexagonal / ports & adapters, padrão já estabelecido no repositório.

### Namespaces

| Localização | Namespace |
|---|---|
| `src/Contracts/Driving/` | `OwnerPro\Nfsen\Contracts\Driving` |
| `src/Contracts/Driven/` | `OwnerPro\Nfsen\Contracts\Driven` |
| `src/Operations/` | `OwnerPro\Nfsen\Operations` |
| `src/Adapters/` | `OwnerPro\Nfsen\Adapters` |
| `src/Danfse/` (novo) | `OwnerPro\Nfsen\Danfse` |
| `src/Danfse/Data/` (novo) | `OwnerPro\Nfsen\Danfse\Data` |
| `src/Exceptions/` | `OwnerPro\Nfsen\Exceptions` |

`NfsenServiceProvider` **não é tocado** — `danfe()` constrói o renderer e seus adapters sob demanda; nada precisa ser registrado no container Laravel.

### Driving port

```
src/Contracts/Driving/RendersDanfse.php
```

```php
interface RendersDanfse
{
    public function toPdf(string $xmlNfse): DanfseResponse;
    public function toHtml(string $xmlNfse): string;
}
```

**Semântica:**

- `toPdf()` captura qualquer erro e retorna `DanfseResponse(sucesso: false, erros: [...])` — consistente com `NfseConsulter::danfse()`.
- `toHtml()` propaga exceção em XML inválido. Uso avançado (debug, integração com renderer próprio do consumidor); quem chama esperar exceção explícita.

### Aplicação

```
src/Operations/NfseDanfseRenderer.php
```

Implementa `RendersDanfse`. Orquestra os três driven ports. Recebe-os por construtor (injeção).

### Driven ports

```
src/Contracts/Driven/BuildsDanfseData.php     → XML string → NfseData
src/Contracts/Driven/RendersDanfseHtml.php    → NfseData → HTML string
src/Contracts/Driven/ConvertsHtmlToPdf.php    → HTML string → PDF binário
src/Contracts/Driven/GeneratesQrCode.php      → string → data URI
```

Os ports permitem trocar qualquer implementação (útil para tests e para consumidores que queiram renderer próprio mantendo o resto do pipeline).

### Driven adapters (todos em `src/Adapters/`, mantendo o padrão flat existente)

| Adapter | Port | Responsabilidade |
|---|---|---|
| `DanfseDataBuilder` | `BuildsDanfseData` | Parseia XML via `new SimpleXMLElement($xml, LIBXML_NONET)` (desabilita network/XXE), navega a árvore da NFS-e, aplica `Formatter`, resolve `Municipios::lookup()`, aplica `Enum::labelOf()`, constrói `NfseData`. |
| `DanfseHtmlRenderer` | `RendersDanfseHtml` | Chama `GeneratesQrCode`, aplica `array_walk_recursive` com `htmlspecialchars(ENT_QUOTES\|ENT_SUBSTITUTE)` em todas as strings do `NfseData` **antes** do include (fail-safe: nenhuma interpolação no template precisa escapar manualmente), depois `include` de `storage/danfse/template.php` com buffer de saída. |
| `DompdfHtmlToPdfConverter` | `ConvertsHtmlToPdf` | Wrapper dompdf com opções pré-configuradas (A4 portrait, DejaVu Sans, `isRemoteEnabled=false`). |
| `BaconQrCodeGenerator` | `GeneratesQrCode` | Wrapper `bacon/bacon-qr-code` para SVG data URI. |

### Value objects de domínio

```
src/Danfse/
├── NfseData.php              (readonly DTO display-ready)
├── DanfseConfig.php          (logo + municipality; portado)
├── MunicipalityBranding.php  (portado)
├── Formatter.php             (CNPJ/CEP/moeda/data/telefone — portado)
├── Municipios.php            (lookup IBGE → "Nome - UF", lazy-loaded)
└── Data/
    ├── DanfseParte.php                (emitente/tomador/intermediario)
    ├── DanfseServico.php
    ├── DanfseTributacaoMunicipal.php
    ├── DanfseTributacaoFederal.php
    ├── DanfseTotais.php
    └── DanfseTotaisTributos.php
```

`NfseData` é **achatado por bloco visual**, não reproduz a hierarquia do XSD. Todos os sub-DTOs são `readonly`, com strings já formatadas (ou `'-'` para campo ausente — simplifica o template).

**`DanfseParte` é deliberadamente permissivo:** um único DTO usado para emitente, tomador e intermediário, com todos os campos nullable/string. Emitente não tem `IM`, tomador não tem `regimeSN`, intermediário não tem `endereco` em alguns casos — cada instância preenche só o que for relevante, restante vira `'-'`. Alternativa (três DTOs distintos espelhando o XSD) foi rejeitada: inflaria superfície sem ganho, já que o template consome os três de forma idêntica.

Esquema de `NfseData`:

```php
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

### Assets portados

```
storage/danfse/
├── template.php       (portado de src/Template/danfse.php da lib)
└── logo-nfse.png      (portado de assets/logo-nfse.png da lib)

storage/
└── ibge-municipios.json   (portado de src/Data/Municipios.php — tabela completa IBGE)
```

### Enums estendidos (aditivo, não invasivo)

Os enums abaixo **já existem** no repositório com mesmos casos e valores da lib. Apenas adicionamos métodos `label()` e `labelOf(?string)`:

| Enum | Arquivo |
|---|---|
| `OpSimpNac` | `src/Dps/Enums/Prest/OpSimpNac.php` |
| `RegApTribSN` | `src/Dps/Enums/Prest/RegApTribSN.php` |
| `RegEspTrib` | `src/Dps/Enums/Prest/RegEspTrib.php` |
| `TpRetISSQN` | `src/Dps/Enums/Valores/TpRetISSQN.php` |
| `TribISSQN` | `src/Dps/Enums/Valores/TribISSQN.php` |
| `NfseAmbiente` | `src/Enums/NfseAmbiente.php` |

Convenção:

```php
public function label(): string       // label do caso atual
public static function labelOf(?string $value): string  // '-' se null/inválido
```

### Entry point

```php
// NfsenClient
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

**`NfsenClient::for()` e `NfsenClient::forStandalone()` ficam intocados.** O renderer é construído sob demanda em `danfe()`, sem estado DANFSE no client.

## Fluxo de dados

```
XML NFS-e autorizada (string)
      │
      │  DanfseDataBuilder (BuildsDanfseData)
      │    1. SimpleXMLElement + namespace http://www.sped.fazenda.gov.br/nfse
      │    2. Navega infNFSe → emit / DPS/infDPS / prest / toma / interm / serv / valores
      │    3. Formatter aplica máscaras (CNPJ, CEP, moeda BR, data, telefone)
      │    4. Enum::labelOf() para campos codificados (tribISSQN, opSimpNac, ...)
      │    5. Municipios::lookup(cMun) para cidades do tomador/intermediário
      │    6. Extrai chave de acesso do atributo Id (remove prefixo "NFS")
      ▼
NfseData (readonly)
      │
      │  DanfseHtmlRenderer (RendersDanfseHtml)
      │    1. GeneratesQrCode::dataUri(URL de consulta) → SVG embutido
      │    2. htmlspecialchars em strings
      │    3. include storage/danfse/template.php com ob_start()/ob_get_clean()
      ▼
HTML (string UTF-8)
      │
      │  DompdfHtmlToPdfConverter (ConvertsHtmlToPdf)
      │    Options: isHtml5ParserEnabled=true, isRemoteEnabled=false,
      │             defaultFont='DejaVu Sans', isFontSubsettingEnabled=true
      │    setPaper('A4', 'portrait')
      ▼
PDF binário (string)
      │
      ▼
DanfseResponse(sucesso: true, pdf: $bytes)
```

## Uso

```php
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

$client = NfsenClient::for('tenant');   // ou ::forStandalone(...)

// Passo 1: emite (usa pipeline existente)
$response = $client->emitir($dps);

// Passo 2: gera o DANFSE a partir do XML retornado
$danfse = $client->danfe()->toPdf($response->xml);

file_put_contents('danfse.pdf', $danfse->pdf);
```

Com customização:

```php
$config = new DanfseConfig(
    logoPath: storage_path('app/logo.png'),
    municipality: new MunicipalityBranding(
        name: 'Município de Canela',
        department: '(54) 3282-5155',   // prefeituras usam livremente este campo
        email: 'issqn@canela.rs.gov.br',
        logoPath: storage_path('app/brasao-canela.png'),
    ),
);

$html = $client->danfe($config)->toHtml($response->xml);  // para debug
```

## Dependências

### Adicionadas em `composer.json`

```json
"dompdf/dompdf": "^3.0",
"bacon/bacon-qr-code": "^3.0"
```

Ambas MIT, compatíveis com PHP 8.3+, sem amarração a framework.

### Descartadas da lib original

- `cuyz/valinor` — construímos `NfseData` direto do `SimpleXMLElement` no `DanfseDataBuilder`. Elimina dep pesada e ~25 DTOs intermediários espelhando o XSD.

## Tratamento de erros

### Nova exceção

```
src/Exceptions/XmlParseException.php   extends NfseException
```

Lançada pelo `DanfseDataBuilder` quando o XML for vazio, malformado, sem namespace NFS-e esperado (`http://www.sped.fazenda.gov.br/nfse`), ou sem o nó raiz `NFSe/infNFSe`. Estende `NfseException` (base já existente em `src/Exceptions/NfseException.php`).

### Captura em `toPdf()` vs propagação em `toHtml()`

| Origem | Captura em `toPdf()` | Comportamento em `toHtml()` |
|---|---|---|
| `XmlParseException` (XML inválido) | `DanfseResponse(sucesso: false, erros: [new ProcessingMessage(descricao: 'XML da NFS-e inválido ou malformado.', complemento: $e->getMessage())])` | Propaga `XmlParseException` |
| `\Dompdf\Exception` | `DanfseResponse(sucesso: false, erros: [new ProcessingMessage(descricao: 'Falha ao renderizar o PDF.', complemento: $e->getMessage())])` | Não aplicável (`toHtml()` não chama dompdf) |
| Qualquer outro `\Throwable` | `DanfseResponse(sucesso: false, erros: [new ProcessingMessage(descricao: 'Erro inesperado ao gerar DANFSE.', complemento: $e->getMessage())])` | Propaga |

**Regra:** `descricao` é estável (string fixa em PT-BR, útil para o usuário final); `complemento` preserva `$e->getMessage()` para diagnóstico sem exposição no campo primário. Campo `complemento` já existe em `src/Responses/ProcessingMessage.php:25`.

`DanfseResponse` é o DTO existente em `src/Responses/DanfseResponse.php`.

## Performance

- Parse do XML: O(tamanho do XML) — NFS-e típica tem < 20KB.
- Lookup de `Municipios`: carrega JSON (~251KB, 5571 entradas) lazy no primeiro `lookup()` do processo → ~1.3ms. Subsequentes são O(1) via static cache.
- Dompdf (renderização): ~100-500ms para uma página A4 (dominante; os outros passos são desprezíveis).

Static cache em `Municipios`:

```php
final class Municipios
{
    /** @var array<int,array{nome:string,uf:string}>|null */
    private static ?array $map = null;

    public static function lookup(string|int $cMun): string
    {
        self::$map ??= json_decode(
            (string) file_get_contents(__DIR__.'/../../storage/ibge-municipios.json'),
            true,
        );
        $m = self::$map[(int) $cMun] ?? null;
        return $m !== null ? $m['nome'].' - '.$m['uf'] : '-';
    }
}
```

## Testes

### Unitários

- `DanfseDataBuilderTest` — XMLs-fixture → asserts nos campos do `NfseData` (cobre Formatter, Municipios, labels dos enums). Inclui caso de XML malformado → `XmlParseException`, XML sem namespace esperado → `XmlParseException`, XML sem `infNFSe` → `XmlParseException`.
- `DanfseHtmlRendererTest` — `NfseData` → HTML contém strings esperadas. Cenários por branch do template:
  - Produção → sem marca d'água "SEM VALIDADE JURÍDICA"
  - Homologação → com marca d'água
  - Sem intermediário → bloco "NÃO IDENTIFICADO NA NFS-e"
  - Com intermediário → bloco preenchido com dados
  - Com `MunicipalityBranding` → nome + email + department (se presente) + brasão renderizados
  - Sem `MunicipalityBranding` → espaço vazio no cabeçalho
  - Com logo de empresa custom → data URI substitui logo default
  - Sem logo (`logoPath: false`) → cabeçalho sem logo
- `DompdfHtmlToPdfConverterTest` — HTML mínimo → output inicia com `%PDF-`; HTML inválido → `\Dompdf\Exception`.
- `BaconQrCodeGeneratorTest` — entrada string → data URI SVG válido (base64, prefixo `data:image/svg+xml;base64,`).
- `NfseDanfseRendererTest` — ports mockados; verifica orquestração:
  - `toPdf()` sucesso → delega em ordem builder → renderer → converter e embala em `DanfseResponse(sucesso: true)`
  - `toPdf()` com `XmlParseException` do builder → `DanfseResponse(sucesso: false)` com `descricao` e `complemento` esperados
  - `toPdf()` com `\Dompdf\Exception` do converter → idem
  - `toPdf()` com `\Throwable` genérico → idem com `descricao` genérica
  - `toHtml()` sucesso → retorna HTML; `toHtml()` com exceção → propaga
- `FormatterTest` — caso por método (CNPJ válido/inválido, CEP, moeda zero/negativo, datas ISO, telefone sem DDD, código de tributação).
- `MunicipiosTest` — lookup válido → "Nome - UF"; inválido → `'-'`; cache sharing (duas chamadas consecutivas usam o mesmo array em memória — verificado via mudança no arquivo JSON não afetar a segunda chamada).
- `DanfseConfigTest` — `logoPath` arquivo existente → data URI com MIME correto; `logoPath: false` → sem logo; `logoPath` inexistente → `\InvalidArgumentException`; `logoDataUri` + `logoPath` juntos → `logoDataUri` tem precedência; sem argumentos → usa logo default do pacote.
- `MunicipalityBrandingTest` — todos os campos opcionais; logo próprio do município; `department` e `email` vazios não quebram.
- Enums estendidos: um teste por enum cobrindo `label()` em todos os casos + `labelOf(null)` e `labelOf('valor-inexistente')` → `'-'`.

### Integração

- `DanfseRenderIntegrationTest`:
  - XML real autorizado → `$client->danfe()->toPdf($xml)` → `sucesso === true`, PDF inicia com `%PDF-`.
  - Extrair texto do PDF via **`smalot/pdfparser` (dev dep)** — escolhido em vez de `pdftotext` CLI por ser puro-PHP (não depende de binário no CI). Verificar campos-chave: chave de acesso, número NFS-e, emitente, tomador, valor líquido.
  - XML de homologação → PDF contém texto "SEM VALIDADE JURÍDICA".
  - Com `DanfseConfig(municipality: ...)` → PDF contém nome do município.
  - Variações de intermediário cobertas via fixtures distintas.

### Fixtures necessárias

```
tests/fixtures/danfse/
├── nfse-autorizada.xml                  (XML real — PDF Canela se possível)
├── nfse-autorizada-intermediario.xml
└── nfse-homologacao.xml
```

Se o XML real da Canela não estiver disponível, usamos o exemplo de `andrevabo/danfse-nacional/examples/` como baseline. Fixtures ficam sob `tests/fixtures/danfse/` (padrão do repo — já existe `tests/fixtures/certs/` para certificados).

### Coberturas (obrigatórias pelo `CLAUDE.md`)

- **100% line coverage** — alcançável em todos os adapters e operations. O template em `storage/danfse/template.php` é view; excluído do coverage via configuração do `phpunit.xml` (mas validado pelos testes de `DanfseHtmlRenderer` que inspecionam o HTML output).
- **100% mutation coverage** — maior risco em `Formatter` (muitos `match`, formatações) e enums estendidos. Attingível com assertions explícitas por caso. `@pest-mutate-ignore` **só** se justificado com comentário.
- **100% type coverage** — todos os novos arquivos com tipos completos; DTOs `readonly` com tipos.

### Fora do coverage/análise estática

- `src/Danfse/Municipios.php` — lógica coberta; dados (`storage/ibge-municipios.json`) não são código.
- `storage/danfse/template.php` — excluir do PHPStan/Psalm (é view PHP, não lógica de negócio).

## Documentação

- **`README.md`:** nova seção "Gerando o DANFSE localmente" com exemplos `danfe()->toPdf()` e `danfe()->toHtml()`, esclarecendo que é alternativa local ao endpoint ADN.
- **`README.md`:** seção de créditos/atribuição com link para `andrevabo/danfse-nacional` (MIT) e `kelvins/municipios-brasileiros` (MIT).
- **`CHANGELOG.md`:** entrada `feat` com escopo user-facing.
- **`CLAUDE.md`:** se a implementação introduzir gotchas (ex.: Dompdf fonts, marca d'água em homologação), documentar na seção "Gotchas".

## Licenças

A lib `andrevabo/danfse-nacional` é **MIT**. Arquivos portados devem conter header de atribuição:

```php
/**
 * Portado de andrevabo/danfse-nacional (https://github.com/andrevabo/danfse-nacional)
 * Licença original MIT. Modificações são deste repositório.
 */
```

Aplicado a: template, `Formatter`, dados de `Municipios`, `DanfseConfig`, `MunicipalityBranding`.

## Segurança

| Vetor | Mitigação |
|---|---|
| **XXE / SSRF via XML** | `new SimpleXMLElement($xml, LIBXML_NONET)` desabilita resolução de entidades externas e carregamento de rede. PHP 8+ já desabilita XXE por default, mas passamos o flag explicitamente como hardening documentado. |
| **SSRF via dompdf** (imagens remotas em `<img src="http://...">`) | `Options::set('isRemoteEnabled', false)` — dompdf não baixa recursos externos. Apenas data URIs e paths locais do próprio pacote. |
| **XSS via conteúdo do XML** | Escape fail-safe: `array_walk_recursive` no `NfseData` aplicando `htmlspecialchars(ENT_QUOTES\|ENT_SUBSTITUTE, 'UTF-8')` **antes** do include do template. Elimina risco de esquecimento em interpolações individuais do template. |
| **Logo loading com path arbitrário** (`DanfseConfig::logoPath`) | Validação em 3 camadas, portada da lib: (1) `is_readable()` — arquivo existe e é legível, senão `\InvalidArgumentException`; (2) `mime_content_type()` detecta MIME; (3) embutido como data URI no HTML (não usado em `<img src="file://...">`). Para Psalm `--taint-analysis`, usar `@psalm-taint-escape html` justificado no construtor de `DanfseConfig` — o path nunca é injetado em HTML, só lido para virar base64. |
| **Path traversal** via `logoPath` | Não é ameaça: o consumidor do SDK é quem fornece o path; estamos no mesmo domínio de confiança que qualquer outro argumento. |

## Riscos e mitigações

| Risco | Mitigação |
|---|---|
| **Fontes do dompdf** — Arial não vem embutido por padrão | Usar DejaVu Sans (default nativo do dompdf). Fidelidade visual preservada. |
| **Template PHP com `include`** | Adapter `DanfseHtmlRenderer` testado via assertions no HTML. Template em si não entra em coverage/análise. |
| **Ausência de `cuyz/valinor`** — perdemos validação estruturada do array XML | Aceitável: XML de NFS-e autorizada tem estrutura garantida pelo XSD federal. Parser lança `XmlParseException` em malformados (XML inválido, sem namespace NFS-e, sem `infNFSe`). |
| **Registro de fonte no Dompdf** — DejaVu Sans já vem embutido, mas cold start com font registration custa alguns ms | Aceitável: custo dominado pelo render do PDF (~100-500ms). Não requer tratamento especial. |
| **`ibge-municipios.json` desatualizado** | Origem oficial (IBGE via `kelvins/municipios-brasileiros`); atualizar quando necessário. Mesma política da lib original. |
| **Tamanho do vendor** | Dompdf adiciona ~6MB; aceitável para SDK de NFS-e. Alternativas (wkhtmltopdf, Gotenberg) exigem binário externo ou serviço — pior trade-off. |
| **Variações de layout entre prefeituras** | Partindo da lib que já compara visualmente bem com PDFs oficiais da ADN. Ajustes CSS pontuais se testes visuais apontarem divergência. |

## Questão aberta

**XML fixture da Canela:** ideal termos o XML real correspondente ao PDF oficial da Canela (enviado durante o brainstorming) como fixture primária. Caso não esteja disponível, usamos o exemplo de `andrevabo/danfse-nacional/examples/` e seguimos. Não é bloqueante.

---

**Próximo passo:** plano de implementação detalhado em `docs/plans/2026-04-14-danfse-local-rendering-plan.md` (via skill `writing-plans`).
