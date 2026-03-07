# Design: Orquestracão de Substituicão de NFS-e

## Problema

Hoje o processo de substituicão exige dois passos manuais do usuário:

1. Emitir a NFS-e substituta com o campo `subst` preenchido manualmente
2. Chamar `substituir()` passando ambas as chaves para registrar o evento e105102

Isso é propenso a erros e expõe complexidade desnecessária.

## Solucão

O método `substituir` passa a orquestrar as duas etapas internamente. O usuário fornece apenas a DPS da nota substituta, a chave da nota original e o motivo.

## Nova API pública

```php
// NfseClient::substituir (breaking change)
public function substituir(
    string $chave,
    DpsData|array $dps,
    CodigoJustificativaSubstituicao|string $codigoMotivo,
    string $descricao = '',
): SubstituicaoResponse;
```

O campo `subst` na DPS é injetado automaticamente pela lib. Se o usuário passar uma DPS que já tenha `subst` preenchido, será sobrescrito silenciosamente.

## Novo DTO: SubstituicaoResponse

```php
final readonly class SubstituicaoResponse
{
    public function __construct(
        public bool $sucesso,           // true apenas se emissão E evento deram certo
        public NfseResponse $emissao,   // sempre preenchido
        public ?NfseResponse $evento,   // null se emissão falhou
    ) {}
}
```

### Cenários de resposta

| Emissão | Evento | `sucesso` | `emissao` | `evento` |
|---------|--------|-----------|-----------|----------|
| OK      | OK     | `true`    | response  | response |
| OK      | Falha  | `false`   | response  | response |
| Falha   | -      | `false`   | response  | `null`   |

## Orquestracão interna (NfseSubstitutor)

1. Injetar campo `subst` na DPS com `chave`, `codigoMotivo` e `descricao` (sobrescrevendo se existir)
2. Chamar `emitter->emitir($dps)` para emitir a nota substituta
3. Se emissão falhou: retornar `SubstituicaoResponse(sucesso: false, emissao: $r, evento: null)`
4. Se emissão deu certo: registrar evento e105102 com chave original e `chaveSubstituta` da emissão
5. Retornar `SubstituicaoResponse(sucesso: $eventoResponse->sucesso, emissao: $r, evento: $eventoResponse)`

## Eventos

Ambas as operacões disparam seus eventos naturalmente, sem supressão:

- Emissão: `NfseRequested('emitir')` -> `NfseEmitted` (ou `NfseRejected`/`NfseFailed`)
- Substituicão: `NfseRequested('substituir')` -> `NfseSubstituted` (ou `NfseRejected`/`NfseFailed`)

Isso será documentado no README para que o usuário saiba quais eventos esperar.

## Contrato

A interface `SubstitutesNfse` muda para refletir a nova assinatura:

```php
interface SubstitutesNfse
{
    public function substituir(
        string $chave,
        DpsData|array $dps,
        CodigoJustificativaSubstituicao|string $codigoMotivo,
        string $descricao = '',
    ): SubstituicaoResponse;
}
```

## Impacto

- **Breaking change**: assinatura e tipo de retorno do `substituir` mudam
- Arquivos criados: `SubstituicaoResponse`
- Arquivos modificados: `SubstitutesNfse`, `NfseSubstitutor`, `NfseClient`, `NfseNacional` (facade), testes, README, exemplos
- Arquivos inalterados: `SubstitutionBuilder`, `ParsesEventResponse` (o registro do evento continua igual internamente)