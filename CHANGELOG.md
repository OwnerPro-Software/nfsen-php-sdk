# Changelog

All notable changes to this project will be documented in this file.

## [3.0.0] - Não lançado

### Fixed

- **O binding do container passa a honrar `detect_not_delivered`.** `NfsenClient::for()` já lia a chave desde a 2.6.0, mas `NfsenServiceProvider::register()` montava o client sem repassá-la, caindo no default `false`. Um app com `NFSE_DETECT_NOT_DELIVERED=true` recebia `RequestNotDeliveredException` ao construir via `::for()` e `IndeterminateResultException` ao resolver `NfsenClient` pelo container — mesma configuração, contratos de exceção diferentes, sem aviso. Quem resolvia pelo container perdia o opt-in em silêncio, exatamente a fragilidade que a flag existe para evitar. Era a única chave de `config/nfsen.php` que o provider deixava de repassar. (Reportado pela auditoria do Pulsar sobre a v2.7.0.)

- **HTTP 204 deixa de ser tratado como resultado indeterminado.** `NfseHttpClient::getResponse()` classificava todo 2xx sem JSON legível como corpo ininterpretável — mas "204 No Content" define corpo vazio, então ali a ausência de JSON é a resposta correta. Na prática, um 204 lançava `IndeterminateResultException`, cujo contrato obriga o chamador a reconciliar antes de qualquer retry, por um simples "não há nada a retornar". Também deixava inalcançável o branch `EMPTY_RESPONSE` de `DistribuicaoResponse::fromHttpResponse()`, escrito justamente para esse caso e coberto apenas por um teste que montava `HttpResponse` à mão. `distribuicao()->documentos()` agora devolve `sucesso: false` com `EMPTY_RESPONSE`. Um 204 com corpo não-JSON contradiz o próprio status e segue indeterminado; num 200, corpo vazio continua indeterminado.

- **`cancelar()` falhava em host cujo fuso tem offset de minuto quebrado.** `dhEvento` usava `date('c')`, mas `TSDateTimeUTC` só aceita offset com minuto zero e na faixa `-11..+12`. Em `Asia/Kolkata` (+05:30), `Asia/Kathmandu` (+05:45), `Pacific/Chatham` (+12:45) ou sob `+13:00`, a validação XSD reprovava e **todo** cancelamento falhava naquele host. Passou a usar `gmdate('c')`, que é sempre válido e representa o mesmo instante. Os exemplos de `dhEmi` no README e em `examples/` tinham o mesmo defeito latente — `dhEmi` é `TSDateTimeUTC` também — e foram corrigidos para `gmdate()`.

- **`validateChaveAcesso()` aceitava chave com quebra de linha no fim.** Em PCRE, `$` casa também antes de um `\n` final, então `/^\d{50}$/` aprovava `"1…1\n"` apesar da mensagem prometer "exatamente 50 dígitos numéricos". A chave seguia interpolada na URL, produzindo requisição malformada em vez de `InvalidArgumentException`. Corrigido com o modificador `/D`.

- **`emitir()` descartava os metadados quando a resposta não trazia `chaveAcesso`.** Dos três branches de resposta, o de `SEM_CHAVE` era o único que jogava fora `idDps`, `tipoAmbiente`, `versaoAplicativo` e `dataHoraProcessamento`, todos presentes no corpo. Sem a chave, o `idDps` é justamente o único identificador que resta para reconciliar via `consultar()->dps()`. Agora são preservados, aceitando as duas grafias (`idDps` e `idDPS`), já que essa resposta não casa com nenhum dos dois envelopes documentados.

- **`HttpException::getResponseBody()` truncava o corpo em 500 bytes**, quebrando `NfseConsulter::parseHttpError()`, que faz `json_decode()` desse valor: um envelope de erro da SEFIN maior que o corte virava JSON inválido, e as mensagens estruturadas eram substituídas por um genérico `HTTP error: N` cuja `descricao` era um fragmento de JSON quebrado. O corpo agora é guardado inteiro — a mensagem da exceção nunca o incluiu, então não há impacto em log.

