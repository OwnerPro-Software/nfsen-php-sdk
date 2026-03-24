# Design: Reescrita do nfsen

**Data**: 2026-02-27
**Abordagem escolhida**: B — Pacote Laravel pragmático com Laravel Http client

## Contexto

O projeto atual tem problemas estruturais que dificultam manutenção e testes:
- SSL desligado hardcoded
- `getData()`/`postData()` duplicados
- `$httpver` nunca inicializado
- `Dps.php` com ~1.500 linhas sem separação de responsabilidades
- Zero testes automatizados
- Validação de schema desabilitada

O novo pacote mantém a dependência `nfephp-org/sped-common` (certificado + assinatura XMLDSig) e substitui o resto.

## Contexto de uso

Pacote standalone instalável via Composer, usado dentro de uma **aplicação Laravel multitenante**. O certificado digital varia por tenant e deve ser configurado dinamicamente em runtime.

## Estrutura de arquivos

```
src/
├── NfsenServiceProvider.php
├── Facades/
│   └── Nfsen.php
├── NfseClient.php
├── Enums/
│   ├── NfseAmbiente.php
│   └── MotivoCancelamento.php
├── Http/
│   └── NfseHttpClient.php
├── Certificates/
│   └── CertificateManager.php
├── Xml/
│   ├── DpsBuilder.php
│   └── Builders/
│       ├── PrestadorBuilder.php
│       ├── TomadorBuilder.php
│       ├── ServicoBuilder.php
│       ├── ValoresBuilder.php
│       └── EventoBuilder.php
├── Signing/
│   └── XmlSigner.php
├── Services/
│   └── PrefeituraResolver.php
├── Consulta/
│   └── ConsultaBuilder.php
├── Events/
│   ├── NfseRequested.php
│   ├── NfseEmitted.php
│   ├── NfseFailed.php
│   └── NfseRejected.php
├── DTOs/
│   ├── DpsData.php
│   └── NfseResponse.php
└── Exceptions/
    ├── NfseException.php
    ├── CertificateExpiredException.php
    └── HttpException.php
config/
    nfsen.php
storage/
    prefeituras.json
    schemes/
tests/
    fixtures/
        certs/
            fake.pfx
        responses/
            emitir_sucesso.json
            emitir_rejeicao.json
            consultar_nfse.json
            consultar_dps.json
            consultar_danfse.json
            consultar_eventos.json
            cancelar_sucesso.json
    Unit/
        Xml/
        Signing/
    Feature/
        NfseClientTest.php
```

## API pública

### Multitenancy — configuração dinâmica por tenant

```php
$client = NfseClient::for($pfxContent, $senha, $prefeitura);
$resposta = $client->emitir($dpsData);       // DpsData DTO
$resposta = $client->consultar()->nfse($chave);
$resposta = $client->consultar()->dps($chave);
$resposta = $client->consultar()->danfse($chave);
$resposta = $client->consultar()->eventos($chave);
```

`consultar()` retorna um `ConsultaBuilder` que recebe o `NfseClient` configurado. Cada chamada a `consultar()` cria uma nova instância:

```php
final class ConsultaBuilder
{
    public function __construct(private readonly NfseClient $client) {}

    public function nfse(string $chave): NfseResponse { ... }
    public function dps(string $chave): NfseResponse { ... }
    public function danfse(string $chave): NfseResponse { ... }
    public function eventos(string $chave): NfseResponse { ... }
}
```

### Via Facade

```php
Nfsen::for($pfxContent, $senha, '3501608')->emitir($dpsData);
Nfsen::for($pfxContent, $senha, '3501608')->cancelar($chave, $motivo, $descricao);
Nfsen::for($pfxContent, $senha, '3501608')->consultar()->nfse($chave);
Nfsen::for($pfxContent, $senha, '3501608')->consultar()->dps($chave);
Nfsen::for($pfxContent, $senha, '3501608')->consultar()->danfse($chave);
Nfsen::for($pfxContent, $senha, '3501608')->consultar()->eventos($chave);
```

### Configuração estática (config/nfsen.php)

```php
return [
    'ambiente'           => env('NFSE_AMBIENTE', NfseAmbiente::HOMOLOGACAO->value),
    'prefeitura'         => env('NFSE_PREFEITURA', null),
    'certificado' => [
        'path'  => env('NFSE_CERT_PATH'),
        'senha' => env('NFSE_CERT_SENHA'),
    ],
    'timeout'            => env('NFSE_TIMEOUT', 30),
    'signing_algorithm'  => env('NFSE_SIGNING_ALGORITHM', 'sha1'),
];
```

## Service Provider e Facade

`NfsenServiceProvider` registra `NfseClient` no container como **transient**, injetando a configuração estática (ambiente, timeout, algoritmo de assinatura):

```php
$this->app->bind(NfseClient::class, fn ($app) => new NfseClient(
    NfseAmbiente::from($app['config']['nfsen.ambiente']),
    $app['config']['nfsen.timeout'],
    $app['config']['nfsen.signing_algorithm'],
));
```

`NfseClient::for()` resolve uma instância do container e aplica a configuração do tenant (certificado + prefeitura):

```php
public static function for(string $pfxContent, string $senha, string $prefeitura): static
{
    // configure() é método privado — não faz parte da API pública
    return app(static::class)->configure($pfxContent, $senha, $prefeitura);
}
```

Separação: **container fornece config estática** (ambiente, timeout); **`for()` fornece config do tenant** (certificado, prefeitura). Cada chamada produz uma instância isolada.

