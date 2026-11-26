# Notas técnicas da NFS-e na reforma tributária: situação no SDK

**Levantado em 26/11/2026**, sobre a `v3.0.0-rc6` (`135397a`).

A Secretaria-Executiva do Comitê Gestor da NFS-e (SE/CGNFS-e) publicou dez notas
técnicas desde a Emenda Constitucional nº 132/2023. Este documento lista cada uma, com
a data, o ambiente em que está valendo e o que o SDK implementa dela.

## Como ler

- **Data** é a que a própria nota traz na capa. Onde o portal mostra outra, as duas
  aparecem.
- **Implementada** quer dizer: o SDK monta o XML que o XSD publicado aceita, ou lê o
  campo no DANFSe. O SDK segue o XSD, e não o texto da nota. Campo que a nota descreve
  e nenhum XSD traz não foi implementado, porque a validação local o recusaria.
- O SDK não replica as regras de negócio da SEFIN (as rejeições `E....`). Regra nova
  sem campo novo não exige mudança aqui.
- Nada do leiaute `nt009` foi exercitado contra a SEFIN. A conferência é só por XSD.

## Resumo

| NT | Versão | Data | Assunto | Onde vale | Situação no SDK |
|---|---|---|---|---|---|
| 001 | 1.0 | 01/08/2024 | Primeira versão dos grupos de IBS, CBS e Imposto Seletivo | Substituída | Nada a implementar |
| 002 | 1.0 | 28/02/2025 | Segunda versão dos grupos, já sem Imposto Seletivo | Substituída | Nada a implementar |
| 003 | 1.2 | 04/07/2025 | Terceira versão: `gIBSCBS`, `cIndOp`, `imovel`, `gReeRepRes` | Substituída | Nada a implementar |
| 004 | 2.0 | 10/12/2025 (v1.1: 19/08/2025) | Grupo `IBSCBS` da DPS e da NFS-e | Produção desde 05/01/2026 | **Implementada** — é o leiaute padrão (`v1.01`) |
| 005 | 1.1 | 19/11/2025 | Novos fatos geradores, locação de bens móveis, doação, estorno de crédito | Fora de produção; parte chegou à Produção Restrita pela NT 009 | **Não implementada** |
| 006 | — | 22/01/2026 | Leiaute da NFS-e Via (pedágio) | — | Fora do escopo |
| 007 | 1.0 | 07/02/2026 | `indZFMALC`, domínios de PIS/COFINS, esclarecimentos | PIS/COFINS em produção desde 09/02/2026 | **Parcial** — PIS/COFINS sim; `indZFMALC` não está em nenhum XSD |
| 008 | 1.02 | 14/07/2026 (v1.0: 05/05/2026; v1.01: 30/06/2026) | Especificação do DANFSe | Produção; a API de DANFSe do ADN saiu do ar em 03/08/2026 | **Implementada**: construída sobre a v1.0 e atualizada para a v1.02 depois deste levantamento |
| 009 | 1.01 | 11/09/2026 (o portal mostra 01/10/2026) | Reestruturação da DPS, Simples Nacional, notas de ajuste, condomínios | Produção Restrita, por partes; produção sem data | **Parcial** — leiaute opcional `nt009` |
| 010 | 1.00 | 11/09/2026 | NFS-e Via v1.01 e evento de Manifestação do Adquirente | — | Fora do escopo |

## O que falta, por ordem de urgência

Este documento é só o levantamento. Na data dele, nenhuma das lacunas abaixo estava
corrigida; a primeira foi corrigida em seguida. O que cada uma
exige do código está em [`adequacoes-pendentes.md`](adequacoes-pendentes.md).

1. ~~**NT 008 v1.02: PIS/COFINS retidos no DANFSe.**~~ Era a única lacuna que atingia a
   produção. Corrigida depois deste levantamento.
2. **Grupos da NT 009 que o leiaute `nt009` ainda não monta**: `imovel`, `comExt`,
   `vAjusteBC/documentos`, `vAjusteBCIBSCBSComExt`, `bensMoveis`, `condominios` e
   `atvEvento/cMun`. Só passam a importar quando a produção adotar o leiaute da NT 009,
   o que não tem data.
