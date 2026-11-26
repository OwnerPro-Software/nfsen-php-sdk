# Leiaute da NT 009 selecionável — design

Data: 2026-11-26. Origem: a NT 009 v1.01 muda a estrutura da DPS e não há data
de produção. Complementa `2026-11-26-cnpj-alfanumerico-design.md`, que deixou a
migração de leiaute fora de escopo.

## Intenção

A Produção Restrita já valida a DPS por um leiaute que recusa o XML que o SDK
monta hoje, e a Produção recusa o XML do leiaute novo. Os dois se chamam `1.01`.
No dia em que a Produção virar, toda emissão para até sair um release, e não
há como ensaiar o leiaute novo contra a SEFIN antes disso.

O SDK passa a carregar os dois leiautes e a escolher por configuração, com o de
produção como padrão. No dia da virada o consumidor muda uma configuração.

## Objetivos

- Emitir a DPS no leiaute do pacote `esquemas-xsd-producao-restrita-20261124.zip`
  quando configurado, validada localmente pelo XSD desse pacote.
- A mesma entrada (`DpsData` ou array) serve aos dois leiautes no núcleo da
  DPS: `infDPS`, `prest`, `toma`, `subst`, `serv/cServ`, `valores`
  (`tribMun`, `tribFed`) e `IBSCBS` com `finNFSe`, `indFinal`, `cIndOp`,
  `indDest`, `CST`, `cClassTrib`.
- Recusar antes do envio, com mensagem clara, o que a entrada não consegue
  expressar no leiaute escolhido.
- O DANFSe lê a NFS-e nos dois leiautes, sem configuração: o documento diz em
  qual está.

## Fora de escopo

- Campos do Simples Nacional do item 2.4 (`opSimpNac` 4, `regApIBSCBSSN`,
  `cAtvSN`): não estão em nenhum XSD publicado. Entram quando o pacote sair.
- O que a NT 009 descreve e o XSD de 24/11 ainda não traz: notas de ajuste
  (`finNFSe` 1 e 2, `tpNFSeDebito`, `tpNFSeCredito`, `gIBSCBSAjuste`, item 2.2) e
  `gPgtoVinc` (item 2.8).
- A forma nova dos grupos reestruturados: `vAjusteBC/documentos` (item 2.3),
  `imovel` (2.6), `bensMoveis` (2.7), `comExt` (2.12), `vAjusteBCIBSCBSComExt`
  (2.13), `atvEvento/cMun` (2.14), `condominios` (2.15). Entrega seguinte.
- Cancelamento e eventos: `evento`, `pedRegEvento` e `tiposEventos` são
  idênticos nos dois pacotes.
- Imprimir no DANFSe o `verCalcIBSCBS`, único campo novo da NFS-e que o pacote
  traz.
- Remover o leiaute de produção.

## Anti-objetivos

- Mudar um byte do XML que o SDK envia hoje com a configuração padrão.
- Seguir a NT onde o XSD publicado diverge dela. Quem recusa o XML é o XSD.
- Amarrar o leiaute ao ambiente: obrigaria a um release do SDK no dia da virada.

## Restrições e fontes

Tudo abaixo foi lido nos artefatos; **nada foi enviado à SEFIN**.

- Pacote de Produção Restrita de 24/11/2026, sha256
  `1ca83c1a7929f6d5684c9a17700fe3d3a375e4ea12b7ccb832bfc369768902c0`. Diferença
  para `storage/schemes/` em `tiposComplexos_v1.01.xsd` (tipo a tipo, abaixo),
  `tiposSimples_v1.01.xsd` e `xmldsig-core-schema.xsd` (perde o `DOCTYPE`).
- `TCInfDPS`: `finNFSe` (1-1) entra depois de `cMotivoEmisTI` e antes de
  `chNFSeRej`; `indDest` (1-1) e `dest` (0-1) entram depois de `toma` e antes de
  `interm`. `TCRTCInfoDest` não mudou.
- `TCRTCInfoIBSCBS`: perde `finNFSe`, `indDest` e `dest`; `cIndOp` vira 0-1;
  ganha `bensMoveis` e `condominios` depois de `imovel`.
- `TCCServ`: `xDescServ` sai de depois de `cTribMun` e vai para o fim, depois de
  `cIntContrib`.
- `valores/trib` do IBSCBS (`TCRTCInfoTribIBSCBS`): `CST`, `cClassTrib`, e
  `gIBSCBS` (0-1) só com `cCredPres`, `gTribRegular`, `gDif`.
  `TCRTCInfoValoresIBSCBS` perde `gReeRepRes`.
