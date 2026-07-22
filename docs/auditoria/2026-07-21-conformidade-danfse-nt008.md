# Auditoria de conformidade — DANFSe × NT 008 v1.0

**Data:** 2026-07-21
**Fonte normativa:** `storage/danfse/nt-008-se-cgnfse-danfse-20260505.pdf` — Especificações Técnicas do DANFSe, Nota Técnica nº 008, versão 1.0, SE/CGNFS-e, 05/05/2026 (26 páginas).
**Escopo auditado:** `storage/danfse/template.php`, `storage/danfse/template.css`, `src/Adapters/DanfseDataBuilder.php`, `src/Adapters/DanfseHtmlRenderer.php`, `src/Danfse/ParticipanteBuilder.php`, `src/Danfse/Formatter.php`, `src/Danfse/Data/*`, `src/Adapters/DompdfHtmlToPdfConverter.php`.
**Método:** leitura integral da NT; leitura do código; e **medição do PDF gerado** — o content stream do Dompdf foi inflado e as coordenadas, corpos de fonte, cores e operadores de traço foram lidos diretamente (mesma técnica de `tests/Unit/Danfse/Nt008GeometryTest.php`). Nenhum código foi modificado.

---

## Veredicto

O DANFSe está **substancialmente conforme**. Os pilares obrigatórios da NT — página única (2.2), papel A4 retrato (2.2.1), margens (2.2.2), borda de página e sombreamento (2.2.3), tamanhos mínimos de fonte (2.4.1 a 2.4.4), cabeçalho e QR Code (2.4.3), mapeamento campo→tag do item 2.4.5, notas 5/7/8/9/10/11 e marca d'água de cancelamento/substituição (2.5.1/2.5.2) — estão implementados e verificados.

Foram encontradas **9 divergências**, das quais **1 é estrutural** (ordem das linhas nos blocos de participante, que contraria a disposição obrigatória do Anexo I) e 3 são de média severidade. Nenhuma impede a emissão; todas são corrigíveis sem redesenho.

| # | Severidade | Item da NT | Divergência |
|---|-----------|-----------|-------------|
| 1 | **Alta** | 2.2.4 + Anexo I + 2.4.5 | Ordem das linhas trocada em todos os blocos de participante |
| 2 | Média | 2.4.5, nota 12 | Campo "E-mail" sai vazio em vez de `-` |
| 3 | Média | 2.2.3 + Anexo I | Sem linhas divisórias internas (grade) nos blocos |
| 4 | Média | 2.4.5, nota 6 | Linha PIS/COFINS impressa incondicionalmente |
| 5 | Baixa | 2.4.5, nota 2 | Tomador ausente não colapsa para a frase da NT |
| 6 | Baixa | 2.4.5 | Rótulo do intermediário: "CNPJ / CPF" em vez de "CNPJ / CPF / NIF" |
| 7 | Baixa | 2.3.1 + nota 4 | Sem tratamento de "OPERAÇÃO NÃO SUJEITA AO ISSQN" |
| 8 | Baixa | 2.4.3 | QR Code de homologação usa URL diferente da fixada pela NT |
| 9 | Cosmética | — | Alíquotas com ponto decimal (`2.00%`) num documento pt-BR |

---

## Divergências

### 1 — Ordem das linhas nos blocos de participante contraria o Anexo I

**Severidade: alta.** Itens 2.2.4 ("devendo a disposição de campos **obrigatoriamente** obedecer ao disposto no respectivo anexo"), Anexo I e tabela 2.4.5.

O Anexo I dispõe cada bloco de participante assim:

```
PRESTADOR / FORNECEDOR | CNPJ / CPF / NIF | Indicador Municipal | Telefone
Nome / Nome Empresarial                   | Município / Sigla UF | Código IBGE / CEP
*Endereço                                 | E-mail
Simples Nacional na Data de Competência   | Regime de Apuração Tributária pelo SN
```

A tabela do item 2.4.5 confirma pelas ordenadas: `xNome`, `cMun ou xCidade` e `cMun + CEP` todos em **Sup 4,98**; `xLgr…xBairro` e `email` ambos em **Sup 5,62**.

O template agrupa de outro jeito — `Nome` com `E-mail` numa linha, `Endereço` com `Município` e `Código IBGE / CEP` na seguinte:

