# Design: Reescrita do nfse-nacional

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
├── NfseNacionalServiceProvider.php
├── Facades/
│   └── NfseNacional.php
├── NfseClient.php
├── Enums/
│   └── NfseAmbiente.php
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
├── DTOs/
│   ├── DpsData.php
│   └── NfseResponse.php
└── Exceptions/
    ├── NfseException.php
    ├── CertificateExpiredException.php
    └── HttpException.php
config/
    nfse-nacional.php
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
$resposta = $client->emitir($dpsData); // DpsData DTO
```

### Via Facade

```php
NfseNacional::for($pfxContent, $senha, '3501608')->emitir($dpsData);
NfseNacional::for($pfxContent, $senha, '3501608')->consultarNfse($chave);
NfseNacional::for($pfxContent, $senha, '3501608')->consultarDps($chave);
NfseNacional::for($pfxContent, $senha, '3501608')->consultarDanfse($chave);
NfseNacional::for($pfxContent, $senha, '3501608')->cancelar($chave, $motivo, $descricao);
```

### Configuração estática (config/nfse-nacional.php)

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

- `CertificateManager` é stateless — instanciado por request
- `sped-common` (`\NFePHP\Common\Certificate`) constrói o objeto diretamente a partir da string do PFX, sem escrita em disco
- Para mTLS via Guzzle, os PEMs são gravados via `tmpfile()` — arquivo temporário anônimo sem nome previsível, descartado no `finally` imediatamente após a request
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
        public ?string $xml,    // XML da nota decodificado (gzip+base64)
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

O pacote dispara eventos que a aplicação consumidora pode escutar via `Event::listen()`:

| Evento | Disparado em |
|---|---|
| `NfseRequested` | Antes do POST |
| `NfseEmitted` | Emissão com sucesso |
| `NfseFailed` | Erro de infraestrutura (exceção) |
| `NfseRejected` | Rejeição de negócio pela Receita |

Cada evento carrega a operação e metadados relevantes (chave, código de erro). A aplicação decide se loga, monitora ou ignora.

## Ambiente

```php
enum NfseAmbiente: int {
    case PRODUCAO    = 1;
    case HOMOLOGACAO = 2;
}
```

Padrão: `NfseAmbiente::HOMOLOGACAO`.

## Prefeituras

`storage/prefeituras.json` mantém o mesmo formato do projeto atual.
`Services/PrefeituraResolver` carrega o arquivo e faz merge com as URLs padrão.
Identificação exclusivamente por **código IBGE** (suporte a nome legado removido).

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