## Fluxo interno do emitir()

```
DpsData (DTO tipado)
    → DpsBuilder::build()        — constrói XML via DOM, valida contra XSD
    → XmlSigner::sign()          — injeta <Signature> XMLDSig (sped-common)
    → gzencode() + base64        — comprime e codifica
    → NfseHttpClient::post()     — POST com mTLS via Laravel Http::
    → event(NfseEmitted)         — dispara evento Laravel
    → NfseResponse               — DTO readonly tipado com chave, xml, sucesso, erro
```

## Gerenciamento de certificado (multitenancy)

- `NfseClient` valida o código IBGE no `configure()`: lança `InvalidArgumentException` se não for 7 dígitos numéricos — falha rápida antes de qualquer requisição
- `CertificateManager` é stateless — instanciado por request
- `sped-common` (`\NFePHP\Common\Certificate`) constrói o objeto diretamente a partir da string do PFX, sem escrita em disco
- Para mTLS via Guzzle, os PEMs são gravados via `tmpfile()` — arquivo temporário anônimo sem nome previsível; o recurso é fechado explicitamente com `fclose()` no `finally` imediatamente após a request (necessário para não vazar file descriptors em workers long-lived como Laravel Octane)
- Nenhum arquivo com CNPJ/CPF em disco; nenhuma persistência entre requests
- SSL habilitado corretamente: `CURLOPT_SSL_VERIFYHOST=2`, `CURLOPT_SSL_VERIFYPEER=1`

## DTO de entrada

```php
readonly class DpsData {
    public function __construct(
        public stdClass $prestador,
        public stdClass $tomador,
        public stdClass $servico,
        public stdClass $valores,
        // demais grupos obrigatórios
    ) {}
}
```

Os grupos internos permanecem como `stdClass` por compatibilidade com o formato existente. O wrapper `DpsData` garante que todos os grupos obrigatórios estejam presentes antes de chegar ao `DpsBuilder`.

## Retorno tipado

```php
readonly class NfseResponse {
    public function __construct(
        public bool    $sucesso,
        public ?string $chave,
        public ?string $xml,    // XML da nota — verificar se a resposta da API já vem em texto plano ou requer gunzip+base64decode (investigar na implementação)
        public ?string $erro,
    ) {}
}
```

Exceções para erros de infraestrutura (certificado expirado, timeout, HTTP 5xx).
Erros de negócio da Receita (rejeições) retornam no `NfseResponse`.

## Assinatura XMLDSig

- Algoritmo padrão: **SHA1** (obrigatório pela especificação atual da Receita Federal / ABRASF)
- Configurável via `signing_algorithm` no config — permite migração sem mudança de código caso a Receita passe a aceitar SHA256
- **Investigar antes da implementação**: verificar a especificação ABRASF vigente para confirmar se SHA256 já é aceito em algum ambiente

## Eventos Laravel

O pacote dispara eventos para **todas as operações** (emitir, cancelar e consultas). A aplicação consumidora pode escutar via `Event::listen()`:

| Evento | Disparado em |
|---|---|
| `NfseRequested` | Antes do POST — todas as operações |
| `NfseEmitted` | Emissão com sucesso |
| `NfseFailed` | Erro de infraestrutura — todas as operações |
| `NfseRejected` | Rejeição de negócio pela Receita |

Cada evento carrega o campo `operacao` ('emitir', 'cancelar', 'consultar.nfse', etc.) e os metadados relevantes (chave, código de erro). A aplicação filtra o que quer ouvir.

## Ambiente

```php
enum NfseAmbiente: int {
    case PRODUCAO    = 1;
    case HOMOLOGACAO = 2;
}
```

Padrão: `NfseAmbiente::HOMOLOGACAO`.

## Cancelamento

```php
enum MotivoCancelamento: string {
    case ErroEmissao = 'e101101';
    case Outros      = 'e105102';
}
```

Assinatura: `cancelar(string $chave, MotivoCancelamento $motivo, string $descricao): NfseResponse`

## Prefeituras

`storage/prefeituras.json` mantém o mesmo formato do projeto atual.
`Services/PrefeituraResolver` carrega o arquivo e faz merge com as URLs padrão.
Identificação exclusivamente por **código IBGE** (suporte a nome legado removido).

> **Breaking change**: consumidores que identificavam prefeituras por nome devem migrar para código IBGE. Registrar no CHANGELOG na release.

## Testes

- **Unit**: `DpsBuilderTest` valida XML gerado contra XSD; `XmlSignerTest` verifica estrutura da assinatura
- **Feature**: `Http::fake()` do Laravel com fixtures JSON gravadas
- **Certificado de teste**: `.pfx` gerado com openssl, incluído no repositório em `tests/fixtures/certs/`

## Dependências

| Pacote | Mantém? | Motivo |
|---|---|---|
| `nfephp-org/sped-common` | Sim | Certificado + assinatura XMLDSig |
| `symfony/var-dumper` | Não | Só debug |
| `tecnickcom/tcpdf` | Não | Sem uso |

PHP mínimo: **8.2**

## Escopo desta versão

Implementar apenas o que o projeto atual já suporta:
- Emissão de DPS
- Consulta de NFSe por chave
- Consulta de DPS por chave
- Consulta de eventos
- Consulta de DANFSe
- Cancelamento (e101101 e e105102)

Grupos XML pendentes (intermediário, imóvel, dedução/redução, etc.) ficam para versões futuras.