- `TCInfoValores`: `vDedRed` dá lugar a `vAjusteBC`, cuja escolha é
  `pAjusteBCISSQN` | `vAjusteBCISSQN` (0-1), seguida de `documentos` e
  `vAjusteBCIBSCBSComExt`.
- `TCComExterior` perde `vServMoeda`, `nDI`, `nRE` e ganha `nFatura`,
  `vFatura`, `nDUIMP`, `nDUE`, `nContCambio`. `TCRTCInfoImovel` foi refeito
  (`cMun` 1-1, `gLocacao`, `gUnidImob`).
- `TCInfoPessoa/xNome` vira 0-1: tomador e intermediário podem vir sem nome.
- Anexo VI, aba de repercussão dos ajustes: os tipos de documento 101 a 104 e
  199 ajustam a base do ISSQN **e** a do IBS/CBS; `vCalcAjusteBCISSQN` e
  `vCalcAjusteBCIBSCBS` podem conter o mesmo documento. Regra 57 (E1295):
  `vBC = vServ - vDescIncond - (vAjusteBCISSQN + vCalcAjusteBCISSQN) - …`, e o
  valor declarado pode vir junto com documentos. Item 2.3 da NT: a base do
  IBS/CBS desconta `vCalcAjusteBCIBSCBS` ou `vCalcAjusteBCLocImoveis`.
- Na NFS-e (`TCValoresNFSe`, `TCRTCValoresIBSCBS`, `TCRTCIBSCBS`,
  `TCRTCTotalCIBS`): `vCalcDR` vira `vCalcAjusteBCISSQN`; `vCalcReeRepRes` dá
  lugar a `vCalcAjusteBCIBSCBS` e `vCalcAjusteBCLocImoveis`; `valores`, `vBC`,
  `gIBS`, `gCBS`, `cLocalidadeIncid` e `xLocalidadeIncid` viram 0-1.
- `TSRTCFinNFSe` no pacote aceita só `0`, embora o item 2.2 da NT descreva 0, 1
  e 2.
- Anexo VI v1.04.01, aba RN DPS: regra 368 (E0908) proíbe `dest` com
  `indDest = 0`; regra 781 (E0960) exige `gIBSCBS` quando o `cClassTrib` indica
  tributação (`ind_gIBSCBS = 1`); regra 694 (E0859) proíbe `cIndOp` quando não
  indica. As regras 695 (E0860) e 782 (E0961) são as inversas: `cIndOp` e
  `gIBSCBS` andam sempre juntos.
- Página "Atualizações e Implantações" (atualizada em 25/11/2026 17h28): as
  entregas da NT 009 de 20/10 e 25/11 constam só na coluna Produção Restrita.
- No uso comum, `finNFSe = 0` e `indDest = 0` são enviados fixos, e há DPS
  emitida sem o grupo `IBSCBS`.

## Abordagens

1. **Dois leiautes no SDK, escolhidos por configuração, com a mesma entrada.
   Escolhida.** Produção não muda; o leiaute novo é validado pelo XSD oficial
   agora; a virada é uma configuração. Custo: dois caminhos de montagem até o
   leiaute antigo sair, e o novo vai para produção sem ter sido enviado antes.
2. Esperar a data de produção. Junta todo o trabalho numa janela que ninguém
   conhece, com a emissão parada.
3. Trocar o leiaute embarcado. Quebra a emissão de produção hoje.
4. Uma árvore de DTOs por leiaute. O consumidor reescreveria a montagem do
   payload no dia da virada, que é o que a configuração existe para evitar.

## Design

### Seleção do leiaute

Enum `Enums\Leiaute`, `@api`:

| Caso | Valor | Leiaute |
|---|---|---|
| `V101` | `v1.01` | pacote de produção `esquemas-xsd-v1.01-20260209` (padrão) |
| `Nt009` | `nt009` | pacote de Produção Restrita de 24/11/2026 |

- `config/nfsen.php`: `'leiaute' => env('NFSE_LEIAUTE', 'v1.01')`.
- `NfsenClient::for(..., ?Leiaute $leiaute = null)`: `null` segue a
  configuração; sem Laravel, `V101`. Mesmo desenho de `ambiente` e `danfse`.
- `NfsenClient::forStandalone(..., Leiaute $leiaute = Leiaute::V101)`.
- O cliente resolvido pelo container (`NfsenServiceProvider`) e pela facade
  segue a mesma configuração.
