# Distribuição ADN Contribuinte

## Contexto

O NFS-e Padrão Nacional possui uma API separada no ADN (Ambiente de Dados Nacional) para distribuição de documentos fiscais aos contribuintes. Essa API permite buscar documentos em lote por NSU (Número Sequencial Único), resolvendo o problema de importação em massa — hoje o SDK só suporta consultas individuais por chave de acesso.

**Swagger salvo em:** `storage/schemes/ADN-Contribuinte-swagger.json`

**URLs base (mesma do ADN existente):**
- Produção: `https://adn.nfse.gov.br`
- Homologação: `https://adn.producaorestrita.nfse.gov.br`

## API pública

### Accessor no client

```php
$client->distribuicao()->documentos(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse;
$client->distribuicao()->documento(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse;
$client->distribuicao()->eventos(string $chave): DistribuicaoResponse;
```

- `documentos()` — retorna lote de documentos fiscais a partir do NSU (GET `/contribuintes/DFe/{NSU}?lote=true`)
- `documento()` — retorna documento fiscal unitário pelo NSU (GET `/contribuintes/DFe/{NSU}?lote=false`)
- `eventos()` — retorna todos os eventos vinculados a uma NFS-e (GET `/contribuintes/NFSe/{ChaveAcesso}/Eventos`)

### Parâmetro cnpjConsulta

- Quando `null`: extraído automaticamente do certificado digital (CNPJ recebido como string no construtor do distributor)
- Quando informado: usa o valor passado (caso de procurador/filiais)

### Query params

Montados no `NfseDistributor` antes de chamar o pipeline (sem alterar interfaces existentes):

```php
$url .= '?' . http_build_query(['cnpjConsulta' => $cnpj, 'lote' => 'true']);
```

## Interfaces (Driving Ports)

### DistributesNfse

```php
interface DistributesNfse
{
    public function documentos(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse;
    public function documento(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse;
    public function eventos(string $chave): DistribuicaoResponse;
}
```

### QueriesDistribuicao

```php
interface QueriesDistribuicao
{
    public function distribuicao(): DistributesNfse;
}
```

`NfsenClient` implementa `QueriesDistribuicao`.

## Response e DTOs

### DistribuicaoResponse

```php
final readonly class DistribuicaoResponse
{
    public function __construct(
        public bool $sucesso,
        public StatusDistribuicao $statusProcessamento,
        /** @var list<DocumentoFiscal> */
        public array $lote,
        /** @var list<ProcessingMessage> */
        public array $alertas,
        /** @var list<ProcessingMessage> */
        public array $erros,
        public ?int $tipoAmbiente,
        public ?string $versaoAplicativo,
        public ?string $dataHoraProcessamento,
    ) {}
}
```

- `sucesso` — derivado: `true` quando `statusProcessamento` é `DocumentosLocalizados`
- `tipoAmbiente` — `?int` para consistência com os responses existentes (`NfseResponse`, `EventsResponse`)
- `dataHoraProcessamento` — `?string` para consistência com os responses existentes

### DocumentoFiscal

```php
final readonly class DocumentoFiscal
{
    public function __construct(
        public ?int $nsu,
        public ?string $chaveAcesso,
        public TipoDocumentoFiscal $tipoDocumento,
        public ?TipoEventoDistribuicao $tipoEvento,
        public ?string $arquivoXml,
        public ?string $dataHoraGeracao,
    ) {}
}
```

- `arquivoXml` chega como GZip+base64 da API — o SDK descomprime automaticamente, o usuário recebe XML puro
- `dataHoraGeracao` — `?string` para consistência

### ProcessingMessage (alteração)

Adicionar campo `parametros` ao DTO existente:

```php
/** @var list<string> */
public array $parametros = [],
```

Adição retrocompatível — responses da Sefin que não retornam esse campo terão `[]`.

## Enums novos

### StatusDistribuicao

```php
enum StatusDistribuicao: string
{
    case Rejeicao = 'REJEICAO';
    case NenhumDocumentoLocalizado = 'NENHUM_DOCUMENTO_LOCALIZADO';
    case DocumentosLocalizados = 'DOCUMENTOS_LOCALIZADOS';
}
```

### TipoDocumentoFiscal

```php
enum TipoDocumentoFiscal: string
{
    case Nenhum = 'NENHUM';
    case Dps = 'DPS';
    case PedidoRegistroEvento = 'PEDIDO_REGISTRO_EVENTO';
    case Nfse = 'NFSE';
    case Evento = 'EVENTO';
    case Cnc = 'CNC';
}
```

### TipoEventoDistribuicao

