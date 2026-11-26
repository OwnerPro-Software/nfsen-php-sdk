# Adequações pendentes: o que corrigir e o que adequar

**Levantado em 26/11/2026**, sobre `ee8eddc`. Complementa
[`notas-tecnicas.md`](notas-tecnicas.md), que diz o que cada nota técnica traz. Aqui
está o que isso exige do código.

Este documento é só o levantamento. Nada do que está listado foi alterado, e nenhuma
decisão de desenho foi tomada.

## Resumo

| # | Item | Origem | Atinge a produção hoje? | Depende de | Tamanho |
|---|---|---|---|---|---|
| A1 | DANFSe: PIS/COFINS retidos | NT 008 v1.02 | **Sim**, nas NFS-e com `tpRetPisCofins` = 1 | Nada | Pequeno |
| A2 | Trocar a referência da NT 008 para a v1.02 | NT 008 v1.02 | Não (é a base dos testes) | A1 | Médio |
| A3 | README: data em que a API de DANFSe saiu do ar | NT 008 v1.01 | Só documentação | Nada | Mínimo |
| A4 | Distribuição: `cpfConsulta` e `TipoConsulta` | Manual do ADN v1.1 | A confirmar | Swagger atual do ADN | Pequeno |
| B1 | `vAjusteBC/documentos` | NT 009, 2.3 | Não | Decisão de API | Grande |
| B2 | `vAjusteBCIBSCBSComExt` | NT 009, 2.13 | Não | B3 | Pequeno |
| B3 | `comExt` reestruturado | NT 009, 2.12 | Não | Decisão de API | Médio |
| B4 | `imovel` reestruturado | NT 009, 2.6 | Não | Decisão de API | Grande |
| B5 | `bensMoveis` | NT 009, 2.7 | Não | Nada | Pequeno |
| B6 | `condominios` | NT 009, 2.15 | Não | Nada | Médio |
| B7 | `atvEvento/cMun` | NT 009, 2.14 | Não | Nada | Mínimo |
| B8 | Nome do tomador e do intermediário opcional | XSD de 24/11/2026 | Não | Nada | Pequeno |
| B9 | Recusa nos sub-builders e teste dourado mais largo | Revisão da `v3.0.0-rc6` | Não | Nada | Pequeno |
| C1 a C5 | Simples Nacional, notas de ajuste, `gPgtoVinc`, `indZFMALC`, doação e estorno | NT 005, 007 e 009 | Não | **XSD que não foi publicado** | — |

Os itens A valem para o leiaute de produção. Os itens B só passam a importar quando a
produção adotar o leiaute da NT 009, o que não tem data. Os itens C não podem ser
feitos.

## A. Corrigir no que já vale em produção

### A1. DANFSe: PIS/COFINS retidos

**O que a nota diz.** Item 2.4.5 da NT 008 v1.02, bloco "Tributação Federal (exceto
CBS)":

| Campo impresso | `tpRetPisCofins` = 1 | Demais casos |
|---|---|---|
| Contribuições Sociais - Retidas | `vRetCSLL` + `vPis` + `vCofins` | `vRetCSLL` |
| PIS - Débito Apuração Própria | `0,00` | `vPis` |
| COFINS - Débito Apuração Própria | `0,00` | `vCofins` |

**O que o SDK faz.** `DanfseDataBuilder::buildTribFed()` imprime `vRetCSLL`, `vPis` e
`vCofins` como vieram, sem olhar o `tpRetPisCofins`.

**O que muda.** A princípio, só `buildTribFed()`: os três campos chegam ao template como
textos já formatados.

**A decidir ao implementar.**

- O "Total das Retenções" vem de `vTotalRet`. Quando a NFS-e não o traz, o SDK refaz a
  soma com `vRetIRRF` + `vRetCP` + `vRetCSLL` + ISSQN retido, que é a fórmula da regra
  E1506 do Anexo VI. A v1.02 não altera essa linha. Com `tpRetPisCofins` = 1, a soma
  refeita deixa de bater com o campo "Contribuições Sociais - Retidas" impresso logo
  acima.
