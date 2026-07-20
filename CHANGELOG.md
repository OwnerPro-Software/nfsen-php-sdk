# Changelog

All notable changes to this project will be documented in this file.

## [2.5.0] - 2026-07-20

Suporte a reconciliação de resultado indeterminado: permite descobrir o estado real de uma DPS após falha de comunicação antes de qualquer retry, eliminando o risco de dupla emissão.

### Added

- `Support\DpsId::generate()` — builder público do identificador de 45 posições da DPS (`TSIdDPS`), fonte única da regra fiscal de formação do ID. `Xml\DpsBuilder` passou a delegar para ele. Valida o retorno contra o padrão `DPS[0-9]{42}` e lança `InvalidDpsArgument` em entrada inválida — inclusive quando CNPJ e CPF são ambos `null` (inscrição zerada silenciosa seria um ID fiscalmente inválido; o caso legítimo de prestador estrangeiro com NIF/cNaoNIF requer `allowEmptyInscricao: true`).
- `Exceptions\IndeterminateResultException` — lançada quando o SDK não consegue obter uma resposta completa e legível em qualquer caminho HTTP (`post`, `get`, `getBytes`, `getResponse`, `head`). Cobre três situações: falha antes de qualquer resposta (timeout, DNS, conexão recusada, TLS), falha no meio da transferência (conexão resetada, corpo truncado — cURL 18/56/92) e resposta 2xx com corpo ilegível (JSON inválido ou vazio). Propriedade `phase` (`connect`|`dns`|`read`|`tls`|`transfer`|`body`|`null`) indica a fase da falha quando detectável. Contrato: capturá-la significa que a SEFIN pode ou não ter processado a requisição — **nunca faça retry cego**; reconcilie via `DpsId::generate()` + `consultar()->dps($id)` antes; qualquer outra exceção ou resposta é definitiva.
- `NfseResponse::DPS_NOT_FOUND` — código de erro dedicado retornado em `erros[0]->codigo` quando `consultar()->dps($id)` recebe HTTP 404 da SEFIN (DPS comprovadamente inexistente, distinto de erro transitório). Erros originais da SEFIN, se presentes, são preservados a partir de `erros[1]`.
- `ExecutesNfseRequests::executeRaw()` — retorna a resposta HTTP crua (status + JSON + corpo) para consultas que precisam distinguir status; lança `HttpException` para status inesperado (≠ 200/201/404) sem corpo de erro estruturado.
- Dependência explícita de `guzzlehttp/guzzle` (já era transitiva via `illuminate/http`) — o SDK agora captura `TransferException` do Guzzle diretamente para cobrir versões do Laravel que não a envelopam.

### Changed

- **`consultar()->verificarDps()`** retorna `false` **apenas em HTTP 404**. Qualquer outro status ≠ 200 (401, 403, 429, redirect…) agora lança `HttpException` — antes retornava `false`, o que podia ser lido como "DPS não existe" e induzir dupla emissão. O throw acontece dentro do pipeline de eventos, disparando `NfseFailed` (paridade com 5xx). Falha de transporte lança `IndeterminateResultException`.
- **Falhas de conexão** que antes vazavam como `Illuminate\Http\Client\ConnectionException` crua (ou `RequestException`/`TransferException`, conforme a versão do Laravel) agora chegam ao integrador como `IndeterminateResultException` (exceção original em `getPrevious()`). Quem capturava `ConnectionException` diretamente deve migrar o catch.
- **Respostas 2xx com corpo ilegível** (JSON inválido, vazio ou escalar) em `post`/`get`/`getResponse` agora lançam `IndeterminateResultException` — antes viravam array vazio e podiam propagar como falso sucesso (ex.: `consultar()->dps()` reconciliando contra um 200 de load balancer com página HTML retornava `sucesso: true` com `chave: null`). Em `distribuicao()`, o caso 200 com corpo vazio que antes retornava `EMPTY_RESPONSE` agora também lança (corpo `{}` válido continua retornando `EMPTY_RESPONSE`).
- **Respostas de erro sem corpo estruturado** em `post`/`get` agora lançam `HttpException` para qualquer status não-2xx (antes apenas 5xx; um redirect ou 4xx com corpo vazio retornava array vazio silencioso). Redirects continuam não sendo seguidos.
- `consultar()->dps()` com HTTP 5xx/redirect sem corpo de erro estruturado agora lança `HttpException` de forma consistente (antes um redirect com corpo vazio era interpretado como sucesso).

## [2.4.0] - 2026-05-11

### Added