- `storage/danfse/template.php:146-169` (prestador)
- `storage/danfse/template.php:203-226` (tomador)
- `storage/danfse/template.php:251-274` (destinatário)
- `storage/danfse/template.php:304-327` (intermediário)

Medido no PDF gerado a partir de `tests/fixtures/danfse/nfse-autorizada.xml`:

| Rótulo | Medido | NT 2.4.5 |
|---|---|---|
| `Nome / Nome Empresarial` | x 0,28 / **y 4,87** | 0,30 / 4,98 |
| `E-mail` | x 10,57 / **y 4,87** | 10,51 / **5,62** |
| `Endereço` | x 0,28 / **y 5,44** | 0,30 / 5,62 |

O E-mail está subindo uma linha e o Endereço, dividindo linha com Município/CEP. Como o item 2.1 declara que os *tamanhos* do 2.4.5 são sugestão mas o 2.2.4 torna a *disposição* do Anexo I obrigatória, a troca de linhas é a única divergência aqui que a NT não tolera.

**Correção:** reorganizar as três linhas centrais dos quatro blocos para `Nome | Município | IBGE/CEP` e `Endereço | E-mail`. O bloco do destinatário não tem Indicador Municipal (correto: `TCRTCInfoDest` não o declara e o item 2.1.5 não o lista) e deve manter Telefone ocupando o restante da primeira linha.

---

### 2 — Campo "E-mail" sai vazio em vez de traço

**Severidade: média.** Item 2.4.5, nota 12: "Os campos sem informações no XML devem ser preenchidos com um traço (-)".

`ParticipanteBuilder` devolve string vazia quando o XML não traz `email`:

- `src/Danfse/ParticipanteBuilder.php:72` — `email: $this->firstOf($prest->email, $emit->email)` (prestador; `firstOf()` devolve `''`)
- `src/Danfse/ParticipanteBuilder.php:150` — `email: $this->str($pessoa->email)` (tomador, intermediário, destinatário; sem o default `'-'` que os campos vizinhos usam)

Verificado removendo `<email>` do XML de fixture: os três blocos renderizam `<span class="value"></span>`, imprimindo um campo em branco. Todos os demais campos do mesmo bloco (`nome`, `im`, `telefone`, `endereco`, `cep`, `codigoIbge`) já normalizam para `-`; o e-mail é a única exceção, e `naoIdentificado()` (`ParticipanteBuilder.php:112`) prova que a intenção era `-`.

**Correção:** `?: '-'` na linha 72 e default `'-'` no `str()` da linha 150.

---

### 3 — Blocos sem linhas divisórias internas

**Severidade: média.** Item 2.2.3 ("As linhas divisórias dos blocos de conteúdo do DANFSe deverão ter 0,5 (meio) ponto de espessura") combinado com o Anexo I, que desenha grade completa: linhas horizontais entre as linhas de campos e separadores verticais entre colunas — visíveis também nos exemplos das notas 2, 3 e 4 (item 2.4.5.1).

Medição do content stream do PDF gerado:

```
segmentos de reta: 13   horizontais = 13   verticais = 0
retângulo de borda: 5.500 5.500 584.280 830.890 re S  (1 w)  ✓ item 2.2.3
```

Os 13 segmentos são as bordas *entre* blocos (`.bordered-section { border-bottom: 0.5pt }`, `template.css:49-52`). Dentro de cada bloco não há nenhuma linha: `td { border: none; }` em `template.css:35-38`. O documento sai como texto posicionado sobre fundo branco, sem a grade do modelo.

O texto do 2.2.3 fala em "linhas divisórias dos blocos", o que isoladamente admitiria a leitura atual; o Anexo I, que o 2.2.4 torna obrigatório, não admite.

**Correção:** aplicar `border: 0.5pt solid #000` (ou ao menos `border-right`/`border-top`) nas células, com colapso já ativo (`border-collapse: collapse`). Atenção: acrescentar bordas muda a altura efetiva das linhas — reexecutar `DanfseSinglePageTest` depois.

---

### 4 — Linha PIS/COFINS impressa fora da janela da nota 6

