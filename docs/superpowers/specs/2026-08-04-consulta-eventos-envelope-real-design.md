# Consulta de eventos — ler o envelope que a SEFIN devolve

## Contexto

`consultar()->eventos()` exige `eventoXmlGZipB64` no topo da resposta. A rota
`GET /nfse/{chave}/eventos/{tipoEvento}/{nSequencial}` do `SefinNacional_1.6.0` não
devolve esse campo: devolve uma lista `eventos[]`, e o XML vem em `eventos[].arquivoXml`.

Consequência: **nenhuma consulta de evento existente pode ter sucesso.** Evento ausente
devolve 404 e é tratado corretamente; evento presente devolve 200, não encontra o campo
exigido em `NfseResponsePipeline::executeRaw` e vira `IndeterminateResultException`.
Só o caminho da ausência funciona.

O defeito não foi pego porque `SefinNacional-swagger.json` declara
`EventosPostResponseSucesso` — o schema do POST — como resposta 200 do GET, e os fixtures
dos testes foram construídos a partir dele. Suíte verde contra um contrato que o servidor
não cumpre.

Origem: incidente em produção do time Pulsar (nota central id 215 / número 122, IBGE
4304408), handoff de 2026-08-04. Nota cancelada por análise fiscal com evento 105104
registrado no SEFIN desde 2026-08-03 permanece autorizada no banco do consumidor.

## Evidência

Varredura em produção, 4 tipos de evento × 3 sequenciais, feita com o próprio
`NfseHttpClient::getResponse()`.

**Evento presente — 105104 seq 1 — HTTP 200:**

```json
{
  "dataHoraProcessamento": "2026-08-04T07:13:04.3397841-03:00",
  "tipoAmbiente": 1,
  "versaoAplicativo": "SefinNacional_1.6.0",
  "eventos": [
    {
      "chaveAcesso": "430440…977201",
      "tipoEvento": 105104,
      "numeroPedidoRegistroEvento": 1,
      "dataHoraRecebimento": "2026-08-03T07:31:39.737",
      "arquivoXml": "SDRzSUFBQUFBQUFFQUsxWDZaS3EycEorbFlyYVA0MWRESUxDQ1hmZFdJdEpVRkFHVWZqVHdTU2dERElQZisr…"
    }
  ]
}
```

O 101103 seq 1 traz a mesma estrutura — é a forma da rota, não peculiaridade de um evento.

**Evento ausente — HTTP 404:** corpo só com `dataHoraProcessamento`, `tipoAmbiente` e
`versaoAplicativo`, sem `erros`. `executeRaw` devolve a resposta e `eventos()` monta
`EVENT_NOT_FOUND`. **Esse caminho está correto e não muda.**

**Codificação de `arquivoXml` — duas camadas de base64 sobre o gzip:**

```
base64_decode(arquivoXml)  →  "H4sIAAAAAAAEAK1X6ZKq2pJ+lYraP41d…"   (texto ASCII)
base64_decode(disso)       →  1f 8b 08 …                            (magic gzip)
```

Uma passada de `base64_decode` produz exatamente o que `GzipCompressor::decompressB64()`
já espera receber.

## Decisão sobre tolerância a duas formas

`storage/prefeituras.json` traz duas prefeituras com base URL e paths próprios
(`nfse.americana.sp.gov.br`, `nfsesantanadeparnaiba.simplissweb.com.br`). Implementações
não-nacionais existem e podem cumprir o swagger declarado. A leitura aceita as duas formas
em vez de trocar uma pela outra: `eventos[]` primeiro, `eventoXmlGZipB64` de topo como
alternativa.

## API pública

### `EventoConsultado` — novo DTO

```php
namespace OwnerPro\Nfsen\Responses;

final readonly class EventoConsultado
{
    public function __construct(
        public ?string $chaveAcesso,
        public ?TipoEvento $tipoEvento,
        public ?int $numeroPedidoRegistroEvento,
        public ?string $dataHoraRecebimento,
        public ?string $xml,
        public ?string $parseError = null,
    ) {}
}
```

- `xml` já descomprimido.
- Item que não pôde ser interpretado por completo **não** derruba a resposta: os campos
  afetados vêm `null` e `parseError` descreve o que faltou. Mesmo contrato de
  `DocumentoFiscal`.
- `tipoEvento` fora do enum `TipoEvento` vira `null` com `parseError`, nunca `TypeError`.

### `EventsResponse`

Ganha `public array $eventos = []` (`list<EventoConsultado>`) **no fim** da lista de
parâmetros — nenhum argumento existente se desloca.

`xml` continua sendo o XML do primeiro evento. `sucesso` permanece `true` quando o
servidor devolveu eventos, ainda que um item traga `parseError` — o precedente é
`ParsesEventResponse`, que devolve `sucesso: true` com `xml: null` e um alerta quando o
recibo chega ilegível.

### `ConsultsNfse::eventos` — docblock

O trecho atual afirma o oposto do que ocorre:

> Um 2xx com JSON válido porém sem `eventoXmlGZipB64` não ocorre em operação normal e
> lança `IndeterminateResultException` — nunca vira sucesso com `xml: null`.