- "Exclusões e Reduções da Base de Cálculo" soma `vPis` e `vCofins`. A v1.02 também não
  altera essa linha, e a NT 007 diz que retenção posta nesses campos diminui a base
  indevidamente. O DANFSe imprime o que a nota manda; a divergência, se houver, é do
  XML.

**Como conferir.** Casos novos em `tests/Unit/Adapters/DanfseDataBuilderTest.php`, com
`tpRetPisCofins` 1 e com outro valor.

### A2. Trocar a referência da NT 008 para a v1.02

Os testes de cobertura do DANFSe partem de `tests/fixtures/nt008/campos-2.4.5.json`,
que `tools/extract-nt008.py` extrai do PDF da v1.0 em `storage/danfse/`.

Rodando o mesmo script sobre o PDF da v1.02:

- Os 94 campos continuam lá, com as mesmas coordenadas e os mesmos tamanhos.
- Três campos mudam de conteúdo: os de A1.
- **A detecção de bloco quebra.** Dez campos de IBS/CBS saem atribuídos ao bloco
  "INCIDÊNCIA / SIGLA UF", que não existe; "Nº NFS-E / CHAVE NFS-E" sai no bloco "CBS";
  e o bloco "IDENTIFICAÇÃO E ASSINATURA" não é reconhecido (24 blocos em vez de 25). O
  script precisa de ajuste antes de regenerar a fixture.
- O bloco "DESTINÁRIO DA OPERAÇÃO" passa a se chamar "DESTINATÁRIO DA OPERAÇÃO". Quem
  usa o nome do bloco como chave nos testes precisa acompanhar.

Arquivos que citam o PDF ou a fixture: `tools/extract-nt008.py`, `.gitattributes`,
`README.md`, `tests/Unit/Danfse/Nt008FieldCoverageTest.php` e
`tests/Unit/Danfse/Nt008TemplateCoverageTest.php`.

### A3. README: data em que a API de DANFSe saiu do ar

O README diz, em dois lugares, que a API remota de DANFSe foi sobrestada em 01/07/2026.
Essa é a data da NT 008 v1.0. As v1.01 e v1.02 a mudaram, e a página de implantações
registra a desativação em **03/08/2026**. O CHANGELOG repete a data antiga numa entrada
da `3.0.0`, que é registro histórico e pode ficar como está.

### A4. Distribuição: `cpfConsulta` e `TipoConsulta`

O manual "Guia de Utilização das APIs do ADN" v1.1, de 03/11/2026, muda o
`GET /DFe/{NSU}`:

- parâmetro novo `cpfConsulta`, que não pode vir junto de `cnpjConsulta` (erro `E2244`);
- consulta em nome de outra inscrição mediante procuração (erro `E2245` quando falta);
- propriedade nova `TipoConsulta` na resposta: `InscricaoCertificado`, `CnpjBase` ou
  `Procuracao`.

O SDK já envia `cnpjConsulta`. Não tem `cpfConsulta` nem lê `TipoConsulta`, e o
`ADN-Contribuinte-swagger.json` embutido não traz nenhum dos dois.

**Antes de implementar:** baixar o swagger atual do ADN e conferir se isso já está em
produção. A página de implantações registra a alternância de perfis na produção em
31/10/2026 só para o Emissor Nacional, e na Produção Restrita em 25/11/2026 para a API
da SEFIN. A API do ADN não é citada.

## B. Adequar ao leiaute da NT 009

Tudo aqui é no leiaute `nt009`. O leiaute padrão não muda em nenhum byte.

Hoje o `Nt009Scope` recusa com erro quatro grupos: `serv/comExt`,
`valores/vDedRed/documentos`, `IBSCBS/imovel` e `IBSCBS/valores/gReeRepRes`. Os itens
B1, B3 e B4 trocam cada recusa por emissão.

**Decisão de API comum a B1, B3 e B4.** Os grupos novos não são os antigos com outro
nome: mudam campos, obrigatoriedade e domínios. Há dois caminhos: DTOs novos, que só
valem no `nt009`, ou os DTOs atuais com campos opcionais a mais. A escolha vale para os
três e deve ser feita uma vez.