3. **À espera de XSD**: Simples Nacional (NT 009, item 2.4), notas de crédito e de
   débito (item 2.2), `gPgtoVinc` (item 2.8), `indZFMALC` (NT 007) e o que restou da
   NT 005 (`indDoacao`, `gEstornoCred`).

## Linha do tempo das implantações

Da página "Atualizações e Implantações" do portal, só o que toca o SDK.

| Data | Ambiente | O que entrou |
|---|---|---|
| 10/12/2025 | Produção Restrita | Grupo `IBSCBS` (NT 004 v2.0) |
| 05/01/2026 | Produção | Grupo `IBSCBS` (NT 004 v2.0) |
| 09/02/2026 | Produção e Produção Restrita | Domínios de PIS/COFINS (NT 007, item 2.c). É a data do pacote `esquemas-xsd-v1.01-20260209`, ainda o de produção |
| 24/04/2026 | Produção | Códigos de mensagem renumerados (`RNG6110` → `E1235`, `E6151` → `E1634` e outros) e regra `E1000` para erros da calculadora |
| 03/08/2026 | Produção | DANFSe do portal passa a seguir a NT 008; API de DANFSe do ADN v1.0 desativada |
| 10/08/2026 | Produção | CNPJ alfanumérico |
| 20/10/2026 | Produção Restrita | `CST` e `cClassTrib` reposicionados (NT 009, 2.16); códigos 99.02 a 99.04 (2.15); grupo `vAjusteBC` (2.3) |
| 25/11/2026 | Produção Restrita | `verCalcIBSCBS` (2.9); validações de endereço do subitem 17.05 (2.10); `dest` em `infDPS` (2.11); comércio exterior (2.12 e 2.13); eventos (2.14); nota emitida pelo tomador ou intermediário na importação de serviços |

## Nota por nota

### NT 001, 002 e 003 — versões preliminares

Três rascunhos sucessivos dos grupos de IBS e CBS. As NT 003 e 004 dizem, na introdução,
que substituem as versões anteriores. Nenhuma delas chegou a ambiente algum na forma em
que foi publicada.

O portal só guarda a última versão de cada uma. As datas das versões 1.0 e 1.1 da NT 003
não foram levantadas.

### NT 004 — grupo `IBSCBS` (implementada)

A v1.1, de 19/08/2025, fixou o leiaute. A v2.0, de 10/12/2025, manteve os campos e
registrou a implantação: Produção Restrita em 10/12/2025 e produção em 05/01/2026.

É o leiaute padrão do SDK. Os XSD em `storage/schemes/` são os do pacote
`esquemas-xsd-v1.01-20260209`, com as divergências de CNPJ alfanumérico descritas no
README. O tipo `TCInfDPS` desse pacote tem 313 caminhos, e os builders montam todos.

Os valores que a SEFIN calcula e devolve na NFS-e (`vDifUF`, `vCredPresIBS`,
`vTribRegCBS`, entre outros) o SDK não monta. O DANFSe imprime os que a NT 008 lista.

### NT 005 — novos fatos geradores (não implementada)

A própria nota avisa que não entraria em produção em janeiro de 2026. O que ela trouxe
teve três destinos:

| Item da NT 005 | Destino | No SDK |
|---|---|---|
| Códigos `cTribNac` 99.02.01, 99.03.01 a 99.03.05 e 99.04.01 | Produção Restrita em 20/10/2026 | Nada a fazer: o XSD aceita qualquer código de seis dígitos |
| `gLocBensMoveis` | Renomeado para `bensMoveis` pela NT 009 (2.7); está no XSD de 24/11/2026 | Não montado |
| `gDedRedIBSCBS` | Renomeado para `gAjusteBCLocImoveis` pela NT 009 (2.6); está no XSD de 24/11/2026 | Não montado |
| `indDoacao`, `gEstornoCred`, `gPagAntecipado` | Em nenhum XSD publicado | Não implementado |

### NT 006 e NT 010 — NFS-e Via (fora do escopo)

A NFS-e Via é outro documento fiscal, de uso restrito às concessionárias de rodovias,
com ambiente nacional, leiaute e APIs próprios. O SDK não a emite nem a lê.

