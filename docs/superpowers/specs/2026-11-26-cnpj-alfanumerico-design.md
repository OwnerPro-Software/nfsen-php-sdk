# CNPJ alfanumérico — design

Data: 2026-11-26. Origem: pedido de mudança do consumidor (lido na v3.0.0-rc4).

## Intenção

A Receita emite CNPJ alfanumérico desde 31/07/2026. O SDK recusa um CNPJ com
letras antes de qualquer envio (Id da DPS, XSD local, chave de acesso) e o DANFSe
apaga as letras ao formatar. A emissão da primeira empresa com CNPJ alfanumérico,
como prestadora ou tomadora, para sem contorno possível do lado do consumidor.

## Objetivos

- Aceitar CNPJ alfanumérico em todos os campos de CNPJ, no Id da DPS, na chave de
  acesso e nos Ids de evento.
- Manter o rigor do `DpsId`: 14 posições exatas, sem máscara, sem minúsculas, sem
  completar com zeros.
- DANFSe imprime o CNPJ com letras e máscara de CNPJ.
- Dizer ao consumidor o contrato: CNPJ sem máscara e em maiúsculas.

## Fora de escopo

- Migrar para o leiaute da NT 009 (`vAjusteBC`, condomínios, comércio exterior).
  É projeto separado; produção não tem data.
- Validar dígito verificador de CNPJ.
- Atualizar swaggers e XSD v1.00.

## Anti-objetivo

Quebrar a emissão que funciona hoje em produção. Por isso o leiaute embarcado
continua sendo o de produção.

## Restrições e fontes

- Produção publica `esquemas-xsd-v1.01-20260209.zip`, com `TSCNPJ = [0-9]{14}`. O
  repositório é idêntico a ele, salvo as âncoras `^`/`$` do `TSSerieDPS`.
- Produção Restrita publica `esquemas-xsd-producao-restrita-20261124.zip`, com os
  padrões alfanuméricos e o leiaute da NT 009 v1.01 juntos.
- A página "Atualizações e Implantações" lista "Evolução para tratamento do CNPJ
  alfanumérico" na coluna Produção em 10/08/2026. NT 009, item 2.1: todos os
  campos CNPJ passam de numérico para caractere.
- Existiu um pacote intermediário de 27/07/2026 (produção + CNPJ alfanumérico),
  hoje "Conteúdo Restrito" no portal. Uma cópia de terceiros difere do pacote de
  produção apenas em `tiposSimples_v1.01.xsd`, nas mesmas linhas listadas abaixo.
- Nada foi testado contra a SEFIN.

## Abordagens

1. **Portar para o XSD atual só as linhas de `tiposSimples` que o pacote de 24/11
   muda sem depender do leiaute novo. Escolhida.** Resolve agora e não toca o
   leiaute de produção. Custo: o XSD embarcado deixa de ser cópia de um pacote
   publicado hoje.
2. Adotar o pacote de 24/11 inteiro. Remove `vDedRed`, `gReeRepRes`, `vServMoeda`,
   `nDI`, `nRE`, que os builders emitem. Quebra a emissão atual.
3. Mexer só no PHP. A validação XSD local continua recusando o CNPJ.

## Design

### `storage/schemes/tiposSimples_v1.01.xsd`

Copiar do pacote oficial de 24/11:

| Tipo | Padrão |
|---|---|
| `TSCNPJ` | `[0-9A-Z]{14}` |
| `TSIdDPS` | `DPS[0-9]{7}(1[0-9]{14}\|2[0-9A-Z]{14})[0-9]{20}` |
| `TSIdNFSe` | `NFS[0-9]{9}[0-9A-Z]{14}[0-9]{27}` |
| `TSChaveNFe` | `[0-9]{6}([0-9A-Z]{14})[0-9]{24}` |
| `TSSerieDPS` | `[0-9]{1,4}\|[0-8][0-9]{4}` |
| `TSDescInfCompl`, `TSDesc40/150/255/500/600/1000/2000` | acrescenta `[\s\S]*[^\s][\s\S]*` |
| data/hora (linha 146) | `0[1,3-9]` vira `0[13-9]` |

**Divergência deliberada: `TSChaveNFSe`.** O oficial traz
`[0-9]{6}([0-9A-Z]{14})[0-9]{30}`, que admite letras nas posições 7 a 20. A
inscrição federal fica nas posições 10 a 23 (regra de formação do `TSIdNFSe`:
Cód.Mun. 7 + Amb.Ger. 1 + Tipo 1 + Inscrição 14). O exemplo da Receita,
`12ABC34501DE35`, seria recusado em `chNFSe` e `chSubstda`. O embarcado usa
`[0-9]{9}[0-9A-Z]{14}[0-9]{27}`, o `TSIdNFSe` sem o prefixo. Um comentário de uma
linha no XSD e um teste registram a decisão.

**Divergência deliberada: Ids de evento.** O oficial exige tipo de inscrição 1 ou
2 na 9ª posição da chave (`PRE[0-9]{8}(1[0-9]{14}|2[0-9A-Z]{14})[0-9]{33}` e
análogos). O embarcado usa `PRE[0-9]{9}[0-9A-Z]{14}[0-9]{33}`,
`EVT[0-9]{9}[0-9A-Z]{14}[0-9]{36}` e, em `TSIdNumEvento`,
`[0-9]{9}[0-9A-Z]{14}[0-9]{33}([0-9]{3})?` — o oficial reduz esse tipo de 59 para
56 posições, e o embarcado aceita as duas larguras. Decidido na
implementação: o padrão oficial recusaria localmente o cancelamento de uma chave
numérica hoje aceita, e não há evidência de que toda chave real tenha 1 ou 2 ali.
Se errado, o Id malformado segue para a SEFIN e é rejeitado lá.

### `Support/DpsId`

- CNPJ: `/^[0-9A-Z]{12}\d{2}$/D`. É o padrão do pedido e do `TSCNPJ` da NFS-e Via
  v1.01; mais estrito que o `TSCNPJ` da DPS.
- `PATTERN`: o `TSIdDPS` novo. Letras só com tipo de inscrição 2.
- O comentário sobre identificador bem formado e errado continua valendo.

### `Pipeline/Concerns/ValidatesChaveAcesso`

`/^\d{9}[0-9A-Z]{14}\d{27}$/D`, o mesmo alinhamento do `TSChaveNFSe` embarcado.
Não exige tipo 1 ou 2 na 9ª posição: nenhuma chave numérica aceita hoje passa a
ser recusada.

### `Danfse/Formatter::cnpjCpf()`

Remove só o que não é dígito nem letra maiúscula e decide a máscara pelo tamanho:
14 posições recebem máscara de CNPJ, 11 de CPF, o resto sai como veio limpo.

### Documentação

- README: seção "CNPJ alfanumérico" com o contrato para quem chama.
- CHANGELOG `[3.0.0]`: o que muda, a série 90000–99999 passando a ser recusada
  pelo XSD local, as descrições em branco recusadas e as duas divergências.

## Minhas escolhas (não decididas pelo usuário)

- Mensagens de exceção novas em pt-BR, no tom das existentes.
- `TSChaveNFe` (chave de NF-e) copiado do oficial como está: o alinhamento dele é
  o da NF-e.
- Os Ids de evento não exigem tipo 1 ou 2 (ver divergência acima).

## Para quem implementa

Testes pela API pública: `DpsId::generate()`, `emitir()`, `cancelar()`,
`consultar()`, `substituir()` e `Formatter`. Valor de teste: `12ABC34501DE35`.
Rodar todas as verificações do `CLAUDE.md` ao final.