- Campos `mensagemErro` e `correcao` no evento `NfseRejected` para facilitar debug e logs operacionais sem precisar inspecionar o payload da resposta. `mensagemErro` é preenchido com `ProcessingMessage::descricao` (fallback `mensagem`); `correcao` espelha `ProcessingMessage::complemento`. Ambos são `?string` com default `null` — retrocompatível com listeners que só leem `operacao`/`codigoErro`.

## [2.3.1] - 2026-04-16

### Added

- Campo `codigoNbs` em `DanfseServico` — código NBS (Nomenclatura Brasileira de Serviços) extraído de `cServ/cNBS`.
- Renderização de **NBS:** (label em negrito) no bloco INFORMAÇÕES COMPLEMENTARES do DANFSE quando `cNBS` presente na NFS-e.
- Resolução de município via tabela IBGE para Local da Prestação (`cLocPrestacao`) e Município de Incidência (`cLocIncid`), produzindo "Cidade - UF" em paridade com o portal nacional.

### Fixed

- `DanfseDataBuilder`: crash ao processar XMLs sem blocos opcionais (`tribFed`, `piscofins`, `pTotTrib`, `end` do tomador/intermediário). Método `str()` agora aceita `?SimpleXMLElement`; acessos a filhos opcionais usam `?->`.
- `DanfseDataBuilder`: Código de Tributação Municipal exibia "- -" quando `cTribMun` e `xTribMun` ausentes. Agora exibe "-".
- `DanfseDataBuilder`: `descTribNacional`/`descTribMunicipal` retornavam string vazia (em vez de "-") quando `xTribNac`/`xTribMun` ausentes, causando concatenação espúria no template.
- `DanfseDataBuilder`: email de emitente/tomador/intermediário era forçado a minúsculas via `strtolower()`. Portal nacional preserva o case do XML; SDK agora também preserva.
- `Formatter::limit()`: truncava no meio de palavra (ex.: "programas de co..."). Agora retrocede ao último espaço antes do limite (ex.: "programas de...").

### Changed

- DANFSE CSS compactado para maior paridade visual com o portal nacional: fontes reduzidas (body 7pt→6.5pt, labels 7pt→6.5pt, values 8pt→7pt), padding reduzido, QR Code 70px→60px. Adicionado `@page { size: A4 }`, `max-height` e `overflow: hidden` para garantir renderização em página única.

## [2.3.0] - 2026-04-15

### Added

- `NfsenClient::for()` e `NfsenClient::forStandalone()` ganham parâmetro `array|false|null $danfse`
  que ativa auto-geração de DANFSE PDF em `emitir()`, `emitirDecisaoJudicial()`, `substituir()`
  e `consultar()->nfse()`. Sentinel `false` força desligar quando config global está ativa.
- Campos `pdf: ?string` e `pdfErrors: list<ProcessingMessage>` em `NfseResponse`.
- `DanfseConfig::fromArray()` e `MunicipalityBranding::fromArray()` com validação schema-like
  (whitelist de chaves + tipos + regras de negócio; `InvalidArgumentException` no boot).
- Bloco `danfse` em `config/nfsen.php` com `enabled` gate e envs `NFSE_DANFSE_*`.
- `NfsenClient::danfse()` — gera DANFSE (PDF e HTML) a partir do XML da NFS-e autorizada. Aceita `DanfseConfig|array|null`.
- Customização via `DanfseConfig` (logo de empresa) e `MunicipalityBranding` (identificação do município emissor).
- Métodos `label()` e `labelOf(?string)` nos enums `OpSimpNac`, `RegApTribSN`, `RegEspTrib`, `TpRetISSQN`, `TribISSQN` e `NfseAmbiente`.
- Exceção `XmlParseException`.

### Changed

- `DanfseDataBuilder`: fallback de `tpAmb` inválido (fora de `{1,2}`) mudou de `PRODUCAO` para `HOMOLOGACAO`. Fail-safe visual — XML suspeito renderiza com watermark "SEM VALIDADE JURÍDICA". Não afeta NFS-e autorizadas reais; SEFIN sempre emite `tpAmb` válido.
- `config/nfsen.php`: envs vazias (`NFSE_DANFSE_LOGO_PATH=`, `NFSE_DANFSE_LOGO_DATA_URI=`, `NFSE_DANFSE_MUN_LOGO_PATH=`, `NFSE_DANFSE_MUN_LOGO_DATA_URI=`) agora viram `null` em vez de string vazia — evita `InvalidArgumentException` no boot ao tentar carregar arquivo com path `''`.

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