**Severidade: média.** Item 2.4.5, nota 6: "Esta linha será impressa para as NFS-e emitidas com data de competência **até o final do ano-calendário de 2026**". Marcada com `***` no Anexo I, aplicando-se a `PIS - Débito Apuração Própria`, `COFINS - Débito Apuração Própria` e `Descrição Contrib. Sociais - Retidas`.

`storage/danfse/template.php:474-487` imprime a linha sempre. Não há leitura de `dCompet` para decidir — o campo é lido apenas para exibição (`DanfseDataBuilder.php:127`) e `DanfseTributacaoFederal` não carrega o sinalizador.

Note-se o contraste com a nota 5, que **está** implementada corretamente (`exibeRegimeEImunidade` / `exibeBeneficioEDeducoes`, `DanfseDataBuilder.php:288-289`) — a mecânica de supressão condicional já existe, só não foi estendida à nota 6.

**Impacto temporal:** nulo até 31/12/2026; a partir de competência 2027 o DANFSe imprimirá uma linha que a NT manda omitir.

**Correção:** derivar um booleano de `dCompet <= 2026-12-31` no builder e condicionar a segunda linha do bloco "TRIBUTAÇÃO FEDERAL (EXCETO CBS)".

---

### 5 — Tomador ausente não colapsa para a frase da NT

**Severidade: baixa.** Item 2.4.5, nota 2 e item 2.3.1.

Quando o XML não traz `<toma>`, `ParticipanteBuilder::tomador()` devolve `naoIdentificado()` (`ParticipanteBuilder.php:85-87`) e o template imprime o bloco inteiro com oito traços. A NT (nota 2) diz "informar, nos respectivos blocos, **apenas**: 'TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e'".

Verificado: com `<toma>` removido, a string não aparece no HTML.

O destinatário (`template.php:277-281`) e o intermediário (`template.php:330-334`) já implementam o colapso corretamente — o tomador é a lacuna.

**Ressalva interpretativa:** o item 2.3.1 abre com "*Poderão* ser feitas as seguintes supressões", o que é permissivo, enquanto a nota 2 usa o imperativo "informar". A leitura conservadora (colapsar) é a que o Anexo I ilustra e a que o próprio código já adota para os outros dois blocos — a inconsistência interna é o argumento mais forte para corrigir.

---

### 6 — Rótulo do intermediário incompleto

**Severidade: baixa.** Item 2.4.5, linha "CNPJ / CPF / NIF" do bloco INTERMEDIÁRIO DA OPERAÇÃO (caminho `NFSe/infNFSe/DPS/infDPS/interm/`, tamanho `18 / 14 / 40`).

`storage/danfse/template.php:292` imprime `CNPJ / CPF`. Os blocos de prestador (`:134`), tomador (`:191`) e destinatário (`:243`) usam a forma completa. O valor exibido já vem de `Identificacao`, que resolve CNPJ, CPF, NIF e `cNaoNIF` — só o rótulo está curto.

**Correção:** trocar por `CNPJ / CPF / NIF`.

---

### 7 — Sem tratamento de "OPERAÇÃO NÃO SUJEITA AO ISSQN"

**Severidade: baixa.** Item 2.3.1 e nota 4 do 2.4.5: para operações sem incidência de ISSQN, informar no bloco apenas "TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN", com altura mínima de 0,32 cm e largura mínima de 20,40 cm.

O template sempre renderiza o bloco completo (`template.php:371-452`). `TribISSQN` distingue as opções (o leiaute prevê 4), mas nenhuma delas dispara o colapso.

Como o 2.3.1 é permissivo ("poderão"), imprimir o bloco com traços não é infração. É, porém, a oportunidade que a NT oferece para devolver altura aos quadros elásticos de "Descrição do Serviço" e "Informações Complementares" — que é justamente onde o pior caso do documento aperta.

---

### 8 — URL do QR Code em homologação

**Severidade: baixa.** Item 2.4.3 fixa o endereço `https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=` sem prever variante.

`src/Adapters/DanfseHtmlRenderer.php:15-17` define duas constantes e escolhe `https://hom.nfse.fazenda.gov.br/ConsultaPublica/?tpc=1&chave=` quando `tpAmb = 2`.

Funcionalmente é a decisão certa — um QR de homologação apontando para produção não resolve chave alguma. É, ainda assim, um desvio do texto literal. Vale registrar a justificativa em comentário no código, ou pelo menos no README, para que não seja lido como descuido numa auditoria fiscal.

