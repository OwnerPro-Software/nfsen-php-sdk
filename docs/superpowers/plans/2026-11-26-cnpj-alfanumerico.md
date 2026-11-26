# CNPJ alfanumérico Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O SDK aceita CNPJ alfanumérico no Id da DPS, no XSD local, na chave de acesso, nos Ids de evento e no DANFSe.

**Architecture:** O leiaute embarcado continua sendo o de produção; só as linhas de `tiposSimples_v1.01.xsd` que tratam identificadores (e três ajustes pequenos) são copiadas do pacote oficial de Produção Restrita de 24/11/2026. As três validações em PHP (`DpsId`, `ValidatesChaveAcesso`, `Formatter`) passam a espelhar esses padrões.

**Tech Stack:** PHP 8.3, Pest (cobertura, mutação e type coverage a 100%), libxml para XSD.

**Spec:** `docs/superpowers/specs/2026-11-26-cnpj-alfanumerico-design.md`

## Global Constraints

- Valor de teste: `12ABC34501DE35` (exemplo oficial da Receita).
- Chave de teste alfanumérica: `330330212` + `12ABC34501DE35` + `000000000000126010000010000` (50 posições).
- Fonte das linhas de XSD: `esquemas-xsd-producao-restrita-20261124.zip`, arquivo `tiposSimples_v1.01.xsd`.
- Identificadores em inglês; mensagens de exceção, README e CHANGELOG em pt-BR.
- Comentários só quando o código não carrega a informação, em uma linha.
- Testes pela API pública, sem reflexão.
- Sem commits: o usuário não pediu.
- Ao final, todas as verificações do `CLAUDE.md`.

## Review Focus

1. CNPJ em minúsculas ou com máscara em `DpsId::generate()`: recusa (Task 1).
2. CNPJ com exatamente 3 letras no DANFSe (11 dígitos restantes): máscara de CNPJ, não de CPF (Task 4).
3. Chave com letra fora da inscrição (posições 10 a 23), em minúscula ou com `\n` final: recusa antes de qualquer requisição (Task 3).
4. Chave com letra na 12ª posição do CNPJ (posição 21 da chave) em `cancelar()` e `substituir()`: passa no XSD local (Task 2).
5. Série `90000` passa a ser recusada pelo XSD local; `89999` e `00001` seguem aceitas (Task 2).

---

### Task 1: `DpsId` aceita CNPJ alfanumérico

**Files:**
- Modify: `src/Support/DpsId.php`
- Test: `tests/Unit/Support/DpsIdTest.php`

- [ ] **Step 1: Testes que falham**

```php
it('generates id for an alphanumeric CNPJ', function () {
    expect(DpsId::generate('3550308', '12ABC34501DE35', null, '1', '42'))
        ->toBe('DPS3550308'.'2'.'12ABC34501DE35'.'00001'.'000000000000042');
});
```

No dataset de inscrição inválida, trocar o trecho esperado do CNPJ para a mensagem nova e acrescentar: minúsculas (`12abc34501de35`), letra no dígito verificador (`12ABC34501DE3X`), 15 posições (`12ABC34501DE350`), `\n` final. O teste `matches the TSIdDPS pattern` passa a usar `/^DPS[0-9]{7}(1[0-9]{14}|2[0-9A-Z]{14})[0-9]{20}$/` e ganha o caso CNPJ alfanumérico.

- [ ] **Step 2: Rodar `./vendor/bin/pest tests/Unit/Support/DpsIdTest.php`** — falha nos casos novos.

- [ ] **Step 3: Implementar**

- CNPJ: `/^[0-9A-Z]{12}\d{2}$/D`, esperado `'catorze posições, as doze primeiras com dígitos ou letras maiúsculas e as duas últimas com dígitos (TSCNPJ)'`.
- `PATTERN`: `/^DPS\d{7}(1\d{14}|2[0-9A-Z]{14})\d{20}$/D`; a mensagem de Id inválido cita o padrão novo.
- Docblock da classe: registrar que só a inscrição de tipo 2 admite letras.

- [ ] **Step 4: Rodar o arquivo de teste** — passa.

### Task 2: XSD embarcado com os padrões alfanuméricos

**Files:**
- Modify: `storage/schemes/tiposSimples_v1.01.xsd`
- Modify: `tests/helpers.php` (helpers `makeCnpjAlfanumerico()` e `makeChaveAcessoAlfanumerica()`).
- Test: `tests/Unit/Xml/DpsBuilder*Test.php`, `tests/Unit/Xml/CancellationBuilderTest.php`, `tests/Feature/NfsenClientCancelarTest.php`