- **`DanfseDataBuilder` desreferenciava nós ausentes.** `build()` validava apenas `infNFSe` e `DPS/infDPS`; a partir daí, um XML truncado fazia cada nível seguinte virar `null`, emitindo `Warning: Attempt to read property … on null` e terminando em `TypeError`. `toPdf()` tem `catch (Throwable)` e absorvia isso, mas `toHtml()` não tem catch algum, então o `TypeError` vazava para o chamador. Os grupos que o XSD declara obrigatórios (`infDPS/prest`, `prest/regTrib`, `infDPS/serv`, `serv/cServ`, `infDPS/valores`, `valores/trib`, `trib/tribMun`, `trib/totTrib`, `infNFSe/emit`, `infNFSe/valores`) passam a ser verificados na entrada, lançando `XmlParseException` que nomeia o grupo faltante. Os opcionais (`toma`, `tribFed`) seguem tolerados.

- **BREAKING — template de operação sem placeholder deixa de descartar parâmetros em silêncio.** `PrefeituraResolver::resolveOperation()` fazia `str_replace('{chave}', …)` sobre um template que não continha placeholder algum: a substituição não fazia nada, o guard de placeholder residual passava (não sobrou nenhum) e `buildUrl()` devolvia a URL base pelada. O fallback `??` para os defaults nacionais não cobre isso, porque dispara em `null`, não em `''`.

  Na prática: Americana/SP (IBGE `3501608`) declara `""` nas **seis** operações em `storage/prefeituras.json`. `consultar()->nfse($chave)` fazia GET na URL de recepção de DPS com a chave descartada, e `cancelar()` fazia **POST** de pedido de cancelamento nesse mesmo endpoint de recepção — ambos sem erro algum. Agora, uma operação que recebe parâmetros e cujo template não tem onde colocá-los lança `InvalidArgumentException` nomeando a operação, o município, o template e o arquivo a corrigir.

  `""` continua válido para operações sem parâmetro (emissão), em que a URL base do município já é o path completo de recepção — o caso legítimo que a estrutura de dados foi feita para expressar. Emissão em Americana segue funcionando; consultas e cancelamento naquele município passam a falhar de forma explícita até que os templates reais sejam preenchidos.

- **BREAKING — um documento ilegível deixa de derrubar o lote inteiro de distribuição.** `DocumentoFiscal::fromArray()` usava `TipoDocumentoFiscal::from()` sobre uma chave acessada sem checagem: um item sem `TipoDocumento` lançava `TypeError`, e um valor que esta versão do SDK não conhecesse lançava `ValueError` — que **não** é `NfseException` e escapava dos catches documentados. Um `ArquivoXml` corrompido lançava `NfseException`. Em qualquer um dos três, `distribuicao()->documentos()` perdia os outros 49 documentos do lote junto com o defeituoso. Nenhum campo de `DistribuicaoNSU` é obrigatório no swagger do ADN, e o governo pode passar a emitir tipos novos a qualquer momento.

  Agora o item entra no lote com os campos afetados em `null` e o motivo em `DocumentoFiscal::$parseError`. O `nsu` é preservado em todos os cenários, para que o chamador consiga refazer a busca daquele documento em específico. Alinha o comportamento ao de `DistribuicaoResponse::fromApiResult()`, que já degradava graciosamente diante de um `StatusProcessamento` desconhecido.

  **Migração.** `DocumentoFiscal::$tipoDocumento` passou de `TipoDocumentoFiscal` para `?TipoDocumentoFiscal`: quem faz `$doc->tipoDocumento->value` direto precisa checar `$doc->parseError === null` antes (ou usar `?->`). O construtor ganhou o sétimo parâmetro opcional `$parseError` no fim — chamadas posicionais e nomeadas existentes seguem válidas.