Reescrever descrevendo `eventos[]`, o preenchimento de `xml`, e a tolerância às duas
formas. O parágrafo sobre `EVENT_NOT_FOUND` fica como está.

## Implementação

### `NfseConsulter::eventos()`

```php
$response = $this->client->executeRaw($url, 'eventos', 'eventoXmlGZipB64');
```

Ordem de leitura no ramo de sucesso:

1. `eventos[]` presente e não vazio → mapear cada item para `EventoConsultado`;
   `xml` recebe `$eventos[0]->xml`.
2. Senão, `eventoXmlGZipB64` de topo → um único `EventoConsultado` com apenas `xml`
   preenchido, os demais campos `null`.

404 e rejeição estruturada seguem inalterados.

### `GzipCompressor::decompressB64()`

Descasca a camada extra condicionada ao magic gzip, com teto de duas camadas:

```php
$decoded = base64_decode($gzipB64, true);

if (! str_starts_with($decoded, "\x1f\x8b")) {
    $peeled = base64_decode($decoded, true);

    if ($peeled !== false && str_starts_with($peeled, "\x1f\x8b")) {
        $decoded = $peeled;
    }
}
```

Um lugar só, determinístico. Cobre também `DocumentoFiscal::$arquivoXml`, caso o ADN
aplique a mesma camada — hipótese levantada pelo consumidor e ainda não medida.

### `ExecutesNfseRequests::executeRaw` — campo obrigatório com alternativas

```php
public function executeRaw(string $url, string ...$requiredFields): HttpResponse;
```

`executeRaw($url, 'chaveAcesso')` em `dps()` não muda. A checagem de presença passa a
aceitar **string não-vazia ou array não-vazio** — hoje é só `is_string`, e `eventos` é
lista.

Mensagem da exceção, forma única para um ou mais campos:

> Resultado indeterminado: o servidor respondeu HTTP %d, mas a resposta não traz nenhum
> dos campos exigidos pela operação (%s); não pôde ser interpretada.

### `IndeterminateResultException` — preservar a evidência

Os parâmetros novos entram **depois** de `$previous`, para não deslocar posicionalmente
quem já constrói a exceção:

```php
public function __construct(
    string $message,
    public readonly ?string $phase = null,
    ?Throwable $previous = null,
    public readonly ?int $statusCode = null,
    public readonly ?string $body = null,
    public readonly ?array $raw = null,
) {}
```

- `body` com teto documentado de 8 KiB, em constante.
- `raw` guarda o array decodificado inteiro, sem teto — a estrutura completa fica sempre
  disponível; `body` serve o caso do `fromUnreadableResponse`, em que não há JSON.

Preenchimento por fábrica, limitado ao que cada call site tem em escopo:

| Fábrica | `statusCode` | `body` | `raw` |
|---|---|---|---|
| `fromUnreadableResponse` | ✅ | ✅ | — (`json_decode` falhou, é a causa) |
| `fromServerError` | ✅ | ✅ | — |
| `fromMissingResponseField` | ✅ | ✅ | ✅ |
| `fromMissingQueryField` | — | — | ✅ |
| `fromMissingEventReceipt` | — | — | ✅ |

As duas últimas não têm status nem corpo cru porque `SendsHttpRequests::get()` e
`post()` devolvem `array`. Mudar as portas driven para devolver `HttpResponse` foi
avaliado e ficou fora de escopo: alcança `Contracts/Driven`, `NfseHttpClient`, os dois
pipelines, quatro `Operations` e todos os fakes de teste, para um ganho que o `raw` já
cobre nesses dois pontos.

### `NfseFailed` — alcançar a evidência sem depender do `catch`

```php
public function __construct(
    public string $operacao,
    public string $mensagem,
    public ?Throwable $throwable = null,
) {}
```

`DispatchesEvents::withFailureEvent` passa o throwable.

## Testes

O fixture é montado com a codificação real (base64 duplo sobre gzip), não com o formato
do swagger — foi exatamente daí que veio o ponto cego.

- 200 com `eventos[]` → `sucesso: true`, `xml` preenchido, `eventos[0]` com todos os campos.
- 200 com `eventoXmlGZipB64` de topo → `sucesso: true`, um `EventoConsultado` só com `xml`.
- `arquivoXml` corrompido → `parseError` preenchido, `xml: null`, resposta de pé.
- `tipoEvento` fora do enum → `tipoEvento: null` com `parseError`.
- `eventos: []` → `IndeterminateResultException` (lista vazia não é resultado).
- 404 → `EVENT_NOT_FOUND` intacto.
- `GzipCompressor` com uma camada e com duas → ambos descomprimem.
- Cada fábrica de `IndeterminateResultException` → propriedades preenchidas conforme a
  tabela acima.
- `NfseFailed` carregando o throwable.

## Fora de escopo

**O POST de evento (`ParsesEventResponse`).** A varredura cobriu apenas o GET, e a
rejeição E0822 que o consumidor capturou por essa rota indica que o POST devolve
`eventoXmlGZipB64` mesmo. Mexer sem medição trocaria um defeito conhecido por um suposto.

**A camada extra de base64 no ADN.** O tratamento em `GzipCompressor` cobre o caso se ele
existir, mas nenhuma medição foi feita contra `DistribuicaoNSU.ArquivoXml`.