### NT 007 — `indZFMALC` e PIS/COFINS (parcial)

| Item | Situação no SDK |
|---|---|
| 2.a — campo `IBSCBS/indZFMALC` (alíquota zero de CBS, arts. 451 e 466 da LC 214/2025) | **Não implementado.** O campo está no Anexo VI v1.04.01 e não está no XSD de produção (09/02/2026) nem no da Produção Restrita (24/11/2026) |
| 2.b — nova tabela de `cIndOp` (Anexo VII) | Nada a fazer: o SDK não embute a tabela |
| 2.c — `CST` de PIS/COFINS com o domínio de 00 a 99 | Implementado (`Dps\Enums\Valores\CST`) |
| 2.c — `tpRetPisCofins` com os códigos 0 e 3 a 9 | Implementado (`TpRetPisCofins`). Os códigos 1 e 2 seguem aceitos e a nota avisa que sairão do schema quando o grupo `IBSCBS` for obrigatório |
| 2.c — retenções de PIS, COFINS e CSLL somadas em `vRetCSLL`; arredondamento e tolerância de R$ 0,01 | Regra de preenchimento e de validação da SEFIN. O SDK envia o que recebe |
| 3 — esclarecimentos (novos fatos geradores, saltos na numeração da NFS-e, NFS-e Via) | Sem campo novo |

### NT 008 — DANFSe (implementada)

O SDK foi construído e auditado contra a v1.0, de 05/05/2026. Na data deste
levantamento, o PDF em `storage/danfse/` e a tabela de campos de
`tests/fixtures/nt008/campos-2.4.5.json` eram os dessa versão. Os dois foram trocados
pelos da v1.02 em seguida.

O portal publica a v1.02. Comparando o texto das duas versões:

| Mudança | Versão | No SDK |
|---|---|---|
| Data de suspensão da API de DANFSe: de 01/07/2026 para 03/08/2026 | 1.01 e 1.02 | **Corrigido depois deste levantamento.** O README e o aviso `DanfseResponse::API_SOBRESTADA` citavam 01/07/2026 |
| A quantidade de caracteres do item 2.4.5 deixa de ser obrigatória | 1.02 | Sem efeito: o SDK segue os tamanhos sugeridos |
| **Com `tpRetPisCofins` = 1, "Contribuições Sociais - Retidas" passa a ser `vRetCSLL` + `vPis` + `vCofins`, e "PIS" e "COFINS - Débito Apuração Própria" passam a `0,00`** | 1.02 | **Implementado depois deste levantamento.** Na data dele, `DanfseDataBuilder::buildTribFed()` imprimia os três campos como vinham, qualquer que fosse o `tpRetPisCofins` |
| Correções de grafia ("DESTINATÁRIO", "INTERMEDIÁRIO", "BASE DE CÁLCULO", "IDENTIFICAÇÃO") | 1.02 | Sem efeito: o template já trazia a grafia correta nos três primeiros, e o quarto fica no canhoto, que é opcional e o SDK não imprime |

As coordenadas e os tamanhos de campo do item 2.4.5 são os mesmos nas duas versões.

A v1.02 só altera os três campos de `tribFed`. A soma de "Total das Retenções" que o SDK
refaz quando a NFS-e vem sem `vTotalRet` ficou como estava: usa `vRetCSLL` e não soma
`vPis` nem `vCofins`, que é a fórmula da regra E1506 do Anexo VI.

A nota anuncia outra, ainda não publicada, com o DANFSe dos novos fatos geradores.

### NT 009 — reestruturação da DPS (parcial)

O SDK traz o leiaute da NT 009 como opção (`NFSE_LEIAUTE=nt009`), validado pelo pacote
`esquemas-xsd-producao-restrita-20261124`, em `storage/schemes/nt009/`. O padrão
continua sendo o de produção. O README descreve a opção em "Leiaute da NT 009".

O `TCInfDPS` do pacote de 24/11/2026 tem 319 caminhos. Ao menos 40 deles o SDK não
monta: são os grupos listados abaixo como recusados ou não montados.