- [ ] **Step 1: Testes que falham**

- `DpsBuilder::buildAndValidate()` aceita prestador, tomador e intermediário com CNPJ `12ABC34501DE35`; o `Id` gerado contém as letras.
- `DpsBuilder` aceita `chSubstda` com a chave alfanumérica de teste.
- `CancellationBuilder::buildAndValidate()` aceita `cnpjAutor: '12ABC34501DE35'` e a chave alfanumérica; o `Id` é `PRE` + chave + código.
- `cancelar()` com a chave alfanumérica envia a requisição.
- Série `90000` é recusada com erro de XSD; `89999` passa.
- `xDescServ` só com espaços é recusado (`TSDesc2000`).

- [ ] **Step 2: Rodar os arquivos tocados** — falham.

- [ ] **Step 3: Editar o XSD** com a tabela do design. `TSChaveNFSe` recebe `[0-9]{9}[0-9A-Z]{14}[0-9]{27}` e um comentário XML de uma linha citando a divergência do oficial.

- [ ] **Step 4: Conferir contra o oficial** — nos tipos da tabela, só `TSChaveNFSe` e os três Ids de evento divergem.

- [ ] **Step 5: Rodar a suíte inteira** — passa. Os Ids de evento não exigem tipo 1 ou 2 (divergência registrada no design), então a chave de teste `1234567890…` segue válida.

### Task 3: `ValidatesChaveAcesso`

**Files:**
- Modify: `src/Pipeline/Concerns/ValidatesChaveAcesso.php`
- Test: `tests/Unit/Operations/NfseConsulterTest.php`, `tests/Feature/NfsenClientCancelarTest.php`

- [ ] **Step 1: Testes que falham**

- `consultar()->nfse()` com a chave alfanumérica chama a URL com a chave intacta.
- Dataset de recusa em `cancelar()`, sem requisição enviada: letra na posição 9, letra na posição 24, minúsculas na inscrição, 49 e 51 posições, chave alfanumérica com `\n` final.

- [ ] **Step 2: Rodar** — falha.

- [ ] **Step 3: Implementar** `/^\d{9}[0-9A-Z]{14}\d{27}$/D`. Mensagem: `"chaveAcesso inválida: '%s'. Esperado: 50 posições numéricas, admitidas letras maiúsculas apenas na inscrição federal (posições 10 a 23)."`

- [ ] **Step 4: Rodar** — passa.

### Task 4: `Formatter::cnpjCpf()`

**Files:**
- Modify: `src/Danfse/Formatter.php`
- Test: `tests/Unit/Danfse/FormatterTest.php`

- [ ] **Step 1: Testes que falham**

```php
it('cnpjCpf keeps the letters of an alphanumeric CNPJ', function () {
    expect($this->fmt->cnpjCpf('12ABC34501DE35'))->toBe('12.ABC.345/01DE-35');
});

it('cnpjCpf does not mask a CNPJ with three letters as CPF', function () {
    expect($this->fmt->cnpjCpf('ABC12345678901'))->toBe('AB.C12.345/6789-01');
});

it('cnpjCpf formats an already masked alphanumeric CNPJ', function () {
    expect($this->fmt->cnpjCpf('12.ABC.345/01DE-35'))->toBe('12.ABC.345/01DE-35');
});
```

- [ ] **Step 2: Rodar** — falha.

- [ ] **Step 3: Implementar** — limpar com `/[^0-9A-Z]/`, 14 posições recebem a máscara de CNPJ, 11 a de CPF.

- [ ] **Step 4: Rodar** — passa.

### Task 5: Documentação e verificações

**Files:**
- Modify: `README.md`, `CHANGELOG.md`

- [ ] **Step 1: README** — seção "CNPJ alfanumérico": contrato (sem máscara, maiúsculas), o que o SDK recusa, origem dos padrões do XSD e a divergência do `TSChaveNFSe`. Trocar as chaves de exemplo `000…0` por uma de formato válido.
- [ ] **Step 2: CHANGELOG `[3.0.0]`** — entrada em "Changed" com: CNPJ alfanumérico, série 90000–99999 recusada, descrições em branco recusadas, as duas divergências do XSD, e que nada foi testado contra a SEFIN.
- [ ] **Step 3: Rodar todas as verificações do `CLAUDE.md`**, repetindo a suíte se alguma alterar arquivo.