- **BREAKING — 5xx sem rejeição estruturada da SEFIN em operação que altera estado passa a lançar `IndeterminateResultException`** (antes: `HttpException`, ou nenhuma exceção). Afeta `emitir()`, `emitirDecisaoJudicial()`, `cancelar()` e `substituir()`. Duas rotas levavam ao mesmo risco: um 5xx com JSON não-envelope (`{"message": "Internal server error"}` de proxy) era devolvido como resultado normal e virava `sucesso: false` definitivo; um 5xx com corpo ilegível (página HTML de gateway) lançava `HttpException`. Nos dois casos o contrato do SDK classifica como resposta definitiva do servidor, e o README autoriza reenviar com o mesmo `nDPS` — mas um 5xx de proxy não prova que a SEFIN deixou de processar a emissão. Risco de nota duplicada.

  Um 5xx que **traz** `erros`/`erro` preenchido continua sendo rejeição definitiva: o envelope prova que a requisição chegou à SEFIN e foi processada. Consultas seguem lançando `HttpException` em 5xx — `GET` não altera estado, então não há o que reconciliar e o erro definitivo é a informação mais útil.

  **Migração.** Quem captura `HttpException` em torno de `emitir`/`cancelar`/`substituir` para tratar falha de servidor precisa passar a capturar também `IndeterminateResultException` — e, nela, reconciliar antes de qualquer reenvio, conforme a seção de reconciliação do README. `catch (CommunicationException)` cobre o caso novo sem distinguir subtipos.

- **`"erro": []` deixa de ser classificado como rejeição.** `ProcessingMessage::fromApiResult()` descartava a chave `erro` vazia desde a 2.3.1, mas os nove pontos que decidiam entre rejeição e processamento testavam `isset($result['erro'])` por conta própria. Um corpo `{"erro": [], "chaveAcesso": "35..."}` — forma que a API realmente produz — virava `sucesso: false` com `erros: []` (nenhuma mensagem), **descartando a `chaveAcesso` de uma nota autorizada** e disparando `NfseRejected('UNKNOWN', null)`. O caller perdia a chave e ficava sem base para reconciliar. Afetava `emitir()`, `substituir()`, `cancelar()`, `consultar()->nfse()`, `->dps()`, `->eventos()` e `->danfse()`.

### Added

- `IndeterminateResultException::fromServerError()` — 5xx sem rejeição estruturada da SEFIN. Sem `phase`: nenhuma fase de transporte falhou, a resposta chegou inteira; o que falta é evidência sobre o processamento.
- `ProcessingMessage::hasApiError()` — critério único de "a resposta traz erro da SEFIN". Classificação e extração de mensagens agora derivam da mesma regra interna, o que impede a divergência acima de voltar. Também resolve o caso `{"erros": [], "erro": {...}}`, em que o plural vazio escondia o singular preenchido.

### Changed