```php
enum TipoEventoDistribuicao: string
{
    case Cancelamento = 'CANCELAMENTO';
    case SolicitacaoCancelamentoAnaliseFiscal = 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL';
    case CancelamentoPorSubstituicao = 'CANCELAMENTO_POR_SUBSTITUICAO';
    case CancelamentoDeferidoAnaliseFiscal = 'CANCELAMENTO_DEFERIDO_ANALISE_FISCAL';
    case CancelamentoIndeferidoAnaliseFiscal = 'CANCELAMENTO_INDEFERIDO_ANALISE_FISCAL';
    case ConfirmacaoPrestador = 'CONFIRMACAO_PRESTADOR';
    case RejeicaoPrestador = 'REJEICAO_PRESTADOR';
    case ConfirmacaoTomador = 'CONFIRMACAO_TOMADOR';
    case RejeicaoTomador = 'REJEICAO_TOMADOR';
    case ConfirmacaoIntermediario = 'CONFIRMACAO_INTERMEDIARIO';
    case RejeicaoIntermediario = 'REJEICAO_INTERMEDIARIO';
    case ConfirmacaoTacita = 'CONFIRMACAO_TACITA';
    case AnulacaoRejeicao = 'ANULACAO_REJEICAO';
    case CancelamentoPorOficio = 'CANCELAMENTO_POR_OFICIO';
    case BloqueioPorOficio = 'BLOQUEIO_POR_OFICIO';
    case DesbloqueioPorOficio = 'DESBLOQUEIO_POR_OFICIO';
    case InclusaoNfseDan = 'INCLUSAO_NFSE_DAN';
    case TributosNfseRecolhidos = 'TRIBUTOS_NFSE_RECOLHIDOS';
}
```

## Operation class

### NfseDistributor

Implementa `DistributesNfse`.

**Construtor:**

```php
public function __construct(
    private SendsHttpRequests $httpClient,
    private ResolvesOperations $resolver,
    private string $codigoIbge,
    private string $adnBaseUrl,
    private string $cnpjAutor,
) {}
```

- Usa `SendsHttpRequests` (não `ExecutesNfseRequests`) porque a API de distribuição tem formato de resposta diferente (PascalCase, enums string) e não requer dispatch de eventos
- `cnpjAutor` — CNPJ extraído do certificado por quem instancia (ServiceProvider ou `forStandalone()`)
- Usa `adnBaseUrl` como base (mesmo padrão do `danfse`)
- Usa trait `ValidatesChaveAcesso` para `eventos()`
- Monta query params na URL antes de chamar `get()` do HTTP client
- Descomprime `ArquivoXml` de cada item do lote (GZip+base64 → XML string)
- Sem dispatch de eventos (consistente com `NfseConsulter`)

**Tratamento de erros HTTP:**

- 200 → parsear normalmente (pode ser `DOCUMENTOS_LOCALIZADOS` ou `NENHUM_DOCUMENTO_LOCALIZADO`)
- 400/404 → API retorna o mesmo JSON com `REJEICAO` + erros, parsear da mesma forma
- 5xx/timeout → capturar `HttpException`, montar `DistribuicaoResponse` com `sucesso: false`, `statusProcessamento: Rejeicao`, e o erro no array de `erros`

## PrefeituraResolver

Novas operations default:

```php
'distribute_documents' => 'contribuintes/DFe/{NSU}',
'distribute_events'    => 'contribuintes/NFSe/{ChaveAcesso}/Eventos',
```

Municípios podem sobrescrever via `prefeituras.json`.

## Wiring

### NfsenClient

- Implementa `QueriesDistribuicao`
- Novo parâmetro no construtor: `DistributesNfse $distributor`
- Novo método `distribuicao(): DistributesNfse` retorna `$this->distributor`

### NfsenServiceProvider

Instancia `NfseDistributor` com as dependências já disponíveis no binding:

```php
$cnpj = (new CertificateManager($certificate))->extractAuthorIdentity();
new NfseDistributor($responsePipeline, $resolver, $codigoIbge, $adnUrl, $cnpj);
```

### NfsenClient::forStandalone()

Mesma lógica — extrai CNPJ do certificado e passa para o `NfseDistributor`.

## Arquivos alterados

```
src/NfsenClient.php                  — implementa QueriesDistribuicao, novo param construtor
src/NfsenServiceProvider.php         — instancia NfseDistributor
src/Adapters/PrefeituraResolver.php  — 2 novas operations default
src/Responses/ProcessingMessage.php  — adiciona campo parametros
README.md                            — documentação da nova feature
CHANGELOG.md                         — entrada da nova feature
```

## Novos arquivos

```
src/Contracts/Driving/DistributesNfse.php
src/Contracts/Driving/QueriesDistribuicao.php
src/Enums/StatusDistribuicao.php
src/Enums/TipoDocumentoFiscal.php
src/Enums/TipoEventoDistribuicao.php
src/Operations/NfseDistributor.php
src/Responses/DistribuicaoResponse.php
src/Responses/DocumentoFiscal.php

tests/Unit/Enums/StatusDistribuicaoTest.php
tests/Unit/Enums/TipoDocumentoFiscalTest.php
tests/Unit/Enums/TipoEventoDistribuicaoTest.php
tests/Unit/Operations/NfseDistributorTest.php
tests/Unit/Responses/DistribuicaoResponseTest.php
tests/Unit/Responses/DocumentoFiscalTest.php
tests/Feature/NfsenClientDistribuicaoTest.php
```