| Item | Assunto | No XSD de 24/11/2026 | No leiaute `nt009` do SDK |
|---|---|---|---|
| 2.1 | CNPJ alfanumérico | Sim | **Implementado**, nos dois leiautes |
| 2.2 | Notas de ajuste: `finNFSe` 1 e 2, `tpNFSeCredito`, `tpNFSeDebito` | Não: `finNFSe` só aceita 0 | Não implementado. O `finNFSe` é emitido na posição nova, em `infDPS`, com valor 0 |
| 2.3 | `vAjusteBC` no lugar de `vDedRed` e `gReeRepRes` | Sim | **Parcial**: `pAjusteBCISSQN` e `vAjusteBCISSQN`. `documentos` e `gReeRepRes` são recusados com erro |
| 2.4 | Simples Nacional: `opSimpNac` 4, `regApIBSCBSSN`, `cAtvSN`, `gTribSN` | Não | Não implementado |
| 2.5 | Campo `indFinal` | Sim | **Implementado** |
| 2.6 | Bens imóveis: `gLocacao`, `gUnidImob`, `gAjusteBCLocImoveis` | Sim | Recusado com erro |
| 2.7 | `bensMoveis` | Sim | Não montado |
| 2.8 | `gPgtoVinc` | Não | Não implementado |
| 2.9 | `verCalcIBSCBS` na NFS-e | Sim | Campo de resposta. O SDK não o lê, e a NT 008 não o imprime |
| 2.10 | Validação de endereços no subitem 17.05 | Regra, sem campo | Regra da SEFIN |
| 2.11 | `indDest` e `dest` em `infDPS` | Sim | **Implementado** |
| 2.12 | Comércio exterior: `nDUE`, `nDUIMP`, `nContCambio`, `nFatura`, `vFatura` | Sim | Recusado com erro |
| 2.13 | `vAjusteBCIBSCBSComExt` | Sim | Não montado |
| 2.14 | Eventos: município do evento (`atvEvento/cMun`) | Sim | **Parcial**: o grupo é montado sem o `cMun`, que é opcional |
| 2.15 | Grupo `condominios` e novos códigos de tributação | Sim | Grupo não montado. Os códigos passam |
| 2.16 | `CST` e `cClassTrib` em `trib`; `cIndOp` e `gIBSCBS` opcionais | Sim | **Implementado** |

O DANFSe lê a NFS-e dos dois leiautes sem configuração.

## Fontes

Portal da NFS-e, lido em 26/11/2026:

- [Documentação técnica](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica),
  seções "RTC", "Documentação Atual (Produção)" e "Documentação Técnica
  (Homologação/testes)".
- [Atualizações e Implantações](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/atualizacoes-e-implantacoes):
  é a página que mostra, por ambiente, quando cada entrega entrou.
- [NFS-e Via](https://www.gov.br/nfse/pt-br/nfs-e-via/documentacao-tecnica), para as
  NT 006 e 010.

Em 26/11/2026 os links do portal para a NT 004 v2.0, para o Anexo VI v1.01.03 e para o
pacote de XSD de 10/12/2025 levavam à tela de login. A NT 004 v2.0 foi lida da cópia do
Wayback Machine de 18/02/2026. A v1.1 segue pública em
`.../documentacao-tecnica/rtc/nt-004-se-cgnfse-novo-layout-rtc.pdf`.

Os dois pacotes de XSD foram baixados de novo e conferidos: o de produção continua
sendo o `esquemas-xsd-v1.01-20260209`, e o da Produção Restrita, o
`esquemas-xsd-producao-restrita-20261124`.

## Como foi conferido

- Datas e versões: capa de cada PDF.
- Campos: cada nome de campo citado numa nota foi procurado nos XSD de
  `storage/schemes/`, nos de `storage/schemes/nt009/` e em `src/`.
- Cobertura da DPS: percurso do tipo `TCInfDPS` em cada pacote de XSD, conferindo se
  cada elemento tem builder em `src/Xml/`. A conferência é por nome de elemento, então
  a contagem do que falta no `nt009` é um piso.
- NT 008: comparação do texto da v1.0 com o da v1.02.

## Quando refazer

Ao sair nota técnica nova, versão nova de uma existente, pacote de XSD novo ou entrada
nova em "Atualizações e Implantações" na coluna de produção.
