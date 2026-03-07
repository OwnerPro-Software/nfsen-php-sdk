# Design: confirmarSubstituicao

## Problema

O `substituir` orquestra emissão + evento. Não há como executar apenas a etapa 2 (registro do evento e105102). Isso é necessário quando:

- O usuário já emitiu a nota substituta por conta própria
- A etapa 2 do `substituir` falhou e o usuário precisa tentar novamente

## Solução

Novo método `confirmarSubstituicao` que executa apenas o registro do evento e105102.

## API

```php
$client->confirmarSubstituicao(
    chaveSubstituida: $chaveOriginal,
    chaveSubstituta: $chaveSubstituta,
    codigoMotivo: CodigoJustificativaSubstituicao::Outros,
    descricao: 'Correção de dados',
); // → NfseResponse
```

## Contrato

Adicionar na interface `SubstitutesNfse`:

```php
interface SubstitutesNfse
{
    public function substituir(...): SubstituicaoResponse;
    public function confirmarSubstituicao(string $chaveSubstituida, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = ''): NfseResponse;
}
```

## Implementação

Extrair a lógica do evento e105102 de `NfseSubstitutor::substituir` para um método privado. Ambos `substituir` e `confirmarSubstituicao` usam esse método.

## README

Documentar como cenário alternativo na seção "Substituir NFSe":

- `substituir` para o fluxo completo (emissão + evento)
- `confirmarSubstituicao` para registrar apenas o evento

## Impacto

- Não é breaking change — apenas adiciona método novo
- Arquivos modificados: `SubstitutesNfse`, `NfseSubstitutor`, `NfseClient`, `NfseNacional` (facade), testes, README