- Valor de configuração desconhecido falha na construção do cliente, como
  `NfseAmbiente::fromConfig()` faz hoje.
- O atributo `versao` da DPS é `1.01` nos dois.

### XSD

`storage/schemes/` continua sendo o leiaute de produção, sem mover arquivo.
`storage/schemes/nt009/` recebe do pacote de 24/11: `DPS_v1.01.xsd`,
`NFSe_v1.01.xsd`, `tiposComplexos_v1.01.xsd`, `tiposSimples_v1.01.xsd` e
`xmldsig-core-schema.xsd`.

A cópia é fiel ao pacote, salvo as quatro divergências deliberadas que o
`tiposSimples` embarcado já carrega e pelas mesmas razões (`TSChaveNFSe`,
`TSIdPedRegEvt`, `TSIdEvento`, `TSIdNumEvento`), marcadas do mesmo jeito.

O diretório vai no pacote dist. Com `schemasPath` informado em
`forStandalone()`, o leiaute `Nt009` é lido de `<schemasPath>/nt009`.

### Emissão

Só muda o XML quando o leiaute é `Nt009`:

| Entrada | XML no leiaute `Nt009` |
|---|---|
| `IBSCBS.finNFSe` | `infDPS/finNFSe`. Sem grupo `IBSCBS`: `0` |
| `IBSCBS.indDest`, `IBSCBS.dest` | `infDPS/indDest` e `infDPS/dest`. Sem grupo `IBSCBS`: `indDest = 0`, sem `dest` |
| `cServ.xDescServ` | último filho de `cServ` |
| `trib.gIBSCBS.CST`, `.cClassTrib` | `IBSCBS/valores/trib/CST` e `/cClassTrib` |
| `trib.gIBSCBS` (`cCredPres`, `gTribRegular`, `gDif`) | `trib/gIBSCBS`, emitido sempre que `cIndOp` é informado, vazio se nenhum dos três veio |
| `IBSCBS.cIndOp` ausente (acrescentado em 26/11 a pedido do usuário) | `cIndOp` e `gIBSCBS` omitidos; `gIBSCBS` com conteúdo ainda é emitido. No `V101`: recusado, `IBSCBS/cIndOp é obrigatório no leiaute v1.01.` |
| `vDedRed.pDR`, `vDedRed.vDR` | `valores/vAjusteBC/pAjusteBCISSQN`, `/vAjusteBCISSQN` |
| `vDedRed.documentos` | recusado |
| `IBSCBS.valores.gReeRepRes` | recusado |
| `IBSCBS.imovel` | recusado |
| `serv.comExt` | recusado |
| todo o resto | igual ao leiaute `V101` |

Recusa é `InvalidDpsArgument` antes da validação XSD, nomeando o grupo e o
leiaute, em pt-BR. Exemplo: `O grupo comExt ainda não é suportado no leiaute da
NT 009.`

Restrições de estrutura:

- O XML do leiaute `V101` não muda. Um teste que trava byte a byte o XML de uma
  DPS com todos os grupos é escrito e passa **antes** de qualquer refatoração.
  Com ele no lugar, o código do `V101` pode ser refatorado para compartilhar
  com o `Nt009` as partes de XML idêntico; duplicar os builders inteiros não é
  o caminho.
- A escolha acontece na raiz de montagem (`NfsenClient`). `Operations/` e
  `Pipeline/` não perguntam qual é o leiaute.
- Remover o `V101` depois deve ser apagar código, não cirurgia.

### Leitura (DANFSe)

`DanfseDataBuilder` lê cada valor da posição nova e, se ausente, da antiga:

| Campo impresso | Leiaute `V101` | Leiaute `Nt009` |
|---|---|---|
| Finalidade | `infDPS/IBSCBS/finNFSe` | `infDPS/finNFSe` |
| Destinatário | `infDPS/IBSCBS/indDest`, `/dest` | `infDPS/indDest`, `/dest` |
| CST, cClassTrib | `…/trib/gIBSCBS/CST`, `/cClassTrib` | `…/trib/CST`, `/cClassTrib` |

Dois campos somados mudam de fórmula, e não só de tag. As tags dos dois
leiautes são disjuntas, então a presença delas decide a fórmula:

| Campo impresso | Leiaute `V101` (sem mudança) | Leiaute `Nt009` |
|---|---|---|
| Total deduções/reduções (bloco do ISSQN) | `vDR`, senão `vCalcDR + vCalcReeRepRes` | `vAjusteBCISSQN + vCalcAjusteBCISSQN` |
| Exclusões e reduções da BC (bloco IBS/CBS) | `vDescIncond + vCalcReeRepRes + vISSQN + vPis + vCofins` | `vDescIncond + vCalcAjusteBCIBSCBS + vCalcAjusteBCLocImoveis + vISSQN + vPis + vCofins` |

No leiaute novo o total do bloco do ISSQN é soma, porque valor declarado e
documentos podem vir juntos, e não leva `vCalcAjusteBCIBSCBS`: os documentos
101 a 104 e 199 já estão em `vCalcAjusteBCISSQN`, e somar os dois contaria o
mesmo documento duas vezes. `vCalcAjusteBCIBSCBS` e `vCalcAjusteBCLocImoveis`
nunca vêm juntos (a NT os liga por "ou"), então somá-los equivale a pegar o
presente.

A NT 008 nomeia as tags antigas e não foi atualizada. As fórmulas do leiaute
novo vêm da regra 57 e do item 2.3 da NT 009 e são inferência: fica dito assim
no ponto do código, numa linha.

Tomador ou intermediário sem `xNome` e NFS-e sem `valores`, `vBC`, `gIBS` ou
`gCBS` imprimem traço nos campos correspondentes.

Fixture nova `tests/fixtures/danfse/nfse-ibscbs-nt009.xml`, escrita à mão (não
existe NFS-e real nesse leiaute ao nosso alcance) e submetida a
`nt009/NFSe_v1.01.xsd` por `DanfseFixtureSchemaTest`.

Lacuna conhecida: `Insc. Imob.` de uma NFS-e com o `imovel` novo sai com traço.

### Documentação

- README: seção "Leiaute da NT 009" — como escolher, o que é recusado, o que
  não foi testado contra a SEFIN. Atualiza o parágrafo que hoje diz que o
  leiaute não foi adotado.
- CHANGELOG `[3.0.0]`.

## Riscos

- **Nenhum envio real.** O XSD local cobre a recusa por schema; as regras de
  negócio da SEFIN só aparecem em produção.
- **`<gIBSCBS/>` vazio.** A regra 781 exige o grupo quando há tributação, e o
  XSD aceita o elemento vazio. Não sabemos se a SEFIN conta o vazio como
  informado.
- **Padrões sem grupo `IBSCBS`.** `finNFSe = 0` e `indDest = 0` são os valores
  do caso regular, mas o SDK passa a afirmá-los em nome de quem omitiu o
  grupo.
- **Classificação sem tributação depende de quem chama.** O critério é o
  `ind_gIBSCBS` da tabela de classificação (a NT cita os CST 400, 410 e 820
  em particular). O SDK não conhece a tabela; se o consumidor enviar `cIndOp`
  nesses casos no leiaute novo, as regras 694 e 782 recusam a nota.
- **O leiaute ainda se move.** Duas entregas em cinco semanas na Produção
  Restrita.

## Minhas escolhas (não decididas pelo usuário)

- Nome e casos do enum, chave `leiaute`, `NFSE_LEIAUTE` e o parâmetro
  `leiaute:`, em português como `ambiente`.
- `finNFSe = 0` e `indDest = 0` quando a entrada não traz `IBSCBS`.
- `<gIBSCBS/>` sempre emitido no leiaute novo.
- `vDedRed.pDR`/`vDR` traduzidos para `pAjusteBCISSQN`/`vAjusteBCISSQN`; os
  quatro grupos reestruturados recusados nesta entrega.
- DANFSe nos dois leiautes dentro desta entrega, e não numa seguinte: sem
  isso, a nota emitida no dia da virada sai impressa sem finalidade, CST e
  classificação.
- As quatro divergências de `tiposSimples` repetidas no diretório `nt009`.
- O código do `V101` pode ser refatorado sob o teste byte a byte.
- As duas fórmulas do DANFSe no leiaute novo, em especial deixar
  `vCalcAjusteBCIBSCBS` fora do total do bloco do ISSQN.

## Para quem implementa

Ordem sugerida: seleção e XSD; emissão; leitura; documentação. Testes pela API
pública (`NfsenClient::forStandalone(leiaute: …)->emitir()`, `danfse()`), TDD.
Todo XML montado em teste no leiaute novo passa por `nt009/DPS_v1.01.xsd`.
Reaproveitar os certificados de `tests/fixtures/certs/`. Rodar todas as
verificações do `CLAUDE.md` ao final.