- **BREAKING — `Enums\TipoEvento`: os 18 casos foram renomeados, sem exceção.** Os nomes anteriores foram atribuídos por posição sobre a lista numérica do swagger, sem conferir a documentação de cada elemento `eNNNNNN` em `storage/schemes/tiposEventos_v1.01.xsd`, e ficaram deslocados em relação ao evento real. Como o valor inteiro de cada caso nunca mudou, o defeito era silencioso: `consultar()->eventos()` montava a URL com um código válido, porém de outro evento, e devolvia o documento errado sem erro. Os códigos permanecem idênticos — apenas os nomes mudam.

  | Antes | Código | Agora |
  |---|---|---|
  | `CancelamentoPorIniciativaPrestador` | 101101 | `Cancelamento` |
  | `CancelamentoPorIniciativaFisco` | 101103 | `SolicitacaoCancelamentoAnaliseFiscal` |
  | `CancelamentoPorDecisaoJudicial` | 105102 | `CancelamentoPorSubstituicao` |
  | `CancelamentoPorDecisaoAdministrativa` | 105104 | `CancelamentoDeferidoAnaliseFiscal` |
  | `CancelamentoPorOficio` | 105105 | `CancelamentoIndeferidoAnaliseFiscal` |
  | `AnaliseParaCancelamento` | 202201 | `ConfirmacaoPrestador` |
  | `AnaliseParaCancelamentoDecisaoJudicial` | 202205 | `RejeicaoPrestador` |
  | `SolicitacaoCancelamento` | 203202 | `ConfirmacaoTomador` |
  | `SolicitacaoCancelamentoDecisaoJudicial` | 203206 | `RejeicaoTomador` |
  | `RejeicaoCancelamento` | 204203 | `ConfirmacaoIntermediario` |
  | `RejeicaoCancelamentoDecisaoJudicial` | 204207 | `RejeicaoIntermediario` |
  | `ConclusaoCancelamento` | 205204 | `ConfirmacaoTacita` |
  | `ConclusaoCancelamentoDecisaoJudicial` | 205208 | `AnulacaoRejeicao` |
  | `SubstituicaoPorIniciativaPrestador` | 305101 | `CancelamentoPorOficio` |
  | `SubstituicaoPorIniciativaFisco` | 305102 | `BloqueioPorOficio` |
  | `SubstituicaoPorOficio` | 305103 | `DesbloqueioPorOficio` |
  | `BloqueioNfse` | 467201 | `InclusaoNfseDan` |
  | `TravamentoNfse` | 907201 | `TributosNfseRecolhidos` |

  **Migração.** Não há camada de compatibilidade: `CancelamentoPorOficio` existe nos dois esquemas apontando para códigos diferentes (105105 antes, 305101 agora), então um alias depreciado mudaria o significado desse nome em silêncio — exatamente a falha que a correção elimina. Migre por **código**, não por nome: localize o valor inteiro que seu código usava hoje na coluna do meio e adote o nome da coluna da direita. Quem passava `int` direto (`eventos($chave, 105102)`) não é afetado.

  `Cancelamento` (101101) segue como default de `consultar()->eventos()` — só o nome mudou.

- `TipoEvento` passou a compartilhar o vocabulário de `TipoEventoDistribuicao`: os mesmos eventos, vistos pelos canais de consulta e de distribuição, agora têm o mesmo nome nos dois enums.

### Notas

- Os templates reais de consulta e cancelamento de Americana/SP (`3501608`) continuam desconhecidos — o SDK não os inventa. Até que sejam preenchidos em `storage/prefeituras.json`, apenas a emissão funciona naquele município, e as demais operações falham com mensagem explícita em vez de montar uma URL silenciosamente errada.
- 467201 e 907201 não constam em nenhum XSD — existem apenas no swagger da SEFIN Nacional. Seus nomes derivam da correspondência posicional com as duas últimas entradas de `TipoEventoDistribuicao`, cujas 16 primeiras conferem com o XSD elemento a elemento. Os 16 códigos documentados no XSD são verificados por teste.

## [2.7.0] - 2026-07-21

Fecha o ciclo da reconciliação: o cancelamento indeterminado passa a ter os mesmos três desfechos ancorados em evidência que a emissão já tinha desde a 2.5.0 — registrou, comprovadamente não registrou, inconclusivo.

### Added

- `EventsResponse::EVENT_NOT_FOUND` — código de erro dedicado retornado em `erros[0]->codigo` quando `consultar()->eventos()` recebe HTTP 404 da SEFIN (evento comprovadamente inexistente, distinto de erro transitório). Erros originais da SEFIN, se presentes no corpo do 404, são preservados a partir de `erros[1]` — mesma convenção de `NfseResponse::DPS_NOT_FOUND`. Na reconciliação de cancelamento, `EVENT_NOT_FOUND` é a prova de que o cancelamento não registrou e o reenvio é seguro; qualquer outro `sucesso: false` permanece inconclusivo.
- `IndeterminateResultException::fromMissingResponseField()` — 2xx com JSON válido porém sem o campo obrigatório da operação.
- `ExecutesNfseRequests::executeRaw()` ganhou o parâmetro opcional `$requiredField`: um 2xx cujo corpo não traga o campo como string não-vazia lança `IndeterminateResultException` de dentro do pipeline, disparando `NfseFailed` (nunca `NfseQueried`). Porta interna — ver nota de `@internal` abaixo.

### Changed