### B1. `vAjusteBC/documentos`

Substitui dois grupos de produção de uma vez: `valores/vDedRed/documentos` e
`IBSCBS/valores/gReeRepRes`.

| | Produção | NT 009 |
|---|---|---|
| Lista | `docDedRed`, 1 a 1000 | `docAjusteBC`, 1 a 1000 |
| Identificação do documento | `chNFSe`, `chNFe`, `NFSeMun`, `NFNFS`, `nDocFisc` ou `nDoc` | `dFeNacional`, `docFiscalOutro` ou `docOutro` |
| Tipo | `tpDedRed` | `tpAjusteBC`: 2, 5 a 9, 99, 101 a 105, 199 |
| Valores | `vDedutivelRedutivel`, `vDeducaoReducao` | `vTotDoc`, `vAjusteAplic` |
| Datas | `dtEmiDoc` obrigatória | `dtEmiDoc` e `dtCompDoc` opcionais |
| Fornecedor | `xNome` obrigatório | `xNome` opcional |
| Convivência | `pDR`, `vDR` ou `documentos`: um só | `pAjusteBCISSQN` ou `vAjusteBCISSQN`, e `documentos` ao lado |

Não há conversão automática do grupo antigo para o novo: a identificação do documento e
a tabela de tipos são outras. O enum de `tpAjusteBC` é novo.

### B2. `vAjusteBCIBSCBSComExt`

Campo opcional no fim de `vAjusteBC`, que aceita valor negativo. Falta emitir e falta
somar em "Exclusões e Reduções da Base de Cálculo" do DANFSe, lacuna que o README já
registra.

### B3. `comExt` reestruturado

| Mudança | Campos |
|---|---|
| Saem | `vServMoeda`, `nDI`, `nRE` |
| Entram, todos opcionais | `nFatura`, `vFatura`, `nDUIMP`, `nDUE`, `nContCambio` |
| Domínio | `mdPrestacao` ganha o valor 5, que o enum `MdPrestacao` não tem |

`vServMoeda` era obrigatório e deixa de existir.

### B4. `imovel` reestruturado

| | Produção | NT 009 |
|---|---|---|
| Raiz | `inscImobFisc`, e `cCIB` ou `end` | `cMun` obrigatório, `gLocacao`, `gUnidImob` |
| Unidades | Uma | `gUnidImob`, 0 a 99, cada uma com `inscImobFisc`, `cCIB` ou `end`, e `gAjusteBCLocImoveis` |
| Endereço | CEP ou endereço no exterior | Só CEP; `xBairro` passa a opcional |
| Locação | — | `gLocacao`: `pCopropriedade`, `vTotOper`, descontos totais, `dVencOrig` |
| Ajustes | — | `gAjusteBCLocImoveis`, 0 a 1000 por unidade; `tpAjusteBCLocImoveis`: 01 a 04 e 99 |

É o grupo que mais muda de forma.

### B5. `bensMoveis`

Grupo novo em `IBSCBS`, de 0 a 1000 ocorrências, com três campos obrigatórios:
`cNCMBemMovel`, `xNCMBemMovel` e `qtdNCMBemMovel`.

### B6. `condominios`

Grupo novo em `IBSCBS`, 0 ou 1:

- `dVencOrig`, obrigatório;
- `gCobranca`, 1 a 20, com `tpCobranca` (01 a 05 e 99), `vCobranca` e `gDetCobranca`,
  0 a 20, com `tpDetCobranca` (001 a 036 e 999) e `vDetCobranca`;
- `gDesconto`, 0 a 10, com `tpDesconto` (1 ou 2), `vDesconto` e `xTpDesconto`.

São três enums novos.

### B7. `atvEvento/cMun`

Campo opcional novo, entre `dtFim` e `idAtvEvt`. O grupo já é emitido sem ele.

### B8. Nome do tomador e do intermediário opcional

