# Resposta ao handoff do time Pulsar — `v3.0.0-rc2`

**Data:** 2026-07-26
**De:** manutenção do `ownerpro/nfsen-php-sdk`
**Para:** time Pulsar (api)
**Em resposta a:** handoff de 2026-07-26 (invoice 215, NFSe 122, `SefinNacional_1.6.0`)

---

## Resumo

Dos cinco itens, **três estão fechados na rc2** (1, 2 e 5) e **dois seguem abertos por decisão de escopo** (3 e 4), aguardando o formato que vocês preferirem. Além disso, a rc2 traz duas capacidades que o caso de vocês expôs mas que o handoff não pediu: **solicitar a análise fiscal pela API** e **acompanhar em que ponto ela está**.

| # | Item | Situação na rc2 |
|---|---|---|
| 1 | `erro` singular contendo lista descarta a mensagem | ✅ corrigido |
| 2 | Mensagem em formato desconhecido não deve sumir | ✅ corrigido |
| 3 | Expor a resposta crua nas respostas tipadas | ⏸️ aberto — precisa de decisão de formato |
| 4 | Erros de negócio tipados | ⏸️ aberto — precisa da lista de códigos |
| 5 | Teste de regressão: consulta de evento inexistente | ✅ já corrigido antes da rc1; testes existiam |

Instalação:

```
composer require "ownerpro/nfsen-php-sdk:^3.0@RC"
```

---

## Antes de tudo: a versão de produção de vocês

O handoff cita `v3.0.0-rc1` como referência, mas o log do item 5 (`sucesso: true` para os eventos `105104` e `105105`, que não existem) **não é produzível nem pela rc1 nem pela 2.7.0**. A proteção que impede isso entrou no commit `9ff9dd6`, e `git tag --contains 9ff9dd6` devolve apenas `v2.7.0` e `v3.0.0-rc1`.

Ou seja: **o ambiente que gerou aquele log roda ≤ v2.6.0**. Vale conferir o que está de fato instalado, porque nessa faixa há mais coisa além do item 5 — ver a seção seguinte.

---

## 1. `erro` singular contendo lista — corrigido

O diagnóstico de vocês estava correto em cada detalhe, inclusive a causa (`array_keys($result['erro']) === [0]`). A correção aplicada é a que vocês propuseram, com uma diferença: em vez de embrulhar o mapa direto, as duas formas passam pelo mesmo filtro.

```php
$erro = $result['erro'] ?? null;

if (! is_array($erro)) {
    return [];
}

return self::onlyMessages(array_is_list($erro) ? $erro : [$erro]);
```

Rotear as duas formas pelo mesmo ponto é o que garante que as tolerâncias não divirjam com o tempo: `{"erro": "Bad Gateway"}` de proxy continua fora da classificação, `{"erro": []}` idem, e item não-mensagem no meio da lista sai sem levar junto os erros reais.

O payload E0822 que vocês capturaram virou fixture (`tests/fixtures/responses/cancelar_rejeicao_erro_lista.json`), e os sete testes que o handoff pediu estão escritos — incluindo o de `parseEventResponse`, que confere `NfseRejected` carregando `codigo` e `descricao` reais.

**Efeito prático:** aquela linha de log passa a sair assim.

```
NFSe Nacional - Rejected {"operacao":"cancelar","codigo_erro":"E0822","mensagem_erro":"O prazo para o cancelamento da NFS-e expirou, conforme parametrização do município emissor da NFS-e.","correcao":null}
```

### Um caso vizinho que encontramos junto

`{"erros":[{}]}` classificava como rejeição sem conteúdo, enquanto `{"erro":{}}` — mesma ausência de conteúdo — não classificava. O plural só filtrava o que não fosse array; o singular já descartava vazio. Fechamos a assimetria: item vazio sai nos dois caminhos, e sem mensagem alguma o status HTTP volta a ser a informação definitiva, chegando por `IndeterminateResultException` (POST 5xx) ou `HttpException` — ambas com o corpo íntegro.

Isso importa para vocês porque era outra rota para o mesmo sintoma que investigaram.

---

## 2. Formato desconhecido — corrigido

