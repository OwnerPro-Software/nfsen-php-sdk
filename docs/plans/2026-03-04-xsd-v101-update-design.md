# Atualização XSD v1.01 — Design

## Contexto

Os arquivos XSD do Sistema Nacional NFS-e foram atualizados. Este documento descreve as mudanças necessárias no código PHP para alinhar com a versão mais recente dos schemas.

Decisões:
- Apenas v1.01 será suportada (v1.00 descontinuada)
- Breaking changes na API pública são aceitos

## 1. Enums a Atualizar

### TipoRetPisCofins (2 → 10 cases)

Valores atuais: `1` (Retido), `2` (NaoRetido)

Novos valores:
- `0` — PIS/COFINS/CSLL Não Retidos
- `1` — PIS/COFINS Retidos
- `2` — PIS/COFINS Não Retidos
- `3` — PIS/COFINS/CSLL Retidos
- `4` — PIS/COFINS Retidos, CSLL Não Retido
- `5` — PIS Retido, COFINS/CSLL Não Retido
- `6` — COFINS Retido, PIS/CSLL Não Retido
- `7` — PIS Não Retido, COFINS/CSLL Retidos
- `8` — PIS/COFINS Não Retidos, CSLL Retido
- `9` — COFINS Não Retido, PIS/CSLL Retidos

### TipoCST (10 → 33+ cases)

Case `'07'` renomeado: `TributavelContribuicao` → `IsentaContribuicao`

24 novos cases adicionados: 49, 50-56, 60-67, 70-75, 98-99

### TipoDedRed (+3 cases)

- `3` — Produção Externa
- `4` — Reembolso de despesas
- `9` — Profissional parceiro

### VinculoPrestacao (+1 case)

- `9` — Desconhecido

## 2. DTOs e Enums a Remover

| Arquivo | Motivo |
|---|---|
| `src/Dps/DTO/Servico/ExploracaoRodoviaria.php` | Tipo removido do XSD |
| `src/Dps/DTO/Servico/LocacaoSublocacao.php` | Tipo removido do XSD |
| `src/Dps/Enums/Servico/CategoriaVeiculo.php` | Usado apenas por ExploracaoRodoviaria |
| `src/Dps/Enums/Servico/TipoRodagem.php` | Usado apenas por ExploracaoRodoviaria |
| `src/Dps/Enums/Servico/CategoriaServico.php` | Usado apenas por LocacaoSublocacao |
| `src/Dps/Enums/Servico/ObjetoLocacao.php` | Usado apenas por LocacaoSublocacao |

## 3. DTOs a Modificar

### Servico

Remover campos `$explRod` (ExploracaoRodoviaria) e `$lsadppu` (LocacaoSublocacao), incluindo imports e `fromArray()`.

### CodigoServico

Campo `$cNBS` muda de required (`string`) para optional (`?string = null`).

## 4. Builders a Modificar

### ServicoBuilder

- Remover bloco de construção `lsadppu` (linhas 76-83)
- Remover bloco de construção `explRod` (linhas 154-165)
- Tornar `cNBS` condicional (só emitir se não null)

### CancellationBuilder

- Remover elemento XML `<nPedRegEvento>`
- Remover parâmetro `$nPedRegEvento` de `buildAndValidate()` e `build()`
- Atualizar geração de ID: `'PRE' . $chNFSe . '101101'` (sem padding de 3 dígitos)

### SubstitutionBuilder

- Remover elemento XML `<nPedRegEvento>`
- Remover parâmetro `$nPedRegEvento` de `buildAndValidate()` e `build()`
- Atualizar geração de ID: `'PRE' . $chNFSe . '105102'` (sem padding de 3 dígitos)

## 5. API Pública (Breaking Changes)

Remover `int $nPedRegEvento = 1` de:

- `Contracts/Driving/CancelsNfse.php` (interface)
- `Contracts/Driving/SubstitutesNfse.php` (interface)
- `Operations/NfseCanceller.php`
- `Operations/NfseSubstitutor.php`
- `NfseClient.php`
- `Facades/NfseNacional.php` (docblock `@method`)

## 6. Testes

- Atualizar testes que referenciam `explRod`, `lsadppu`, `nPedRegEvento`
- Adicionar testes para novos enum cases
- Atualizar testes de validação XSD
- Remover testes de DTOs/enums removidos

## 7. Fora do Escopo

Itens presentes no XSD mas não implementados no PHP:

- Campos IBSCBS RTC (pRedutor, indFinal, vDifUF, vDifMun, vDifCBS) — tipos de resposta
- `xOutInf`, `nDFe/nDFSe`, `verAplic`, eventos de rejeição/anulação
- `TStat 101`, `TSCodigoEventoNFSe e907202/e967203`