No XSD de 24/11/2026, `toma/xNome` e `interm/xNome` passam a opcionais. Os DTOs exigem
o nome nos dois leiautes. Não quebra nada; só impede emitir sem nome onde o leiaute novo
permite.

### B9. Recusa nos sub-builders e teste dourado mais largo

Pendências menores da revisão da `v3.0.0-rc6`:

- A recusa dos grupos reestruturados acontece em `DpsBuilder`. Quem usa `ServicoBuilder`,
  `ValoresBuilder` ou `IBSCBSBuilder` direto, no leiaute `nt009`, não é barrado.
- O teste dourado do leiaute de produção cobre duas DPS.
- Na fixture `nfse-ibscbs-nt009.xml`, o `vBC` de IBS/CBS não fecha com a regra 84 do
  Anexo VI. Vem da fixture de origem.

### O que o DANFSe do leiaute novo ainda não tem

A NT 008 nomeia as tags antigas e não foi atualizada para a NT 009. O SDK lê as posições
novas e infere duas somas. `verCalcIBSCBS` (NT 009, 2.9) não é impresso porque a NT 008
não o lista. Não há o que fazer até sair versão nova da NT 008, que já anuncia uma nota
específica para o DANFSe dos novos fatos geradores.

## C. Bloqueado: a nota descreve, nenhum XSD traz

| # | O que | Onde está descrito | O que destrava |
|---|---|---|---|
| C1 | Simples Nacional: `opSimpNac` 4, `regApIBSCBSSN`, `cAtvSN`, `gTribSN` | NT 009, 2.4 | Pacote de XSD com os campos |
| C2 | Notas de crédito e de débito: `finNFSe` 1 e 2, `tpNFSeCredito`, `tpNFSeDebito` | NT 009, 2.2 | Idem. O enum `FinNFSe` só tem o caso `Regular` |
| C3 | `gPgtoVinc` | NT 009, 2.8 | Idem |
| C4 | `indZFMALC` | NT 007, 2.a; Anexo VI v1.04.01 | Idem |
| C5 | `indDoacao`, `gEstornoCred` | NT 005 | Idem; a NT 009 não os retoma |

O sinal é entrada nova em "Atualizações e Implantações" ou pacote novo em "Documentação
Técnica (Homologação/testes)".

## Conferido, sem ação

- **Leiaute de produção.** O pacote publicado continua sendo o
  `esquemas-xsd-v1.01-20260209`, e os builders montam os 313 caminhos da DPS.
- **Fora os grupos acima, a DPS dos dois pacotes é a mesma.** A comparação da árvore
  inteira de `TCInfDPS` só encontrou o que já está implementado (`finNFSe`, `indDest`,
  `dest`, `CST`, `cClassTrib`, `cIndOp` e `gIBSCBS` opcionais) e os itens B3, B7 e B8.
- **NT 007, PIS/COFINS.** Os domínios de `CST` e de `tpRetPisCofins` batem com o XSD.
- **Códigos 99.02 a 99.04.** O XSD aceita qualquer `cTribNac` de seis dígitos.
- **Códigos de mensagem renumerados em 24/04/2026.** O SDK não cita nenhum deles.
- **Emissão por decisão judicial.** O manual de 26/01/2026 confirma o que o README diz:
  o emissor envia a NFS-e completa, com `nNFSe` e `cStat` 102, e não uma DPS.

## Não foi possível conferir

- **Swaggers atuais.** As páginas de documentação das APIs (`sefin.nfse.gov.br`,
  `adn.nfse.gov.br`) exigem certificado de cliente. Os quatro arquivos embutidos em
  `storage/schemes/` não foram comparados com os atuais.
- **Aceitação pela SEFIN.** Nada do leiaute `nt009` foi enviado. Seguem em aberto as
  dúvidas já registradas no desenho da NT 009, como a aceitação de `gIBSCBS` vazio.
- **Regras de negócio do Anexo VI** para os grupos de B. Foram lidas só as que o
  leiaute `nt009` atual já toca. Cada item de B pede a leitura das suas ao ser
  implementado.