Escolhemos a opção 1 de vocês, com o bruto em `complemento` em vez de `descricao`. `descricao` continua significando "a descrição que a SEFIN mandou"; misturar payload ali tornaria o campo ambíguo.

Quando nenhuma chave conhecida casa:

```php
$msg->codigo;      // ProcessingMessage::FORMATO_DESCONHECIDO
$msg->complemento; // {"error_code":"E0822","message":"Prazo expirado"}
$msg->descricao;   // null
```

`NfseRejected` expõe `complemento` em `correcao`, então o payload cai direto no log que vocês já têm — sem listener novo.

A constante é pública (`ProcessingMessage::FORMATO_DESCONHECIDO`), no mesmo espírito de `EVENT_NOT_FOUND`: dá para ramificar sobre ela em vez de comparar string.

**Limite conhecido:** isso cobre a *mensagem* irreconhecível, não o *envelope*. Se a SEFIN renomear a chave de topo (`erro`/`erros`), `hasApiError()` devolve `false` e nada chega ao consumidor. É exatamente o buraco que o item 3 fecha.

---

## 3. Resposta crua — aberto, precisa de decisão

Concordamos com o diagnóstico e com a prioridade. Confirmamos por leitura do código que não existe caminho público: `grep` por `raw` em `src/Responses/` volta vazio na 2.7.0, na rc1 e na rc2. O corpo bruto só escapa por exceção (`HttpException::getResponseBody()`, `IndeterminateResultException`); na rejeição, ele é descartado dentro de `NfseHttpClient::request()`, que devolve só o array decodificado.

Registramos também que reflexão sobre `final readonly` para diagnóstico não é aceitável como prática — o pedido é legítimo.

Não implementamos ainda porque a escolha muda a API pública e queremos a de vocês:

**(a) Campo `raw` nas respostas tipadas** — `?array $raw` em `NfseResponse` e `EventsResponse`. Direto, serve também ao sucesso inesperado, e não depende de listener. Custo: toda resposta passa a carregar o payload em memória, e ele pode ir parar em log de erro do consumidor sem filtro. `DanfseResponse` fica de fora: o corpo lá é PDF binário, não JSON.

**(b) Evento `NfseUnparsedError`** com o corpo bruto. Nada muda nas respostas, o consumidor decide se loga. Custo: só serve a quem já escuta eventos, e não ajuda no caso de sucesso fora do contrato.

Nossa inclinação é **(a)**, com **(b)** como complemento se vocês quiserem separar "logar" de "ler". Digam qual, e sai na rc3.

---

## 4. Erros de negócio tipados — aberto, precisa da lista

Também concordamos: `E0822` é decisão de fluxo, não ruído. Hoje o SDK tem quatro constantes de código (`DPS_NOT_FOUND`, `EVENT_NOT_FOUND`, `EMPTY_RESPONSE`, `FORMATO_DESCONHECIDO`), todas de estados do próprio SDK — nenhum código de negócio da SEFIN —, e as nove exceções são de transporte, certificado e validação.

Duas ressalvas antes de escolher a opção 1 do handoff:

**Exceção tipada muda o contrato de `cancelar()`.** Hoje rejeição da SEFIN volta como `NfseResponse(sucesso: false)` e não lança; passar a lançar para *alguns* códigos cria dois caminhos de saída para a mesma classe de resultado, e quem não capturar a exceção nova quebra na atualização.

**A alternativa não perde nada em expressividade.** Constantes públicas mais um predicado — `$response->rejeitadoPor(CodigoRejeicao::PrazoCancelamentoExpirado)` — permitem o mesmo `if` sem comparação de string e sem mudar o contrato.

O que precisamos de vocês: **a lista dos códigos em que a ação de vocês muda.** No repositório não há catálogo da SEFIN para derivar — o swagger não enumera códigos —, então a lista tem de vir do que vocês observam em produção. `E0822` é o primeiro. Se puderem enviar os que já viram (nota já cancelada, justificativa inválida, prazo, etc.) com o código exato e o texto, montamos o enum a partir disso.

---

## 5. Evento inexistente — já estava resolvido

Confirmado: `executeRaw('eventoXmlGZipB64')` exige o recibo e o 404 vira `EVENT_NOT_FOUND`. Entrou no commit `9ff9dd6`, publicado na **2.7.0** — antes da rc1, portanto.

