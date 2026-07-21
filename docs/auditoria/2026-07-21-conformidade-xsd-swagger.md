# Auditoria de conformidade: código ↔ XSD/swagger

**Auditoria original:** 2026-07-21, sobre `main` @ `9ff9dd6`
**Revisão e revalidação:** 2026-07-21, sobre `main` @ `babc3d0`
**Escopo:** somente auditoria — nada foi editado em `src/`, `tests/` ou `storage/`.

> **Nota de revisão.** Este documento passou por um review que encontrou quatro problemas
> nele: dois achados já corrigidos entre a auditoria e a revisão (`6c7e887`, `1a9b507`),
> uma referência `arquivo:linha` obsoleta, e — o mais grave — uma linha da tabela
> "conforme" sustentada por um script que não verificava nada. Todos os números e saídas
> abaixo foram **regerados contra `babc3d0`**. O histórico das correções está em
> [Correções aplicadas a este documento](#correções-aplicadas-a-este-documento).

## Motivação

As versões 2.7.0 e 3.0.0 corrigiram dois defeitos que atravessaram a stack de qualidade
inteira (phpstan + psalm + rector + pint + 100% coverage + 100% type-coverage + 100%
mutation score, tudo verde) sem disparar nada:

1. `Enums\TipoEvento` tinha os 18 códigos corretos, na ordem correta do swagger, mas os
   **nomes** dos casos estavam deslocados em relação ao evento que cada código representa
   no XSD. `consultar()->eventos()` montava URL válida e devolvia o documento errado.
2. `Adapters\DanfseDataBuilder` lia `vBC`, `vISSQN`, `vDescCond` e `vDescIncond` de
   `valores/trib/tribMun`. O XSD põe esses campos em `infNFSe/valores` (`TCValoresNFSe`)
   e `valores/vDescCondIncond`. Cinco campos saíam `-` em todo PDF real.

Ambas passaram pelo mesmo motivo: **o artefato de verificação foi derivado do código, não
da fonte de verdade.** As ferramentas verificam *estrutura*; esses defeitos vivem no
*vocabulário* — qual identificador está colado em qual valor externo, e de qual nó do XML
um campo é lido.

## Metodologia

Toda afirmação de conformidade abaixo sai de um script que faz o parse do arquivo
autoritativo e compara programaticamente com o código. Leitura comparativa lado a lado não
foi aceita como verificação. Item sem script executado está listado como "não verificado".

Fontes de verdade, nesta ordem de autoridade:

- `storage/schemes/*_v1.01.xsd` — estrutura e enumerações
- `storage/schemes/tiposSimples_v1.01.xsd` — rótulos em prosa nos `<xs:documentation>`
- `storage/schemes/tiposEventos_v1.01.xsd` — documentação por elemento `eNNNNNN`
- `storage/schemes/SefinNacional-swagger.json` e `ADN-Contribuinte-swagger.json`

Os scripts estão preservados em [`docs/auditoria/scripts/`](scripts/). São descartáveis —
foram escritos para esta auditoria, não são parte da suite. Rodar a partir da raiz do repo
(alguns usam `vendor/autoload.php`).

Critério de severidade:

| Severidade | Critério |
|---|---|
| **Alta** | produz saída errada em silêncio |
| **Média** | falha ou degrada em cenário real |
| **Baixa** | só em condição rara ou de borda |

## Sumário

| # | Achado | Severidade | Estado em `babc3d0` |
|---|---|---|---|
| 1 | `RegApTribSN::label()` contradiz o XSD | Alta | **Corrigido** (rótulos transcritos do XSD; teste passou a derivar do XSD) |
| 2 | `prefeituras.json` 3501608 com templates vazios | Média | **Aberto** (dado); mecanismo corrigido em `1a9b507` |
| 3 | `DocumentoFiscal::fromArray()` usava `from()` | — | **Corrigido** em `6c7e887` |
| 4 | `DanfseDataBuilder` lê `emit->NIF`, inexistente no XSD | Baixa | **Aberto** |
| 5 | Fixtures JSON com XML-stub irreal | Baixa | **Aberto** |
| 6 | Teste do `cNBS` monta XML fora da ordem do XSD | Baixa | **Aberto** |

---

## Achados

### 1. ALTA — `RegApTribSN::label()` casos 2 e 3 contradizem o XSD

`src/Dps/Enums/Prest/RegApTribSN.php:21-22` (numeração no momento da auditoria)

> **Corrigido.** Os três rótulos passaram a ser transcrição literal do
> `<xs:documentation>` — o que também troca "pelo Simples Nacional" por "pelo SN" no caso 1.
> `tests/Unit/Dps/Enums/Prest/RegApTribSNLabelTest.php` era tautológico (repetia as strings
> do enum, passaria com qualquer rótulo) e foi reescrito para **extrair os rótulos do XSD em
> tempo de execução** e comparar. Verificado que o teste novo recusa a regressão: reintroduzir
> "pela NFS-e" no caso 2 faz 2 dos 3 testes falharem.

O XSD diz **"por fora do SN"**; o PHP diz **"pela NFS-e"**. Significados diferentes: o
rótulo impresso na DANFSe afirma que os tributos são apurados *pela NFS-e*, quando o campo
declara que são apurados *fora do Simples Nacional*, conforme legislação municipal/federal.

**Cenário:** prestador ME/EPP que ultrapassou sublimite emite com `regApTribSN=2`. O PDF
sai com "…e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo". A
informação fiscal está errada e não há sintoma nenhum.

**Script:** `scripts/audit_enum_labels.php` — extrai os pares `N - Rótulo;` dos
`<xs:documentation>` dos tipos `TS*` e compara com o `match` de cada `label()`.

Saída completa, sem cortes — o script marca `[DIFF]` nos **três** casos:

```
ENUM RegApTribSN  <-  DPS/<regApTribSN>  <-  XSD/TSRegimeApuracaoSimpNac
  [OK ] conjunto de cases == <xs:enumeration> (1,2,3)
  [DIFF] 1 => case ApuracaoSN
         XSD : Regime de apuração dos tributos federais e municipal pelo SN
         PHP : Regime de apuração dos tributos federais e municipal pelo Simples Nacional
  [DIFF] 2 => case ApuracaoSNIssqnFora
         XSD : Regime de apuração dos tributos federais pelo SN e ISSQN  por fora do SN conforme respectiva legislação municipal do tributo
         PHP : Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo
  [DIFF] 3 => case ApuracaoForaSN
         XSD : Regime de apuração dos tributos federais e municipal por fora do SN conforme respectivas legislações federal e municipal de cada tributo
         PHP : Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo
```

Confirmado nas duas versões do XSD — `tiposSimples_v1.00.xsd` e `v1.01.xsd` trazem "por
fora do SN".

O caso 1 (`SN` → `Simples Nacional`) também sai `[DIFF]`, mas é expansão inofensiva da
sigla: o significado é o mesmo. O defeito real está em 2 e 3, onde "por fora do SN" virou
"pela NFS-e". O script é literal por desenho — não tem como distinguir sinônimo de troca de
sentido, e essa é a razão de ele não decidir sozinho: cada `[DIFF]` exige julgamento.

**Corroboração:** `scripts/03d_fixture_coverage.php` mostra que `regApTribSN` **não existe
em nenhuma das duas fixtures DANFSe** — o rótulo nunca foi exercitado com valor real.
Mesmo padrão dos bugs 2.7.0/3.0.0.

---

### 2. MÉDIA — `prefeituras.json` 3501608 (Americana): dado inválido derruba 5 das 10 operações

`storage/prefeituras.json:9-14`

> **Revisado.** A auditoria original classificou como ALTA, descrevendo URLs silenciosamente
> erradas. O commit `1a9b507` (posterior à auditoria) fechou o mecanismo: `resolveOperation()`
> agora rejeita template sem placeholder quando há parâmetros. **Falha barulhenta não é saída
> errada em silêncio** — logo ALTA → MÉDIA. O dado ruim em `prefeituras.json` continua.

A URL declarada para Americana (`.../api/adn/dps/recepcao`) é um **endpoint de recepção de
DPS**, não uma base. Como consequência todos os templates viraram `""`. O guard atual está
em `src/Adapters/PrefeituraResolver.php:111` e usa `str_contains($template, '{')`:

```php
if ($params !== [] && ! str_contains($template, '{')) {
    throw new InvalidArgumentException(/* ... */);
}
```

**Script:** `scripts/06_repro_americana.php` — usa o próprio `PrefeituraResolver` mais a
regra de `buildUrl()`, capturando a exceção para mostrar o comportamento por operação.

```
=== IBGE 3501608 (PRODUCAO) ===
  query_nfse             REJEITADO: Operação 'query_nfse' do município 3501608 não declara placeholder algum (template: ''), mas recebeu os parâmetros [chave] — eles seriam descartados silenciosamente. Corrija o template em storage/prefeituras.json.
  query_dps              REJEITADO: (idem, parâmetros [id])
  verify_dps             path='dps/DPS3303302...'      URL=https://nfse.americana.sp.gov.br/api/adn/dps/recepcao/dps/DPS3303302...
  query_events           REJEITADO: (idem, parâmetros [chave, tipoEvento, nSequencial])
  cancel_nfse            REJEITADO: (idem, parâmetros [chave])
  emit_nfse              path=''                       URL=https://nfse.americana.sp.gov.br/api/adn/dps/recepcao
  emit_court_order       path='decisao-judicial/nfse'  URL=https://nfse.americana.sp.gov.br/api/adn/dps/recepcao/decisao-judicial/nfse
  query_danfse           REJEITADO: (idem, parâmetros [chave])
  distribute_documents   path='contribuintes/DFe/1'    URL=https://adn.nfse.gov.br/contribuintes/DFe/1
```

**Cenários concretos, no estado atual:**

- `cancelar()`, `consultar()->nfse()`, `->dps()`, `->eventos()` e `->danfse()` em Americana
  lançam `InvalidArgumentException` na montagem da URL. **Cinco das dez operações estão
  indisponíveis** para o município — mas falham na cara do integrador, com mensagem que
  aponta o arquivo a corrigir.
- `verify_dps` e `emit_court_order` não estão sobrescritos, caem no `DEFAULT_OPERATIONS` e
  **concatenam** sobre o endpoint de recepção, produzindo
  `.../api/adn/dps/recepcao/dps/{id}`. Estes ainda saem errados em silêncio — o guard não
  os alcança porque o template tem placeholder; o problema é a base não ser uma base.
- `emit_nfse` funciona por acidente: a URL declarada *é* o endpoint de emissão.

`scripts/check_prefeituras.py` sinaliza os 5 templates vazios em operação parametrizada;
`scripts/06_repro_americana.php` mostra o efeito operação a operação.

**Correção de fundo:** `urls.sefin_*` de 3501608 deveria conter a base
(`https://nfse.americana.sp.gov.br/api/adn`) e `operations.emit_nfse` o caminho
(`dps/recepcao`), com os demais templates removidos para herdarem o DEFAULT — se e somente
se Americana seguir os caminhos nacionais, o que **não foi possível verificar** (ver
"Não verificado", item 3).

---

### 3. ~~MÉDIA~~ CORRIGIDO — `DocumentoFiscal::fromArray()` usava `from()` onde o swagger não garante o campo

`src/Responses/DocumentoFiscal.php:39-40` (numeração no momento da auditoria)

> **Corrigido em `6c7e887`** — "fix!: keep the distribution batch when one document is
> unparseable" —, commit posterior à auditoria. `from()` foi trocado por `tryFrom()` mais um
> campo `parseError`, e o lote sobrevive a um documento não parseável. `scripts/repro_lote.php`
> rodado contra `babc3d0` agora imprime:
>
> ```
> 1: sem excecao
> 2: sem excecao
> ```
>
> O achado está morto. O texto abaixo fica como registro do que foi observado e do porquê.

`DistribuicaoNSU` no `ADN-Contribuinte-swagger.json` **não tem lista `required`** e
`TipoEvento` é `nullable: true`. O código chama `TipoDocumentoFiscal::from(...)` e
`TipoEventoDistribuicao::from(...)`, que lançam `ValueError`/`TypeError` fora da hierarquia
de exceções do SDK. `DistribuicaoResponse::fromApiResult()` usa `tryFrom` com degradação
graciosa para `StatusProcessamento` — a assimetria é o defeito.

**Cenário:** a RFB já expandiu o conjunto de eventos (467201 e 907201 existem só no
swagger, não no XSD). No próximo código novo, um único item do lote derruba `distribuir()`
inteiro — os outros documentos, perfeitamente parseáveis, são perdidos.

**Script:** `scripts/repro_lote.php`, saída **no momento da auditoria** (`9ff9dd6`):

```
1: ValueError: "NOVO_EVENTO_2027" is not a valid backing value for enum OwnerPro\Nfsen\Enums\TipoEventoDistribuicao
PHP Warning: Undefined array key "TipoDocumento" in src/Responses/DocumentoFiscal.php on line 40
2: TypeError: TipoDocumentoFiscal::from(): Argument #1 ($value) must be of type string, null given
```

Schema, via `scripts/bloco_b_adn_names.py`:

```
== DistribuicaoNSU: obrigatoriedade dos campos
   required = AUSENTE (nenhum campo obrigatorio)
   NSU nullable=True | ChaveAcesso nullable=True | TipoDocumento nullable=False
   TipoEvento nullable=True | ArquivoXml nullable=True | DataHoraGeracao nullable=True
```

---

### 4. BAIXA — `DanfseDataBuilder` lê `emit->NIF`, que não existe em `TCEmitente`

`src/Adapters/DanfseDataBuilder.php:146`

> **Referência corrigida.** A auditoria original citava `:121`, correto em `9ff9dd6` mas
> obsoleto em `babc3d0` — hoje a linha 121 é `informacoesComplementares`. O achado em si
> permanece.

**Script:** `scripts/03b_extract_paths.php` extrai por AST **todo** acesso `$var->prop->prop`
do builder, resolvendo aliases de variáveis locais e bindings de parâmetro pelos call sites
(nada foi transcrito à mão); `scripts/03c_xsd_paths_auto.php` resolve cada caminho contra o
modelo de conteúdo do XSD.

```
FALHA L146  NFSe/infNFSe/emit/NIF
        <NIF> NAO existe sob <NFSe/infNFSe/emit> (tipo TCEmitente).
        Filhos validos: [CNPJ, CPF, IM, xNome, xFant, enderNac, fone, email]

--- 119 caminhos verificados, 1 falha(s) ---
```

> **Nota sobre o extrator.** Ao revalidar contra `babc3d0`, o script devolveu apenas **59**
> dos 119 caminhos e nenhuma falha. Causa: o builder ganhou um wrapper
> `$this->required($node, 'rotulo')` nas guardas de grupo obrigatório, e a propagação de
> alias não o atravessava — 60 caminhos sumiram e o resultado passou a ser um falso "tudo
> conforme". O extrator foi corrigido para tratar `required()` como transparente. É o mesmo
> modo de falha da tabela IBGE (ver [Correções aplicadas](#correções-aplicadas-a-este-documento)):
> **um verificador que deixa de verificar não fica vermelho, fica verde.**

Sem efeito prático: é o terceiro fallback de `firstNonEmpty()` e o emitente sempre tem CNPJ
ou CPF. Código morto que sugere um contrato inexistente. `toma/NIF` e `interm/NIF`
**existem** no XSD — só o de `emit` não.

---

### 5. BAIXA — fixtures JSON carregam XML-stub que não representa o payload real

`tests/fixtures/responses/cancelar_sucesso.json`, `tests/fixtures/responses/consultar_eventos.json`

**Script:** `scripts/01_validate_fixtures.php` descomprime os campos `*GZipB64` e valida
contra o XSD da raiz.

```
cancelar_sucesso.json  eventoXmlGZipB64 => '<NFSe xmlns="http://www.sped.fazenda.gov.br/nfse"/>'
consultar_eventos.json eventoXmlGZipB64 => '<Evento/>'
```

`cancelar_sucesso` põe raiz `<NFSe>` num campo que carrega documento de evento;
`consultar_eventos` usa `<Evento>` quando a raiz do `evento_v1.01.xsd` é `<evento>`
(minúsculo). Os consumidores foram inspecionados (`grep` em `src/`): o XML de evento é
repassado opaco, sem inspeção da raiz — não há bug hoje.

Fica registrado porque **nenhuma fixture do repo carrega um documento de evento real**,
então nada verifica que o consumidor de `eventoXmlGZipB64` recebe algo compatível com
`evento_v1.01.xsd`.

---

### 6. BAIXA — teste do `cNBS` monta XML fora da ordem do XSD

`tests/Unit/Adapters/DanfseDataBuilderTest.php:159-168`

**Script:** `scripts/01c_mutated_fixtures.php` reaplica por AST cada `str_replace` dos
testes sobre a fixture e valida o XML resultante.

```
L161: XML resultante INVALIDO vs NFSe_v1.01.xsd
      Element 'cNBS': This element is not expected. Expected is ( xDescServ ).
```

O teste insere `<cNBS>` **antes** de `<xDescServ>`; o XSD exige depois. Não esconde bug
(SimpleXML é indiferente à ordem), mas o teste afirma comportamento sobre um XML que a API
nunca emite.

As outras 21 mutações também saem inválidas, porém deliberadamente — strings vazias,
`tpAmb=99`, whitespace — para exercitar as defesas do `str()`. Não são achados.

---

## Conforme, verificado por script

| Item | Script | Resultado |
|---|---|---|
| Fixtures DANFSe vs XSD | `01_validate_fixtures.php` | `nfse-autorizada.xml` e `nfse-homologacao.xml`: **VÁLIDO** vs `NFSe_v1.01.xsd` |
| Caminhos SimpleXML do `DanfseDataBuilder` | `03b_extract_paths.php` + `03c_xsd_paths_auto.php` | 118/119 conformes (única falha: achado 4). Caminhos extraídos por AST, não transcritos |
| `TipoEvento` — códigos e nomes | `08_tipoevento.php`, `08b_tipoevento_swagger.php` | 16 códigos batem com a documentação `eNNNNNN`; conjunto **e ordem** idênticos ao `enum` do swagger; 467201/907201 ausentes do XSD e documentados como tal no próprio arquivo |
| `TribISSQN`, `TpRetISSQN`, `OpSimpNac`, `RegEspTrib`, `NfseAmbiente` — cases e rótulos | `audit_enum_labels.php` | Conjuntos `==` `<xs:enumeration>`; rótulos idênticos ao XSD a menos de acento/caixa (`Operação tributável`/`Tributável`, `Intermediario`/`Intermediário`) |
| `StatusDistribuicao`, `TipoDocumentoFiscal`, `TipoEventoDistribuicao`, `CodigoJustificativa*`, `NfseAmbiente` | `bloco_b_enums.py`, `bloco_b_labels.py`, `bloco_b_adn_names.py` | Diff simétrico vazio em todos; nomes dos casos coerentes token a token com o valor/rótulo autoritativo |
| Literais de chave das respostas | `bloco_a_crosscheck.py` | Todos existem — ver nota abaixo |
| Rotas `DEFAULT_OPERATIONS` vs swagger | `check_routes.py`, `check_hosts.py` | 10/10 rotas casam path, método, posição de placeholder e base (SEFIN vs ADN), com match **case-sensitive** exato. 0 divergências |
| `prefeituras.json` — placeholders vs call sites | `check_prefeituras.py` | Nenhum placeholder sobrando nem faltando em template não vazio; os únicos problemas são os `""` do achado 2 |
| Nomes de elemento emitidos pelos builders | `07_dps_elements.php` | 189 nomes emitidos, **0** inexistentes no conjunto dos XSDs |
| Tabela IBGE do DANFSe | `09_ibge.php` (corrigido — ver nota) | 5571 entradas, todas com chave de 7 dígitos e no formato `{nome,uf}`; **0** UFs fora da enumeração `TSUF`; 27 UFs distintas presentes; todos os códigos de `prefeituras.json` presentes |

**Nota sobre a tabela IBGE — a afirmação original era falsa.** A primeira versão de
`09_ibge.php` lia `$v[1]`, esperando um array posicional, mas `ibge-municipios.json` traz
`{"nome": ..., "uf": ...}`. `$uf` saía `''` em toda entrada, as 5571 caíam em `$badUf`, e a
saída era `valores sem sufixo "- UF" valida: 5571 …`. O documento afirmava mesmo assim
"todas as UFs dentro da enumeração `TSUF`, 27 UFs distintas" — número que veio de contar as
27 UFs *do XSD*, não de validar município nenhum.

Era exatamente o padrão que esta auditoria existe para caçar: **artefato de verificação que
não verifica**. O script foi corrigido (lê `$v['nome']`/`$v['uf']`, rejeita entrada fora de
formato e imprime aviso explícito de resultado não conclusivo se houver alguma) e rodado de
novo:

```
entradas: 5571
UFs no XSD: 27
chaves fora do formato 7 digitos: 0
entradas fora do formato {nome,uf}: 0
UF fora da enumeracao TSUF: 0
UFs distintas presentes na tabela: 27
codigos de prefeituras.json ausentes da tabela IBGE: 0
```

Agora a afirmação se sustenta. Note o alcance: isto valida **a UF**, não o nome do
município — ver "Não verificado", item 2.

**Nota sobre os literais de chave.** As 5 "divergências" na saída bruta de
`bloco_a_crosscheck.py` são **falso-positivo**, verificadas manualmente contra os schemas:
`erros` vive em `NFSePostResponseErro` e `erro` em `ResponseErro`; o código
(`src/Responses/ProcessingMessage.php:101,107`) lê os dois com `?? []`, o que é correto
para uma API que usa schemas de erro distintos por endpoint. O casing `idDps`/`idDPS` foi
confirmado nos dois schemas — é inconsistência deliberada da API, não bug do SDK.

---

## Não verificado — o que continua cego

Esta seção importa tanto quanto os achados: é o mapa do que a auditoria não alcançou.

1. **Se os valores vão para os campos certos na *escrita* do DPS.** Os builders
   auto-validam contra o XSD (`XsdValidator`), o que garante *estrutura* e nomes de
   elemento — mas não que `$dto->vDescIncond` foi escrito em `<vDescIncond>` e não em
   `<vDescCond>`. É exatamente a classe do bug do `DanfseDataBuilder`, do lado da emissão.
   Não há fonte autoritativa que ligue campo-da-API-pública a elemento do XSD; teria que
   ser derivado da própria intenção do código.

2. **Correção do conteúdo de `storage/ibge-municipios.json`.** Formato, UF válida e
   cobertura foram verificados. Não há fonte de verdade no repo para os **nomes** — a
   autoridade é a tabela IBGE, externa. Um nome de município trocado imprimiria localidade
   errada na DANFSe sem sintoma.

3. **Correção de `storage/prefeituras.json` além do achado 2.** O arquivo não tem schema
   nem contraparte autoritativa: não há como verificar programaticamente que
   `https://nfsesantanadeparnaiba.simplissweb.com.br` é de fato o host de Santana de
   Parnaíba, nem que mapear `query_danfse → 'nfse/{chave}'` (em vez de `danfse/{chave}`) é
   o comportamento real daquele provedor. Idem para 3547304 declarar `adn_*` apontando para
   host municipal quando distribuição é serviço nacional — pode ser intencional.

4. **Se o `ADN-Contribuinte-swagger.json` realmente prefixa `/contribuintes`.** O arquivo
   não tem `servers` nem `host`/`basePath` (`check_hosts.py`: `servers=None host=None
   basePath=None`). O prefixo `contribuintes/` nas rotas do código bate com os `paths`
   declarados, mas a base `https://adn.nfse.gov.br` não é confirmada por nenhum documento
   do repo — só a de homologação aparece. Também não foi confirmada
   `https://sefin.producaorestrita.nfse.gov.br/...`: o swagger SEFIN declara apenas o host
   de produção e `schemes: ["http"]`.

5. **Rótulos dos 22 enums de `src/Dps/Enums/` sem `label()`** — `CST`, `TpDedRed`,
   `TpImunidade`, `TpSusp`, `TpRetPisCofins`, `MdPrestacao`, `Mdic`, `MecAFComexP`,
   `MecAFComexT`, `MovTempBens`, `VincPrest`, `CNaoNIF`, `CMotivoEmisTI`, `TpEmit`,
   `FinNFSe`, `IndDest`, `IndFinal`, `TipoChaveDFe`, `TpEnteGov`, `TpOper`, `TpReeRepRes`.
   Foram verificados os 5 com `label()` (escopo definido). Nos demais só o *nome do case*
   codifica o significado, e um nome deslocado seria invisível — mas não há consumidor que
   imprima esse nome, então o risco é de uso incorreto pelo integrador, não de saída
   errada. A comparação não foi rodada.

6. **Documentos de evento reais.** Nenhuma fixture do repo contém um XML de evento válido
   contra `evento_v1.01.xsd` (achado 5). Todo o caminho de parse/consumo de eventos está
   verificado apenas contra stubs.

7. **A suite não foi executada**, por decisão de escopo: ela está verde e continuaria
   verde — foi ela que deixou os dois bugs originais passarem. Todos os achados vêm de
   comparação direta código ↔ XSD/swagger.

---

## Próximos passos sugeridos

Ordem por retorno:

1. ~~Corrigir o achado 1~~ — **feito**. A abordagem usada (teste que extrai o rótulo do XSD
   em vez de repetir a string do enum) é o modelo para os itens 2–4 abaixo.
2. **Promover `01_validate_fixtures.php` e `03c_xsd_paths_auto.php` a testes de
   conformidade de verdade.** O segundo teria pego sozinho o defeito do
   `DanfseDataBuilder` de 3.0.0; o primeiro teria rejeitado a fixture que ratificou o bug.
3. **Adicionar `03d_fixture_coverage.php` como guard de cobertura:** campo lido pelo
   builder e ausente de toda fixture ⇒ falha. É o sinal que faltou no achado 1.
4. **Promover `audit_enum_labels.php`** — compara `label()` com o `<xs:documentation>` do
   XSD, matando a classe de bug "rótulo errado no documento fiscal".
5. **Corrigir o dado de 3501608 em `prefeituras.json`** (achado 2), depois de confirmar com
   o provedor quais caminhos Americana expõe.
6. Corrigir os achados 4, 5 e 6 — baixo impacto, baixo custo.

Se os itens 2–4 virarem teste, vale um requisito de projeto para eles: **todo script de
conformidade tem que falhar quando não consegue verificar.** Os dois defeitos encontrados
*nesta auditoria* (tabela IBGE, extrator de caminhos) foram scripts que degradaram para
"verde" ao perder o alvo. Contar o que foi efetivamente comparado, e comparar essa contagem
com o esperado, é o que separa um teste de conformidade de um enfeite.

---

## Correções aplicadas a este documento

Review de 2026-07-21 sobre a primeira versão, com revalidação completa contra `babc3d0`.

| Item | Problema no documento | Correção |
|---|---|---|
| Achado 1 | Bloco de saída cortado escondia que o caso 1 também sai `[DIFF]` | Saída completa dos 3 casos + explicação de por que só 2 e 3 são defeito |
| Achado 2 | Severidade ALTA e mecanismo obsoletos: `1a9b507` já rejeita template sem placeholder; descrição citava o guard antigo `preg_match('/\{(\w+)\}/')` | ALTA → MÉDIA; guard atual (`str_contains`) e saída regerada; separado o que ainda falha em silêncio (`verify_dps`, `emit_court_order`) do que agora falha alto |
| Achado 3 | Reportado como aberto; `6c7e887` já trocou `from()` por `tryFrom()` + `parseError` | Marcado como CORRIGIDO, com a saída atual (`sem excecao`) e a antiga preservada |
| Achado 4 | `arquivo:linha` obsoleto (`:121`, hoje `informacoesComplementares`) | Corrigido para `:146` |
| Achado 4 | O extrator de caminhos devolvia 59 de 119 em `babc3d0` — não atravessava o novo wrapper `required()` — e o resultado virava um falso "0 falhas" | `03b_extract_paths.php` corrigido; 119 caminhos, 1 falha |
| Tabela "conforme" | Linha da tabela IBGE afirmava verificação que o script nunca fez (`$v[1]` em mapa associativo) | `09_ibge.php` corrigido e rerodado; nota explícita sobre a afirmação falsa |

Os dois últimos são a lição desta revisão, e são o mesmo defeito dos bugs 2.7.0/3.0.0 que
motivaram a auditoria: **um verificador quebrado não acusa; ele aprova.** Um relatório de
auditoria é um artefato derivado como qualquer outro e merece a mesma desconfiança que
aplica ao código que audita.