---

### 9 — Alíquotas com ponto decimal

**Severidade: cosmética.**

`DanfseDataBuilder.php:291` (`$pAliq.'%'`) e `percentOrEmpty()` (`:386-391`) concatenam o valor cru do XML. Medido: `2.00%`. Todos os valores monetários passam por `Formatter::currency()` e saem com vírgula (`R$ 1.500,00`), então o documento mistura as duas convenções.

A NT não especifica separador decimal para percentuais. Não é não conformidade — é inconsistência interna do documento em pt-BR.

---

## Conformidades verificadas

Cada item abaixo foi conferido no código **e** confirmado por medição no PDF gerado, salvo indicação em contrário.

### Estrutura e formulário (2.2)

| Item | Exigência | Situação |
|---|---|---|
| 2.2 | Página única, obrigatoriamente | ✅ `max-height: 829pt` (`template.css:26`) + `DanfseSinglePageTest` exercita o pior caso da norma |
| 2.2.1 | A4 retrato, mínimo 210×297 mm | ✅ `@page { size: A4 portrait }` + `setPaper('A4','portrait')` |
| 2.2.2 | Margem entre 0,15 e 0,20 cm em todos os lados | ✅ `margin: 5pt` = 0,176 cm; borda medida em `5.500 5.500 584.280 830.890` |
| 2.2.3 | Borda de página de 1 ponto | ✅ operador `1 w` antes do retângulo de borda |
| 2.2.3 | Divisórias de 0,5 ponto | ⚠️ presentes entre blocos (`0.5 w`), ausentes dentro deles — ver divergência 3 |
| 2.2.3 | Sombreado cinza 5% no cabeçalho, nos títulos de bloco e em "Emitente da NFS-e" e "Valor Líquido da NFS-e + IBS/CBS" | ✅ `#f2f2f2`, medido `0.949 0.949 0.949 rg`; 12 retângulos preenchidos = cabeçalho + 9 títulos + os 2 campos nominados |

### Cabeçalho e identificação (2.4.3, 2.1.1, 2.1.2)

| Exigência | Situação |
|---|---|
| Logomarca oficial da NFS-e à esquerda | ✅ embarcada em `storage/danfse/logo-nfse.png`, não configurável (justificado em `DanfseHtmlRenderer.php:23-29`: substituí-la imprimiria informação fora do XML, vedado pelo 2.1) |
| "DANFSe v2.0" e "Documento Auxiliar da NFS-e" ao centro, 9 pt Arial negrito | ✅ medido `/F2 9.0 Tf` para ambas |
| Município do emitente à direita, 8 pt, formato normal | ✅ medido `/F1 8.0 Tf` em x 15,69 cm (NT: 15,62) |
| Ambiente Gerador e Tipo de Ambiente, 6 pt | ✅ `.header-ambiente { font-size: 6pt }`, posição coberta por `Nt008GeometryTest` |
| "MUNICÍPIO": não exibir quando o item do código de tributação nacional for 99 | ✅ `DanfseDataBuilder::buildMunicipioEmitente()` devolve `''` e o template omite a linha — corretamente distinto de `'-'` |
| "NFS-e SEM VALIDADE JURÍDICA" em homologação, 9 pt Arial negrito vermelho M100/Y100 | ✅ `.sem-validade` com `#ff0000`; condicionado a `tpAmb = 2`; fallback fail-safe para HOMOLOGAÇÃO em `tpAmb` inválido (`DanfseDataBuilder.php:114`) |
| QR Code em X 17,48 / Y 1,67 cm, mínimo 1,52×1,52 cm | ✅ verificado por `Nt008GeometryTest` diretamente no content stream |
| URL do QR | ⚠️ ver divergência 8 |
| Descrição sob o QR em 3 linhas, 6 pt, formato normal | ✅ medido `/F1 6.0 Tf`, coberto por teste |
| Chave de acesso em bloco único de 50 dígitos | ✅ prefixo `NFS` do atributo `Id` removido (`DanfseDataBuilder.php:90`) |
| Os 11 campos do item 2.1.2 | ✅ todos presentes, na disposição do Anexo I (chave; nNFSe/dCompet/dhProc; nDPS/serie/dhEmi; tpEmit/cStat/finNFSe) |

### Fontes (2.4)