Os três testes que vocês pedem já existiam e continuam verdes:

| Cenário | Onde |
|---|---|
| 404 → `sucesso: false` com `EVENT_NOT_FOUND` | `tests/Feature/NfsenClientConsultarTest.php:60`, `tests/Unit/Operations/NfseConsulterTest.php:426` |
| 200 sem `eventoXmlGZipB64` → nunca `sucesso: true` | `tests/Feature/NfsenClientConsultarTest.php:51` |
| Evento existente → `sucesso: true` **e** `xml` não nulo | `tests/Feature/NfsenClientConsultarTest.php:35` |

A proteção de vocês (`sucesso && xml !== null`) pode ser mantida — não custa nada —, mas a partir da 2.7.0 ela é redundante.

---

## O que veio junto, sem ter sido pedido

O caso de vocês terminou em "a via passa a ser substituição ou análise fiscal", e a análise fiscal não existia neste SDK. Agora existe.

### Solicitar a análise fiscal pela API

```php
$response = $client->solicitarAnaliseFiscalCancelamento(
    chave: '43044082229529488000120000000000012226077899661443',
    codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
    descricao: 'Prazo de cancelamento direto expirado',
);
```

É o evento `e101103` (`TE101103` em `tiposEventos_v1.01.xsd`), no mesmo `POST /nfse/{chave}/eventos` — o mesmo fluxo que o portal nacional oferece pela tela de confirmação. Sucesso significa **pedido registrado**, não nota cancelada: dispara `NfseFiscalAnalysisRequested`, nunca `NfseCancelled`, porque a NFS-e segue válida até o fisco decidir.

### Saber em que ponto está a análise

```php
$situacao = $client->consultar()->situacaoCancelamento($chave);
// SituacaoCancelamento::SemPedido | EmAnalise | Deferido | Indeferido
```

Vale explicar por que isso não era trivial de fazer do lado de vocês: **`TStat` não tem estado para análise em curso** — a nota continua `100` (Gerada) o tempo todo. O estado só existe nos eventos vinculados à chave (101103, 105104, 105105). O método lê `GET /NFSe/{ChaveAcesso}/Eventos` do ADN e resolve os três numa chamada. Vence o evento de maior NSU, não a posição no lote: o ADN não promete ordem, e um pedido novo depois de um indeferimento reabre a análise.

Se vocês já consomem `distribuicao()->documentos($nsu)`, os eventos `105104`/`105105` chegam nesse fluxo por NSU — o desfecho vem por lote, sem varrer nota a nota. Para uma tela que precisa do estado de uma nota específica, `situacaoCancelamento()` é o caminho.

---

## Notas de atualização

- **Breaking apenas para quem implementa as portas:** `CancelsNfse` e `ConsultsNfse` ganharam um método cada. Se vocês têm fakes ou decorators dessas interfaces nos testes, eles precisam declarar `solicitarAnaliseFiscalCancelamento()` e `situacaoCancelamento()`. Uso normal do cliente não muda.
- `NfseConsulter` (marcada `@internal`) recebe `DistributesNfse` como sexto parâmetro do construtor. Só afeta quem a constrói à mão em vez de usar `NfsenClient::for()`/`forStandalone()`.
- O restante dos breaking changes da 3.0.0 continua o mesmo da rc1 — ver `CHANGELOG.md`, seção `[3.0.0]`.

---

## Sobre o script de diagnóstico

Sim, é útil. Se puderem rodá-lo contra outros cenários de rejeição — especialmente **nota já cancelada** e **justificativa inválida** —, os payloads crus viram fixture aqui e alimentam a lista do item 4. Interessa o corpo exato, antes de qualquer normalização, junto do código HTTP.

---

## Verificação da rc2

```
pest --coverage --min=100 --parallel   → 100.0%
pest --mutate --min=100 --parallel     → 100.00%
pest --type-coverage --min=100         → 100.0%
rector / phpstan / psalm --taint-analysis / pint  → limpos
CI: PHP 8.3 / 8.4 / 8.5 × Laravel 11 / 12 / 13   → verde
```