- **`consultar()->eventos()` com HTTP 404** retorna `sucesso: false` com `EVENT_NOT_FOUND` em `erros[0]` — antes o 404 sem corpo estruturado lançava `HttpException`, e com corpo estruturado virava um `sucesso: false` genérico, indistinguível de erro transitório.
- **`ExecutesNfseRequests` marcado `@internal`** e **`ExecutesNfseRequests::execute()` removido** — `consultar()->eventos()` passou a usar `executeRaw()` (precisa do status para distinguir o 404), único consumidor restante do método. A interface é porta de wiring, construída apenas pelo `NfsenClient`: só afeta quem a implementa por conta própria; nenhum uso público muda.
- **HTTP 404 sem corpo de erro não dispara mais `NfseQueried`** em `consultar()->dps()` e `consultar()->eventos()` — o recurso não existe, então não é consulta bem-sucedida (nem rejeição da SEFIN, que continua disparando `NfseRejected` quando o 404 traz `erros`/`erro`). `NfseRequested` continua sendo disparado. Listeners que contavam consultas bem-sucedidas deixam de contar 404s.
- **`consultar()->eventos()` com 2xx sem `eventoXmlGZipB64`** (ex.: corpo `{}`) agora lança `IndeterminateResultException` — antes retornava `sucesso: true` com `xml: null`. 404 é o sinal canônico de ausência; 200 sem o campo obrigatório é anomalia ininterpretável, e a régua da 2.5.0/2.6.0 é "classificação exige certeza, viés para indeterminado". Quem tratava `xml: null` como "sem evento" deve migrar para o branch `EVENT_NOT_FOUND`. Nesse caminho os listeners observam `NfseRequested` → `NfseFailed`, como já acontece com 2xx de corpo ilegível.

## [2.6.0] - 2026-07-20

Separa "não entregue" de "indeterminado": falhas de DNS, conexão TCP e handshake TLS acontecem antes de qualquer byte HTTP ser enviado — a requisição comprovadamente não chegou à SEFIN, e o retry direto é seguro sem reconciliação. Opt-in para não quebrar catches da 2.5.0.

### Added

- `Exceptions\CommunicationException` — base abstrata das falhas de comunicação. `IndeterminateResultException` agora a estende (antes estendia `NfseException` diretamente; `instanceof NfseException` continua valendo). `catch (CommunicationException)` cobre os dois subtipos e equivale a tratar tudo como indeterminado — sempre seguro.
- `Exceptions\RequestNotDeliveredException` — a requisição comprovadamente nunca foi entregue (`phase`: `dns`|`connect`|`tls`); a operação não foi processada e o reenvio direto é seguro. Lançada **apenas** com `detectNotDelivered: true`.
- Flag `detectNotDelivered` (default `false`): parâmetro em `NfsenClient::forStandalone()`, chave `detect_not_delivered` / env `NFSE_DETECT_NOT_DELIVERED` no config Laravel. Com `false`, o comportamento é idêntico ao da 2.5.0 (toda falha de comunicação lança `IndeterminateResultException`).
- `Support\TransportFailureClassifier` — a decisão entregue/não-entregue usa apenas o errno do cURL extraído do handler context do Guzzle (nunca texto de mensagem, que varia entre versões do libcurl). Regra: certeza obrigatória, viés para indeterminado — `RequestNotDeliveredException` só com errno 6/7/35/58/60; errno ausente, ambíguo ou desconhecido classifica como indeterminado (mantendo a `phase` informacional do sniffing legado como diagnóstico). cURL 28 (timeout) é **sempre** indeterminado: em conexão keep-alive reutilizada o cURL zera os timers de connect (curl issue #2703), então timeout de connect não é provável.
- `IndeterminateResultException::fromTransportFailureWithPhase()` — variante com fase explícita derivada do errno.

### Notas

- Com `detectNotDelivered: true`, um timeout de connect (cURL 28) reporta `phase: 'read'` (errno não prova a fase de timeouts); com a flag desativada, mantém a fase legada `'connect'` sniffada da mensagem. `phase` é diagnóstico — não a use para decidir retry.
- A classificação vale para todas as operações HTTP do SDK (emissão, cancelamento, substituição, consultas, distribuição).

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
