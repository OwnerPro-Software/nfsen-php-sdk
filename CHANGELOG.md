# Changelog

All notable changes to this project will be documented in this file.

## [2.2.1] - 2026-04-08

### Fixed
- `ProcessingMessage::fromArray()` agora converte valores não-string (arrays/objetos retornados pela API) para JSON em vez de falhar com type error. Corrige crash quando campo `Mensagem` vem como objeto Enum do Swagger do ADN.

## [2.2.0] - 2026-04-08

### Added
- `HttpResponse` DTO com `statusCode`, `json` e `body` para respostas HTTP completas.
- Interface `SendsRawHttpRequests` com método `getResponse()` para acesso a respostas HTTP sem perda de informação.
- `DistribuicaoResponse::fromHttpResponse()` — novo factory method que preserva HTTP status code e body raw em cenários de erro.
- `NfseHttpClient` agora implementa `SendsRawHttpRequests` além de `SendsHttpRequests`.

### Changed
- `NfseDistributor` usa `SendsRawHttpRequests::getResponse()` em vez de `SendsHttpRequests::get()`, preservando HTTP status code e body raw em todas as respostas de erro.

### Fixed
- Respostas HTTP 4xx com body vazio (ex: 429 rate limiting), redirects (3xx) e respostas 2xx com corpo vazio agora são diagnosticáveis — o `DistribuicaoResponse` inclui o status code no `codigo` do erro e o body raw no `complemento`.
- `ProcessingMessage::fromArray()` agora converte valores não-string (arrays/objetos retornados pela API) para JSON em vez de falhar com type error. Corrige crash quando campo `Mensagem` vem como objeto Enum do Swagger do ADN.

## [2.1.1] - 2026-04-08

### Fixed
- `DistribuicaoResponse::fromApiResult()` agora inclui o JSON completo da API no campo `complemento` e as chaves presentes no `descricao` quando `StatusProcessamento` é ausente ou inválido, facilitando o diagnóstico de respostas inesperadas.

## [2.1.0] - 2026-04-08

### Added
- Distribuição de documentos fiscais via ADN Contribuinte (`$client->distribuicao()`)
  - `documentos(int $nsu)` — consulta em lote por NSU
  - `documento(int $nsu)` — consulta unitária por NSU
  - `eventos(string $chave)` — consulta todos os eventos de uma NFS-e
- Novos DTOs: `DistribuicaoResponse`, `DocumentoFiscal`
- Novos enums: `StatusDistribuicao`, `TipoDocumentoFiscal`, `TipoEventoDistribuicao`
- Campo `parametros` adicionado ao `ProcessingMessage`

## [2.0.0] - 2026-04-03

### Added
- Suporte a Laravel 13 (`illuminate/http`, `illuminate/support`, `illuminate/contracts` `^13.0`)
- Suporte a `orchestra/testbench` `^11.0` (testbench v11 = Laravel 13)
- Typed constants (`const array`, `const string`) via PHP 8.3
- Atributo `#[Override]` em métodos sobrescritos
- CI com matrix PHP 8.3/8.4 × Laravel 11/12/13

### Changed
- Requisito mínimo de PHP alterado de **8.2** para **8.3**
- Pest 3 → Pest 4, pest-plugin-laravel 3 → 4.1, pest-plugin-type-coverage 3 → 4

### Breaking Changes
- **PHP 8.2 não é mais suportado** — requisito mínimo agora é PHP 8.3

## [1.0.1] - 2026-03-26

### Security
- Validação HTTPS obrigatória nas URLs de prefeitura em ambiente de produção
- Cross-check de identidade: CNPJ do certificado digital é verificado contra o CNPJ do prestador na DPS antes do envio
- Remoção de exposição de chave privada em mensagens de erro do `CertificateManager`

## [1.0.0] - 2026-03-24

Primeira versão estável sob o namespace `OwnerPro\Nfsen` e pacote `ownerpro/nfsen-php-sdk`.

Reescrita completa do projeto original, com arquitetura hexagonal, 100% de cobertura
de testes, tipos e mutações, e suporte a PHP 8.2+ com Laravel 11/12.

### Added
- `NfsenClient::for()` — instância configurada por tenant via container Laravel
- `NfsenClient::forStandalone()` — instância standalone sem dependência do container
- Emissão de NFSe (`emitir`) e emissão por decisão judicial (`emitirDecisaoJudicial`)
- Cancelamento de NFSe (`cancelar`)
- Substituição de NFSe (`substituir`) — emissão da substituta + cancelamento da original em uma única requisição
- Consultas fluentes: `consultar()->nfse/dps/danfse/eventos($chave)`
- Verificação de DPS: `consultar()->verificarDps($idDps)`
- DTOs tipados para toda a estrutura DPS conforme XSD v1.01
- Responses tipados: `NfseResponse`, `DanfseResponse`, `EventsResponse`, `ProcessingMessage`
- Eventos Laravel: `NfseEmitted`, `NfseCancelled`, `NfseSubstituted`, `NfseQueried`, `NfseRequested`, `NfseRejected`, `NfseFailed`
- Assinatura digital XML com certificado A1 (PFX/P12)
- Validação XSD dos documentos antes do envio
- mTLS via `tmpfile()` — sem escrita nomeada em disco, sem CNPJ no path
- SSL habilitado corretamente (`verify: true`)
- Facade `Nfsen` para uso simplificado com Laravel
- Override de ambiente em runtime via `NfsenClient::for(..., ambiente: NfseAmbiente::PRODUCAO)`
- Identificação de prefeituras exclusivamente por código IBGE (7 dígitos)
- Suporte a PHP 8.2, 8.3 e 8.4
- Suporte a Laravel 11 e 12

### Arquitetura
- Arquitetura hexagonal com ports & adapters
- Contratos (interfaces) separados em Driving (entrada) e Driven (infraestrutura)
- Pipeline de requisição/resposta com concerns reutilizáveis
- DTOs imutáveis com validação exclusiva de campos mutuamente exclusivos
- Enums tipados seguindo nomenclatura do XSD oficial
- Testes de arquitetura (arch tests) garantindo fronteiras hexagonais
- 100% de cobertura de testes, tipos e mutações (1129 mutações)
- CI com matrix PHP 8.2/8.3/8.4 × Laravel 11/12
- Quality gates: PHPStan, Psalm (taint analysis), Rector, Pint

### Breaking Changes (em relação ao projeto original)
- Namespace: `Hadder\NfseNacional` → `OwnerPro\Nfsen`
- Pacote: `ownerpro/nfsen-php-sdk` (antes não publicado no Packagist)
- Requisito mínimo de PHP alterado de 8.1 para **8.2**
- API pública completamente nova: `NfsenClient::for($pfx, $senha, $ibge)->emitir($dpsData)`
- Identificação de prefeituras exclusivamente por código IBGE; suporte a nome legado removido
- Removido `Helpers.php` com `now()` global
- Removidas dependências `symfony/var-dumper` e `tecnickcom/tcpdf`

### Créditos
Este pacote teve como base o trabalho do projeto original
[nfse-nacional](https://github.com/Rainzart/nfse-nacional) de **Fernando Friedrich**,
construído sobre o [NFePHP](https://github.com/nfephp-org) de **Roberto L. Machado**.
Agradecimento a todos os contribuidores do projeto original.