| Exigência | Situação |
|---|---|
| Arial para títulos/labels | ✅ `body { font-family: Arial… }` |
| Microsoft Sans Serif para conteúdos | ⚠️ declarada em `.value`, mas não embarcável — fonte proprietária sem licença de redistribuição. Desvio conhecido e documentado em `template.css:81-90`, com justificativa sólida: o fallback óbvio (DejaVu Sans, que acompanha o Dompdf) é largo o bastante para empurrar o pior caso para a segunda página, trocando a divergência do 2.4 pela do 2.2, que é pior. O consumidor pode registrar a fonte no seu font dir |
| Preto sólido K100 | ✅ `color: #000`, medido `0.000 0.000 0.000 rg` |
| 2.4.1 — títulos de bloco: 7 pt, negrito, caixa alta | ✅ medido 7 pt em todos os 9 títulos |
| 2.4.2 — labels de campo: 6 pt, negrito | ✅ medido 6 pt (ex.: `CNPJ / CPF / NIF`) |
| 2.4.2 — exceção: labels do bloco de identificação em 7 pt, caixa alta | ✅ `.first-section .label` com `text-transform: uppercase`; medido 7 pt em `CHAVE DE ACESSO DA NFS-E` e `FINALIDADE` |
| 2.4.4 — conteúdo dos campos: 7 pt, formato normal | ✅ `.value { font-size: 7pt; font-weight: normal }` |

### Campos e mapeamento XML (2.1, 2.4.5)

| Exigência | Situação |
|---|---|
| 2.1 — nada impresso fora do XML | ✅ toda origem é tag da NFS-e; única exceção é a marca d'água, que a própria NT exige e que o XML não carrega (justificado em `MarcaDagua`) |
| Cobertura campo→tag da tabela 2.4.5 | ✅ `Nt008FieldCoverageTest` extrai a tabela do PDF para fixture e **valida a fixture contra o XSD a cada execução** — fixture desatualizada quebra a suíte em vez de virar requisito fantasma |
| 2.1.7 — "Descrição do Código de Tributação": `SE xTribMun <> "" ENTÃO municipal SENÃO nacional`, campo único, **sem label** | ✅ `resolveDescricaoTributacao()` + `template.php:356-360` sem `<span class="label">` |
| Truncamento por reticências nos limites da NT | ✅ 167 (descrição de tributação, campo de 170), 1297 (descrição do serviço, campo de 1300), 1997 (informações complementares, campo de 2000), 37 (imunidade e suspensão, campos de 40) |
| 2.1.10 — bloco IBS/CBS completo (17 campos) | ✅ apurados lidos de `infNFSe/IBSCBS`, declarados de `infDPS/IBSCBS`; grupo inteiro é `minOccurs=0` e degrada para traços em NFS-e anterior à reforma |
| "Exclusões e Reduções da Base de Cálculo" = somatório de 5 origens | ✅ `sumCurrency()` sobre `vDescIncond + vCalcReeRepRes + vISSQN + vPis + vCofins` |
| 2.1.11 — "Total do IBS/CBS" = `vIBSTot + vCBS`; "Valor Líquido + IBS/CBS" = `vTotNF` (não recalculado) | ✅ `buildTotais()` |
| Retenções: `vTotalRet` com recomposição quando ausente; `tpRetISSQN = 1` ("Não Retido") não entra na soma | ✅ `DanfseDataBuilder.php:406-428` |
| Nota 5 — supressão de linha quando **nenhum** campo da linha tem dado | ✅ `exibeRegimeEImunidade` / `exibeBeneficioEDeducoes` |
| Nota 7 — chave da substituída como `NFS-e Subst.: <chave>` | ✅ |
| Nota 8 — `Cod. Obra:` e `Insc. Imob.:` | ✅ |
| Nota 9 — `Cod. Evt.:` | ✅ |
| Nota 10 — linha fixa e obrigatória de totais aproximados, imune ao truncamento | ✅ `DanfseTotaisTributos::linhaNt008()` reproduz o texto e a pontuação da nota; o template a imprime em **célula própria**, fora da área que trunca (`template.php:623-627`) — atende ao "sem prejuízo da linha" |
| Nota 10 — valores monetários **ou** percentuais | ✅ `totalTributo()` prefere `pTotTrib`, cai para `vTotTrib` |
| Nota 11 — canhoto opcional | ✅ não implementado, o que a nota autoriza |
| Nota 12 — campos sem informação com traço | ⚠️ atendido em toda parte **exceto** e-mail — ver divergência 2 |
| Ordem dos 10 campos de "Informações Complementares" | ✅ exatamente a da tabela 2.4.5, separados por ` \| `, rótulo some junto com o campo ausente |
| Nota 3 — "O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO" | ✅ derivado de `indDest = 0`, distinto do caso "não identificado" da nota 2 |
| Nota 2 — destinatário e intermediário não identificados | ✅ (tomador é a exceção — divergência 5) |

