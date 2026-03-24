# Hexagonal Architecture (Ports & Adapters) — Design

**Data:** 2026-03-03
**Motivação:** Architectural purity — aplicar Ports & Adapters para que o core da biblioteca nunca dependa de classes concretas de infraestrutura.
**Abordagem:** B — certificate é detalhe interno dos adapters; o core enxerga apenas `SignsXml` + `ExtractsAuthorIdentity`.

---

## 1. Estrutura de Diretórios

```
src/Contracts/Ports/
├── Driving/
│   ├── EmitsNfse.php
│   ├── CancelsNfse.php
│   ├── SubstitutesNfse.php
│   ├── QueriesNfse.php
│   └── ExecutesNfseRequests.php
└── Driven/
    ├── SendsHttpRequests.php
    ├── SignsXml.php
    ├── ExtractsAuthorIdentity.php
    └── ResolvesPrefeituras.php
```

Namespace: `OwnerPro\Nfsen\Contracts\Ports\Driving\*` e `OwnerPro\Nfsen\Contracts\Ports\Driven\*`.

O diretório `src/Contracts/` atual é removido após a migração.

---

## 2. Driven Ports (outbound)

### `SignsXml`

```php
interface SignsXml
{
    public function sign(string $xml, string $tagname, string $rootname): string;
}
```

### `ExtractsAuthorIdentity`

```php
interface ExtractsAuthorIdentity
{
    /** @return array{cnpj: ?string, cpf: ?string} */
    public function extract(): array;
}
```

### `SendsHttpRequests`

```php
interface SendsHttpRequests
{
    /** @param array<string, mixed> $payload
     *  @return array<string, mixed> */
    public function post(string $url, array $payload): array;

    /** @return array<string, mixed> */
    public function get(string $url): array;

    public function head(string $url): int;
}
```

### `ResolvesPrefeituras`

```php
interface ResolvesPrefeituras
{
    public function resolveSeFinUrl(string $codigoIbge, NfseAmbiente $ambiente): string;
    public function resolveAdnUrl(string $codigoIbge, NfseAmbiente $ambiente): string;
    /** @param array<string, int|string> $params */
    public function resolveOperation(string $codigoIbge, string $operacao, array $params = []): string;
}
```

---

## 3. Driving Ports (inbound)

Interfaces existentes migram de `Contracts/` para `Contracts/Ports/Driving/` sem alteração de assinatura:

- `EmitsNfse`
- `CancelsNfse`
- `SubstitutesNfse`
- `QueriesNfse`
- `ExecutesNfseRequests`

---

## 4. Adapters

Cada classe concreta de infraestrutura ganha `implements` do port correspondente. Nenhuma muda de diretório.

| Adapter | Port | Alteração |
|---------|------|-----------|
| `NfseHttpClient` | `SendsHttpRequests` | Adiciona `implements` |
| `XmlSigner` | `SignsXml` | Adiciona `implements` |
| `CertificateManager` | `ExtractsAuthorIdentity` | Adiciona `implements` + método `extract()` |
| `PrefeituraResolver` | `ResolvesPrefeituras` | Adiciona `implements` |

`CertificateManager::extract()` move a lógica de `NfseRequestPipeline::extractAuthorIdentity()` para dentro do adapter. `getCertificate()` permanece — é usado por `NfseHttpClient` e `XmlSigner` (infraestrutura, não core).

---

## 5. Refatoração do Core

### `NfseRequestPipeline`

Antes:
```php
__construct(
    NfseAmbiente $ambiente,
    string $signingAlgorithm,
    PrefeituraResolver $prefeituraResolver,
    GzipCompressor $gzipCompressor,
    CertificateManager $certManager,
    string $prefeitura,
    NfseHttpClient $httpClient,
)
```

Depois:
```php
__construct(
    NfseAmbiente $ambiente,
    ResolvesPrefeituras $prefeituraResolver,
    GzipCompressor $gzipCompressor,
    SignsXml $signer,
    ExtractsAuthorIdentity $authorIdentity,
    string $prefeitura,
    SendsHttpRequests $httpClient,
)
```

- `signingAlgorithm` desaparece (detalhe interno do adapter `XmlSigner`)
- `CertificateManager` desaparece (dividido em `SignsXml` + `ExtractsAuthorIdentity`)
- `signCompressSend()` usa `$this->signer->sign()` diretamente (sem `new XmlSigner()`)
- `extractAuthorIdentity()` delega para `$this->authorIdentity->extract()`

### `NfseQueryExecutor`

```php
// antes
__construct(private NfseHttpClient $httpClient)
// depois
__construct(private SendsHttpRequests $httpClient)
```

### `ConsultaBuilder`

```php
// antes
__construct(..., private PrefeituraResolver $resolver, ...)
// depois
__construct(..., private ResolvesPrefeituras $resolver, ...)
```

### `NfseClient`

```php
// antes
__construct(..., private PrefeituraResolver $prefeituraResolver, ...)
// depois
__construct(..., private ResolvesPrefeituras $prefeituraResolver, ...)
```

---

## 6. Composition Root

`NfseClient::forStandalone()` é o composition root. É o único lugar que instancia adapters concretos:

```php
$certManager        = new CertificateManager($pfxContent, $senha);
$prefeituraResolver = new PrefeituraResolver($jsonPath);
$httpClient         = new NfseHttpClient($certManager->getCertificate(), ...);
$signer             = new XmlSigner($certManager->getCertificate(), $signingAlgorithm);

$pipeline = new NfseRequestPipeline(
    ambiente: $ambiente,
    prefeituraResolver: $prefeituraResolver,
    gzipCompressor: new GzipCompressor,
    signer: $signer,
    authorIdentity: $certManager,
    prefeitura: $prefeitura,
    httpClient: $httpClient,
);
```

Assinatura pública de `forStandalone()` permanece idêntica. Sem breaking change para consumidores.

---

## 7. O Que NÃO Muda

- DTOs, Enums, Events, Exceptions — dados puros
- XML Builders — dependem de `XsdValidator` (utilitário puro)
- `GzipCompressor` — função pura, sem dependência externa
- Facade — delega para `NfseClient`
- Traits (`DispatchesEvents`, etc.) — concerns internas do core

---

## 8. Resumo de Operações

| Ação | Arquivos |
|------|----------|
| **Criar** (4 interfaces) | `Contracts/Ports/Driven/SignsXml.php`, `SendsHttpRequests.php`, `ExtractsAuthorIdentity.php`, `ResolvesPrefeituras.php` |
| **Mover** (5 interfaces) | `Contracts/*.php` → `Contracts/Ports/Driving/*.php` |
| **Deletar** | Arquivos antigos em `src/Contracts/` |
| **Editar adapters** (4) | Adicionar `implements` em `NfseHttpClient`, `XmlSigner`, `CertificateManager`, `PrefeituraResolver` |
| **Editar core** (4) | Trocar type hints em `NfseRequestPipeline`, `NfseQueryExecutor`, `ConsultaBuilder`, `NfseClient` |
| **Editar testes** | Atualizar imports e tipos de mock |