### Cancelamento e substituição (2.5)

| Exigência | Situação |
|---|---|
| Marca d'água "CANCELADA" / "SUBSTITUÍDA" | ✅ `MarcaDagua` com os dois casos |
| Na diagonal | ✅ **verificado no PDF**: matriz `0.707 0.707 -0.707 0.707 cm` = rotação de 45° — o Dompdf honra o `transform: rotate(-45deg)` |
| Formato normal, mínimo 50 pontos, Arial | ✅ medido `/F1 50.0 Tf` |
| Cinza K35 | ✅ medido `0.651 0.651 0.651 rg` = `#a6a6a6` = 35% de preto |
| Origem do dado | ✅ escolhida por quem renderiza, não pelo `cStat` — correto: cancelamento e substituição chegam como evento posterior, e `cStat` só descreve como a nota foi gerada |

---

## Observações que não são divergências

**Compressão vertical em relação à tabela 2.4.5.** O documento gerado termina por volta de 19,5 cm; a tabela da NT projeta o canhoto em 28,10 cm. Medições (topo, em cm): bloco do prestador 4,39 (NT 4,34), tomador 6,68 (6,92), serviço 10,40 (12,74), tributação municipal 11,87 (14,43), valor total 17,58 (20,90), informações complementares 18,73 (22,27).

Isto **não é não conformidade**: o item 2.1 é explícito — "Embora os tamanhos descritos no item 2.4.5 não sejam obrigatórios, o DANFSe deverá ser impresso conforme o modelo permitido (conforme o item 2.2.4) e utilizando-se os tamanhos mínimos de fonte descritos no item 2.4 e seguintes". Tamanhos e posições são sugestão; disposição e corpo de fonte é que obrigam. A folga é o que dá margem aos quadros elásticos e ao canhoto, se ele for adotado.

**Blocos colapsados e o mínimo das notas 2/3/4.** As notas exigem altura mínima de 0,32 cm e largura mínima de 20,40 cm para os blocos reduzidos. O template usa um `div` de 7 pt sem altura declarada (`template.php:232`, `:278`, `:331`), o que resulta em algo próximo de 0,25–0,30 cm. Vale medir e, se necessário, fixar `min-height`.

**Código morto.** `DanfseServico::descTribNacional` e `::descTribMunicipal` são preenchidos por `buildServico()` (`DanfseDataBuilder.php:213,215`) e nunca consumidos pelo template — o campo impresso é `descricaoTributacao`, resolvido pela regra do 2.4.5. Não afeta conformidade; é resíduo de antes da unificação do campo.

---

## Recomendação de ordem de correção

1. **Divergência 1** (ordem das linhas) — única infração à disposição obrigatória do Anexo I. Reexecutar `Nt008GeometryTest` e `DanfseSinglePageTest` depois.
2. **Divergência 2** (e-mail sem traço) — duas linhas, sem risco de layout.
3. **Divergência 3** (grade interna) — resolve junto com a 1 se as bordas forem aplicadas na mesma passada; altera altura de linha, então valide a página única.
4. **Divergência 4** (nota 6) — prazo real: competências a partir de 2027.
5. **Divergências 5 a 7** — colapsos de bloco; ganham altura para os quadros elásticos.
6. **Divergências 8 e 9** — documentar a 8; a 9 é polimento.

Vale acrescentar à suíte um teste de disposição que ancore as ordenadas relativas dos rótulos dentro de cada bloco de participante — é exatamente o tipo de regressão que o `Nt008GeometryTest` foi criado para pegar e que hoje passa despercebida, porque ele mede o cabeçalho e o QR Code, mas não o miolo.
