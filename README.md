# NFSe Nacional

[![CI](https://github.com/OwnerPro-Software/nfsen-php-sdk/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/OwnerPro-Software/nfsen-php-sdk/actions)
[![PHPStan Level 10](https://img.shields.io/badge/PHPStan-level%2010-brightgreen.svg)](https://phpstan.org/)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/ownerpro/nfsen-php-sdk.svg)](https://packagist.org/packages/ownerpro/nfsen-php-sdk)
[![PHP Version](https://img.shields.io/packagist/php-v/ownerpro/nfsen-php-sdk.svg)](https://packagist.org/packages/ownerpro/nfsen-php-sdk)
[![License](https://img.shields.io/packagist/l/ownerpro/nfsen-php-sdk.svg)](LICENSE)

Pacote PHP para emissão, cancelamento, substituição e consulta de **NFSe Padrão Nacional** ([nfse.gov.br](https://www.nfse.gov.br/)) via API REST. Funciona com Laravel 11/12 ou standalone (sem framework).

## Funcionalidades

- Emissão de NFSe (`emitir`) e emissão por decisão judicial (`emitirDecisaoJudicial`)
- Cancelamento de NFSe (`cancelar`)
- Substituição de NFSe (`substituir`)
- Consulta por chave de acesso, DPS, DANFSE (URL do PDF), eventos e verificação de DPS
- Distribuição de documentos fiscais via ADN — consulta em lote por NSU (`distribuicao`)
- Assinatura digital XML com certificado A1 (PFX/P12)
- Validação XSD dos documentos
- Eventos Laravel opcionais (`NfseEmitted`, `NfseCancelled`, `NfseRejected`, etc.)
- mTLS sem escrita nomeada em disco
- 100% de cobertura de testes e tipos

## Requisitos

- PHP 8.2+
- Extensões: `curl`, `dom`, `zlib`, `openssl`, `mbstring`, `libxml`
- Laravel 11 ou 12 (opcional — funciona standalone)

## Instalação

```bash
composer require ownerpro/nfsen-php-sdk
```

## Configuração

### Laravel

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=nfsen-config
```

Adicione as variáveis de ambiente no `.env`:

```env
NFSE_AMBIENTE=2                   # 1 = Producao, 2 = Homologacao (aceita: 'producao', 'production', 'homologacao', 'homologation')
NFSE_PREFEITURA=3550308           # Codigo IBGE do municipio (7 digitos)
NFSE_CERT_PATH=/caminho/cert.pfx  # Caminho do certificado PFX/P12
NFSE_CERT_SENHA=senha             # Senha do certificado
NFSE_TIMEOUT=30
NFSE_CONNECT_TIMEOUT=10
NFSE_SIGNING_ALGORITHM=sha1
NFSE_SSL_VERIFY=true
NFSE_VALIDATE_IDENTITY=true          # Valida CNPJ/CPF do certificado contra o prestador da DPS
```

A variavel `NFSE_VALIDATE_IDENTITY` (padrao: `true`) controla se o SDK verifica que o CNPJ ou CPF do certificado digital corresponde ao prestador informado na DPS antes de enviar a requisicao. Desative (`false`) apenas quando um representante legal (contador ou procurador com procuracao eletronica) emite notas em nome de terceiros.

### Standalone (sem Laravel)

```php
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\NfsenClient;

$client = NfsenClient::forStandalone(
    pfxContent: file_get_contents('/caminho/certificado.pfx'),
    senha: 'senha_certificado',
    prefeitura: '3550308',
    ambiente: NfseAmbiente::HOMOLOGACAO,
);
```

Para desabilitar a validacao de identidade (representante legal / contador):

```php
$client = NfsenClient::forStandalone(
    pfxContent: file_get_contents('/caminho/certificado.pfx'),
    senha: 'senha_certificado',
    prefeitura: '3550308',
    ambiente: NfseAmbiente::HOMOLOGACAO,
    validateIdentity: false,
);
```

## Uso

### Emitir NFSe

```php
$response = $client->emitir([
    'infDPS' => [
        'tpAmb'    => '2',                          // 1 = Producao, 2 = Homologacao
        'dhEmi'    => gmdate('Y-m-d\TH:i:sP'),      // Data/hora emissao (UTC: TSDateTimeUTC exige offset de minuto zero)
        'verAplic' => 'MeuSistema_v1.0',
        'serie'    => '1',
        'nDPS'     => '1',
        'dCompet'  => date('Y-m-d'),                // Data de competencia
        'tpEmit'   => '1',                          // 1 = Prestador
        'cLocEmi'  => '3550308',                    // Codigo IBGE 7 digitos
    ],
    'prest' => [
        'CNPJ'    => '00000000000000',
        'fone'    => '11999999999',
        'regTrib' => [
            'opSimpNac'   => '2',                   // 1 = Nao Optante, 2 = MEI, 3 = ME/EPP
            'regEspTrib'  => '0',                   // 0 = Nenhum
        ],
    ],
    'toma' => [
        'xNome' => 'Tomador Exemplo Ltda',
        'CPF'   => '00000000000',
        'end'   => [
            'xLgr'   => 'Rua Exemplo',
            'nro'    => '100',
            'xBairro' => 'Centro',
            'endNac' => [
                'cMun' => '3550308',
                'CEP'  => '01001000',
            ],
        ],
    ],
    'serv' => [
        'cLocPrestacao' => '3550308',
        'cServ' => [
            'cTribNac'    => '010101',
            'xDescServ'   => 'Desenvolvimento de software sob encomenda',
            'cNBS'        => '116030000',
            'cIntContrib' => '1234',
        ],
    ],
    'valores' => [
        'vServPrest' => ['vServ' => '1000.00'],
        'trib' => [
            'tribMun' => [
                'tribISSQN'  => '1',               // 1 = Operacao tributavel
                'tpRetISSQN' => '1',               // 1 = Nao retido
            ],
            'indTotTrib' => '0',
        ],
    ],
]);

if ($response->sucesso) {
    echo "Chave: {$response->chave}\n";
    echo "XML: {$response->xml}\n";
} else {
    foreach ($response->erros as $erro) {
        echo "[{$erro->codigo}] {$erro->mensagem} - {$erro->descricao}";
    }
}
```

### Emitir NFSe por decisão judicial

Utiliza endpoint diferente (`emit_court_order`) para notas emitidas por determinação judicial:

```php
$response = $client->emitirDecisaoJudicial($dps);
// Mesma estrutura de DPS e mesmo NfseResponse do emitir()
```

### Cancelar NFSe

```php
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;

$response = $client->cancelar(
    chave: '00000000000000000000000000000000000000000000000000',
    codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
    descricao: 'Erro na emissao da nota fiscal',
);
```

Codigos de cancelamento: `ErroEmissao`, `ServicoNaoPrestado`, `Outros`.

### Substituir NFSe

O método `substituir()` emite uma DPS com o grupo `subst` preenchido automaticamente. O ADN (Ambiente de Dados Nacional) cancela a nota original ao processar a DPS substituta — uma única requisição.

> **Nota:** O registro do evento de cancelamento por substituição (`e105102`) via API Eventos (`POST /nfse/{chave}/eventos`) é restrito a sistemas municipais conveniados com o ADN — o autor desse evento é o município emissor (MEmis), não o contribuinte. O cancelamento da nota original ocorre automaticamente ao emitir a DPS com o grupo `subst` preenchido.

```php
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;

$response = $client->substituir(
    chave: '00000000000000000000000000000000000000000000000000',
    dps: $dpsSubstituta, // DPS da nota substituta (mesma estrutura do emitir)
    codigoMotivo: CodigoJustificativaSubstituicao::Outros,
    descricao: 'Substituicao por correcao de dados',
);

// NfseResponse (mesmo retorno do emitir)
if ($response->sucesso) {
    echo "Chave substituta: {$response->chave}";
} else {
    foreach ($response->erros as $erro) {
        echo "[{$erro->codigo}] {$erro->descricao}\n";
    }
}
```

Codigos de substituição: `DesenquadramentoSimplesNacional`, `EnquadramentoSimplesNacional`, `InclusaoRetroativaImunidadeIsencao`, `ExclusaoRetroativaImunidadeIsencao`, `RejeicaoTomadorIntermediario`, `Outros`.

### Consultas

```php
use OwnerPro\Nfsen\Enums\TipoEvento;

// Consultar NFSe por chave de acesso
$response = $client->consultar()->nfse($chave);

// Consultar DPS por ID
$response = $client->consultar()->dps($idDps);
// Quando a SEFIN responde 404 (DPS inexistente), a resposta traz um erro
// dedicado: $response->erros[0]->codigo === NfseResponse::DPS_NOT_FOUND.
// Sinal inequívoco de "não existe" — distinto de erros transitórios
// (401/403/429/5xx lançam HttpException, pois consulta não altera estado;
// falha de transporte lança IndeterminateResultException).

// Obter PDF do DANFSE
$response = $client->consultar()->danfse($chave);
// $response->pdf contém o conteúdo binário do PDF
file_put_contents('danfse.pdf', $response->pdf);

// Consultar eventos (tipoEvento é obrigatório)
$response = $client->consultar()->eventos(
    chave: $chave,
    tipoEvento: TipoEvento::Cancelamento, // e101101
    nSequencial: 1,
);
// Tipos disponíveis (nome — código, conforme tiposEventos_v1.01.xsd):
// Cancelamento — 101101                          SolicitacaoCancelamentoAnaliseFiscal — 101103
// CancelamentoPorSubstituicao — 105102           CancelamentoDeferidoAnaliseFiscal — 105104
// CancelamentoIndeferidoAnaliseFiscal — 105105   ConfirmacaoPrestador — 202201
// RejeicaoPrestador — 202205                     ConfirmacaoTomador — 203202
// RejeicaoTomador — 203206                       ConfirmacaoIntermediario — 204203
// RejeicaoIntermediario — 204207                 ConfirmacaoTacita — 205204
// AnulacaoRejeicao — 205208                      CancelamentoPorOficio — 305101
// BloqueioPorOficio — 305102                     DesbloqueioPorOficio — 305103
// InclusaoNfseDan — 467201                       TributosNfseRecolhidos — 907201
//
// Quando a SEFIN responde 404 (evento inexistente), a resposta traz um erro
// dedicado: $response->erros[0]->codigo === EventsResponse::EVENT_NOT_FOUND.
// Sinal inequívoco de "não existe" — qualquer outro sucesso: false é
// inconclusivo. Um 2xx sem `eventoXmlGZipB64` não ocorre em operação normal e
// lança IndeterminateResultException (nunca vira sucesso com xml: null).

// Verificar se DPS foi processada
// true em HTTP 200; false APENAS em HTTP 404 (comprovadamente não existe).
// Qualquer outro status (401, 403, 429, redirect, 5xx…) lança HttpException;
// falha de transporte lança IndeterminateResultException — nesses casos a
// existência é indeterminada e NÃO é seguro re-emitir.
$processada = $client->consultar()->verificarDps($idDps); // true ou false
```

#### Gerar o ID da DPS sem montar o XML

O identificador de 45 posições da DPS (`TSIdDPS`) pode ser calculado fora da
emissão — útil para reconciliar após um timeout, consultando a DPS antes de
qualquer retry:

```php
use OwnerPro\Nfsen\Support\DpsId;

// "DPS" + cLocEmi(7) + tpInsc(1=CPF|2=CNPJ) + inscrição(14, zero-pad)
//       + série(5, zero-pad) + nDPS(15, zero-pad)
$idDps = DpsId::generate(
    cLocEmi: '3550308',
    cnpj: '12345678000195',
    cpf: null,
    serie: '1',
    nDps: '42',
);
// => "DPS3550308212345678000195000010000000000000042"
```

`DpsId` é a fonte única da regra fiscal de formação do ID — o mesmo código usado
internamente na construção do XML da DPS. O retorno é validado contra o padrão
`DPS[0-9]{42}` do schema nacional; entrada inválida lança `InvalidDpsArgument`.
Informe o **mesmo** CNPJ ou CPF usado na emissão — ambos `null` lança
`InvalidDpsArgument` (inscrição zerada só é válida para prestador estrangeiro
com NIF/cNaoNIF, via `allowEmptyInscricao: true`).

#### Reconciliação após resultado indeterminado na emissão

```php
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Support\DpsId;

try {
    $response = $client->emitir($payload);
} catch (IndeterminateResultException $e) {
    // O SDK não conseguiu ler o resultado — a nota pode ou não ter sido emitida.
    $idDps = DpsId::generate($cLocEmi, $cnpj, null, $serie, (string) $nDps);

    $lookup = $client->consultar()->dps($idDps);

    if ($lookup->sucesso) {
        // A nota FOI emitida: salvar chave e continuar o fluxo normal.
    } elseif (($lookup->erros[0]->codigo ?? null) === NfseResponse::DPS_NOT_FOUND) {
        // A emissão NÃO aconteceu: seguro re-emitir com o mesmo nDPS.
    }
}
```

#### Reconciliação após resultado indeterminado no cancelamento

Mesma régua da emissão, ancorada na consulta de eventos:

```php
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Responses\EventsResponse;

try {
    $response = $client->cancelar($chave, $codigoMotivo, $descricao);
} catch (IndeterminateResultException $e) {
    $lookup = $client->consultar()->eventos(
        chave: $chave,
        tipoEvento: TipoEvento::Cancelamento,
        nSequencial: 1,
    );

    if ($lookup->sucesso) {
        // O cancelamento REGISTROU: siga o fluxo normal com $lookup->xml.
    } elseif (($lookup->erros[0]->codigo ?? null) === EventsResponse::EVENT_NOT_FOUND) {
        // O cancelamento NÃO registrou: seguro reenviar.
    } else {
        // Inconclusivo (401/429/5xx com corpo estruturado): aguarde e tente de novo.
    }
}
```

### Distribuição (ADN Contribuinte)

Consulta em lote de documentos fiscais via NSU (Número Sequencial Único) através do ADN (Ambiente de Dados Nacional). Útil para importação em massa de NFS-e.

```php
// Buscar lote de documentos a partir do NSU 0
$response = $client->distribuicao()->documentos(0);

if ($response->sucesso) {
    foreach ($response->lote as $doc) {
        // Um item que o SDK não conseguiu interpretar por completo não interrompe o
        // lote: os campos afetados vêm null e parseError diz o que faltou. O nsu é
        // sempre preservado, então dá para refazer a busca daquele documento.
        if ($doc->parseError !== null) {
            echo "NSU {$doc->nsu} incompleto: {$doc->parseError}\n";

            continue;
        }

        echo "NSU: {$doc->nsu} | Tipo: {$doc->tipoDocumento->value} | Chave: {$doc->chaveAcesso}\n";
        // $doc->arquivoXml contém o XML já descomprimido
    }
}

// Buscar documento unitário pelo NSU
$response = $client->distribuicao()->documento(42);

// Buscar todos os eventos de uma NFS-e
$response = $client->distribuicao()->eventos($chave);

// Usar CNPJ diferente do certificado (procurador/filiais)
$response = $client->distribuicao()->documentos(0, '99999999000100');
```

O fluxo típico de importação:

1. Comece com NSU `0`
2. Chame `documentos($nsu)` — receba um lote
3. Guarde o maior NSU do lote
4. Repita com o próximo NSU até `statusProcessamento` ser `NenhumDocumentoLocalizado`

### Laravel Facade

```php
use OwnerPro\Nfsen\Facades\Nfsen;

// Emitir
$response = Nfsen::emitir($dps);

// Cancelar
$response = Nfsen::cancelar($chave, $motivo, $descricao);

// Consultar
$response = Nfsen::consultar()->nfse($chave);
$danfse   = Nfsen::consultar()->danfse($chave);

// Usar certificado diferente por requisicao
$client = Nfsen::for($pfxContent, $senha, '3550308');
$response = $client->emitir($dps);

// Sobrescrever ambiente (ignorar config)
use OwnerPro\Nfsen\Enums\NfseAmbiente;

$client = Nfsen::for($pfxContent, $senha, '3550308', NfseAmbiente::PRODUCAO);
$response = $client->emitir($dps);
```

## Eventos

O pacote dispara eventos Laravel que podem ser escutados na sua aplicação:

| Evento | Propriedades | Descricao |
|--------|-------------|-----------|
| `NfseEmitted` | `chave` | NFSe emitida com sucesso |
| `NfseCancelled` | `chave` | NFSe cancelada com sucesso |
| `NfseSubstituted` | `chave`, `chaveSubstituta` | NFSe substituída com sucesso |
| `NfseQueried` | `operacao` | Consulta realizada |
| `NfseRequested` | `operacao`, `metadata` | Operação iniciada |
| `NfseRejected` | `operacao`, `codigoErro`, `mensagemErro`, `correcao` | Operação rejeitada pela API (`mensagemErro` e `correcao` ficam `null` quando a API não retorna os campos correspondentes ou no fallback `SEM_CHAVE`) |
| `NfseFailed` | `operacao`, `mensagem` | Falha na operação |

**Substituição:** como `substituir` delega ao `emitir` internamente, a sequência de eventos disparados é:
`NfseRequested('emitir')` → `NfseEmitted` → `NfseSubstituted`

## Objetos de Resposta

Cada operação retorna um DTO tipado e imutável:

### `NfseResponse`

Retornado por `emitir()`, `emitirDecisaoJudicial()`, `cancelar()`, `substituir()`, `consultar()->nfse()` e `consultar()->dps()`.

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `sucesso` | `bool` | Se a operação foi aceita |
| `chave` | `?string` | Chave de acesso da NFSe (50 dígitos) |
| `xml` | `?string` | XML da NFSe processada |
| `idDps` | `?string` | Identificador da DPS |
| `alertas` | `list<ProcessingMessage>` | Alertas não-bloqueantes |
| `erros` | `list<ProcessingMessage>` | Erros de processamento |
| `tipoAmbiente` | `?int` | 1 = Produção, 2 = Homologação |
| `versaoAplicativo` | `?string` | Versão do aplicativo da SEFIN |
| `dataHoraProcessamento` | `?string` | Data/hora do processamento |

### `DanfseResponse`

Retornado por `consultar()->danfse()`.

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `sucesso` | `bool` | Se o PDF foi obtido |
| `pdf` | `?string` | Conteúdo binário do PDF |
| `erros` | `list<ProcessingMessage>` | Erros de processamento |

> **A API remota de DANFSe foi sobrestada em 01/07/2026.** A Nota Técnica nº 008, de
> 05/05/2026 (`storage/danfse/nt-008-se-cgnfse-danfse-20260505.pdf`, seção 1),
> suspendeu `https://adn.nfse.gov.br/danfse` e transferiu a geração do documento para
> o emissor. Toda falha de `consultar()->danfse()` passa a trazer, além do erro
> original, uma mensagem com o código `DanfseResponse::API_SOBRESTADA`.
>
> Gere o documento localmente a partir do XML da NFS-e:
>
> ```php
> $pdf = $client->danfse()->toPdf($response->xml);
> ```

### `EventsResponse`

Retornado por `consultar()->eventos()`.

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `sucesso` | `bool` | Se a consulta teve sucesso |
| `xml` | `?string` | XML do evento (nunca `null` quando `sucesso` é `true`) |
| `erros` | `list<ProcessingMessage>` | Erros de processamento |
| `tipoAmbiente` | `?int` | 1 = Produção, 2 = Homologação |
| `versaoAplicativo` | `?string` | Versão do aplicativo da SEFIN |
| `dataHoraProcessamento` | `?string` | Data/hora do processamento |

Constante `EventsResponse::EVENT_NOT_FOUND`: presente em `erros[0]->codigo`
quando a SEFIN responde 404 — o evento comprovadamente não existe (distinto de
erro transitório, que permanece `sucesso: false` sem esse código).

### `DistribuicaoResponse`

Retornado por `distribuicao()->documentos()`, `distribuicao()->documento()` e `distribuicao()->eventos()`.

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `sucesso` | `bool` | `true` quando `statusProcessamento` é `DocumentosLocalizados` |
| `statusProcessamento` | `StatusDistribuicao` | Status: `Rejeicao`, `NenhumDocumentoLocalizado`, `DocumentosLocalizados` |
| `lote` | `list<DocumentoFiscal>` | Documentos fiscais retornados |
| `alertas` | `list<ProcessingMessage>` | Alertas não-bloqueantes |
| `erros` | `list<ProcessingMessage>` | Erros de processamento |
| `tipoAmbiente` | `?int` | 1 = Produção, 2 = Homologação |
| `versaoAplicativo` | `?string` | Versão do aplicativo |
| `dataHoraProcessamento` | `?string` | Data/hora do processamento |

### `DocumentoFiscal`

Cada item do lote na `DistribuicaoResponse`.

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `nsu` | `?int` | Número Sequencial Único |
| `chaveAcesso` | `?string` | Chave de acesso da NFS-e |
| `tipoDocumento` | `?TipoDocumentoFiscal` | Tipo: `Nfse`, `Dps`, `Evento`, `Cnc`, `PedidoRegistroEvento`, `Nenhum`. `null` quando ausente ou desconhecido — veja `parseError` |
| `tipoEvento` | `?TipoEventoDistribuicao` | Tipo do evento (quando `tipoDocumento` é `Evento`). `null` quando ausente ou desconhecido |
| `arquivoXml` | `?string` | XML do documento (já descomprimido). `null` quando ausente ou indecodificável |
| `dataHoraGeracao` | `?string` | Data/hora de geração |
| `parseError` | `?string` | Por que o documento não pôde ser interpretado por completo; `null` quando íntegro. Nenhum campo de `DistribuicaoNSU` é obrigatório no contrato do ADN, e o governo pode emitir tipos que esta versão do SDK ainda não conhece — nesses casos o item entra no lote com os campos afetados em `null`, em vez de derrubar o lote inteiro |

### `ProcessingMessage`

Representa uma mensagem de erro ou alerta da API:

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `mensagem` | `?string` | Mensagem principal |
| `codigo` | `?string` | Código do erro/alerta |
| `descricao` | `?string` | Descrição detalhada |
| `complemento` | `?string` | Informação complementar |
| `parametros` | `list<string>` | Parâmetros adicionais da mensagem |

## Exceções

| Exceção | Pai | Quando |
|---------|-----|--------|
| `NfseException` | `RuntimeException` | Erros gerais (XML inválido, falha de compressão, etc.) |
| `CommunicationException` | `NfseException` | Base abstrata das falhas de comunicação (nenhuma resposta completa e legível). Capture-a para tratar tudo como indeterminado; capture as subclasses para distinguir |
| `IndeterminateResultException` | `CommunicationException` | **Resultado indeterminado**: a requisição pode ou não ter sido processada pela SEFIN. Reconcilie antes de retry (veja abaixo). `phase` indica a fase da falha quando detectável (`connect`, `dns`, `read`, `tls`, `transfer`, `body`); é `null` quando a resposta chegou inteira e o que falta é evidência de processamento, como num 5xx sem rejeição da SEFIN |
| `RequestNotDeliveredException` | `CommunicationException` | **Não entregue**: a falha ocorreu comprovadamente antes de qualquer byte HTTP ser enviado (`phase`: `dns`, `connect` ou `tls`). A operação não foi processada — retry direto é seguro, sem reconciliação. Só é lançada com `detectNotDelivered: true` |
| `HttpException` | `NfseException` | Resposta HTTP de erro recebida sem corpo estruturado (redirect, 4xx vazio, 5xx em consulta; status inesperado em `verificarDps()`/`dps()`). Acesse `getResponseBody()` para detalhes. **Num 5xx a operações que alteram estado** (`emitir`, `cancelar`, `substituir`) o SDK lança `IndeterminateResultException`, não esta |
| `CertificateExpiredException` | `NfseException` | Certificado PFX/P12 expirado |
| `InvalidDpsArgument` | `InvalidArgumentException` | Campos mutuamente exclusivos ou obrigatórios violados na DPS; ID de DPS fora do padrão `TSIdDPS` |

```php
use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Exceptions\RequestNotDeliveredException;

try {
    $response = $client->emitir($dps);
} catch (CertificateExpiredException $e) {
    // Certificado expirado -- renovar
} catch (InvalidDpsArgument $e) {
    // Dados da DPS inválidos -- corrigir payload
} catch (RequestNotDeliveredException $e) {
    // Só com detectNotDelivered: true. Nada chegou à SEFIN -- retry direto seguro.
} catch (IndeterminateResultException $e) {
    // Resultado INDETERMINADO -- a nota pode ou não ter sido emitida.
    // NUNCA re-emita sem antes reconciliar com consultar()->dps($idDps).
} catch (HttpException $e) {
    // Resposta HTTP recebida com erro -- $e->getCode() para status, $e->getResponseBody() para corpo
} catch (NfseException $e) {
    // Outros erros (XML, compressão, etc.)
}
```

**Contrato de indeterminação:** capturar `IndeterminateResultException`
significa que a SEFIN pode ou não ter recebido e processado a requisição. Ela
cobre cinco situações:

1. **Falha antes de qualquer resposta** (timeout, DNS, conexão recusada, TLS)
   — a requisição pode nem ter chegado ao servidor;
2. **Falha no meio da transferência** (conexão resetada, corpo truncado) — o
   servidor processou, mas o resultado não pôde ser lido;
3. **Resposta 2xx com corpo ilegível** (JSON inválido ou vazio) — o servidor
   confirmou o processamento, mas o resultado não pôde ser interpretado;
4. **Resposta 2xx com JSON válido porém sem o campo obrigatório da operação**
   (ex.: `consultar()->eventos()` sem `eventoXmlGZipB64`) — shape que não ocorre
   em operação normal; ausência comprovada é sinalizada por HTTP 404, nunca por
   corpo vazio;
5. **Resposta 5xx a uma operação que altera estado** (`emitir`,
   `emitirDecisaoJudicial`, `cancelar`, `substituir`) **sem rejeição estruturada
   da SEFIN no corpo** — o erro pode ter vindo de um proxy antes da SEFIN, ou da
   própria SEFIN depois de gravar a nota, e nada no corpo distingue os dois. Um
   5xx que **traz** `erros`/`erro` preenchido é rejeição definitiva: prova que a
   requisição chegou e foi processada. Em consultas, 5xx continua lançando
   `HttpException` — não há estado a reconciliar.

> **204 não entra nesta lista.** "No Content" define corpo vazio, então a ausência
> de JSON ali é a resposta correta e não estado indeterminado — `distribuicao()`
> devolve `sucesso: false` com o código `EMPTY_RESPONSE`.

Nos cinco casos a ação é a mesma: **nunca faça retry cego de emissão** (a NFS-e
pode já existir e um retry causaria dupla emissão). Calcule o ID com
`DpsId::generate()` e consulte `consultar()->dps($id)`: se encontrou, a nota
foi emitida; se retornar `NfseResponse::DPS_NOT_FOUND`, é seguro re-emitir com
o mesmo nDPS. Qualquer outra exceção ou resposta do SDK é uma resposta
definitiva do servidor.

**Distinguindo "não entregue" de "indeterminado"** (opt-in): falhas de DNS,
conexão TCP e handshake TLS acontecem **antes** de qualquer byte HTTP ser
enviado — a requisição comprovadamente não chegou à SEFIN e o retry direto é
seguro, sem reconciliação. Ative com `detectNotDelivered: true` em
`forStandalone()` (ou `NFSE_DETECT_NOT_DELIVERED=true` no Laravel) para que
esses casos lancem `RequestNotDeliveredException` em vez de
`IndeterminateResultException`:

```php
try {
    $response = $client->emitir($payload);
} catch (RequestNotDeliveredException $e) {
    // Nada chegou à SEFIN ($e->phase: dns|connect|tls) — repetir o envio direto.
} catch (IndeterminateResultException $e) {
    // Pode ter sido processada — reconciliar antes de qualquer retry.
}
```

A classificação usa apenas o errno do cURL (evidência inequívoca: 6, 7, 35,
58, 60); qualquer ambiguidade — incluindo **todo** timeout (cURL 28, cuja fase
não é provável em conexões keep-alive reutilizadas) — permanece indeterminada.
Vale para todas as operações do SDK (emissão, cancelamento, consultas,
distribuição), não apenas `emitir()`. O default é `false` para não alterar
catches existentes de `IndeterminateResultException`;
`catch (CommunicationException)` cobre os dois tipos e equivale a tratar tudo
como indeterminado (sempre seguro).

> Nota: com a flag ativa, um timeout de connect (cURL 28) reporta
> `phase: 'read'` na `IndeterminateResultException` — a fase de um timeout não
> é provável por errno, então ele nunca vira "não entregue". Com a flag
> desativada, a fase legada vinda do texto da mensagem (`'connect'`) é mantida.
> Não use `phase` para decidir retry; ela é apenas diagnóstico.

## Renderização local do DANFSE

O SDK gera o DANFSE (PDF ou HTML) localmente a partir do XML da NFS-e autorizada, útil como alternativa quando o endpoint ADN oficial está indisponível ou quando você precisa renderizar offline.

### Uso básico

```php
use OwnerPro\Nfsen\NfsenClient;

$client = NfsenClient::for($pfx, $senha, $prefeitura);
$response = $client->emitir($dps);

$pdf = $client->danfse()->toPdf($response->xml);
file_put_contents('danfse.pdf', $pdf->pdf);
```

### Customização via array

```php
$resp = $client->danfse([
    'logo_path' => '/path/to/custom-logo.png',
    'municipality' => [
        'name' => 'São Paulo',
        'department' => 'SF/SUBTES',
        'email' => 'nfse@sp.gov.br',
    ],
])->toPdf($response->xml);

if ($resp->sucesso) {
    file_put_contents('danfse.pdf', $resp->pdf);
}
```

### Customização via DTO (equivalente)

```php
use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

$config = new DanfseConfig(
    logoPath: '/caminho/para/logo.png',
    municipality: new MunicipalityBranding(
        name: 'Município de Canela',
        department: '(54) 3282-5155',
        email: 'issqn@canela.rs.gov.br',
        logoPath: '/caminho/para/brasao.png',
    ),
);

$pdf = $client->danfse($config)->toPdf($response->xml);
```

### Debug: obter o HTML intermediário

```php
$html = $client->danfse()->toHtml($response->xml);
file_put_contents('danfse.html', $html);
```

Diferente de `toPdf()`, que devolve `DanfseResponse` com `sucesso: false` em caso de
falha, `toHtml()` retorna `string` e portanto **propaga** a exceção: `XmlParseException`
quando o XML está malformado ou não traz algum grupo obrigatório da NFS-e.

### Geração automática do DANFSE

O PDF é anexado ao `NfseResponse` em `emitir()`, `emitirDecisaoJudicial()`, `substituir()`
e `consultar()->nfse()` quando a DANFSE é configurada.

**Modo Laravel simples** — via `config/nfsen.php`:

```env
NFSE_DANFSE_AUTO=true
NFSE_DANFSE_LOGO_PATH=/path/to/logo.png
NFSE_DANFSE_MUN_NAME="São Paulo"
NFSE_DANFSE_MUN_DEPT="SF/SUBTES"
NFSE_DANFSE_MUN_EMAIL=nfse@sp.gov.br
```

```php
$resp = NfsenClient::for($pfx, $senha, $ibge)->emitir($dps);
echo $resp->pdf;                 // string com o PDF (ou null se render falhou)
print_r($resp->pdfErrors);       // list<ProcessingMessage> quando render falha
```

**Modo multi-tenant** — passe o array por requisição:

```php
$client = NfsenClient::for($pfx, $senha, $tenant->ibge, danfse: [
    'logo_path' => $tenant->logoPath,
    'municipality' => [
        'name' => $tenant->municipio,
        'email' => $tenant->emailPrefeitura,
    ],
]);

$resp = $client->emitir($dps);
```

**Desligar pontualmente** (mesmo com `NFSE_DANFSE_AUTO=true`):

```php
$client = NfsenClient::for($pfx, $senha, $ibge, danfse: false);
```

**Quando o PDF falha** (`$resp->sucesso === true` mas `$resp->pdf === null`): a NFS-e
foi emitida com sucesso. Tente regenerar sob demanda com
`$client->danfse($config)->toPdf($resp->xml)`.

**Gotcha do `enabled`**: a flag `nfsen.danfse.enabled` é checada com `=== true` estrito.
O config publicado aplica `(bool)` cast; se consumir config de outra fonte (ex.: banco de
dados) garanta o tipo bool. `1`, `'true'`, ou `'on'` **não** ativam auto-render.

### Atribuição

A renderização do DANFSE foi portada da biblioteca [`andrevabo/danfse-nacional`](https://github.com/andrevabo/danfse-nacional) (MIT) e adaptada à arquitetura deste SDK. A tabela de municípios IBGE vem de [`kelvins/municipios-brasileiros`](https://github.com/kelvins/municipios-brasileiros) (MIT).

## Exemplos

Exemplos completos de cada operação estão disponíveis no diretório [`examples/`](examples/).

## Testes

```bash
composer test
```

Para executar todas as verificações de qualidade:

```bash
composer quality
```

## Contribuindo

Veja [CONTRIBUTING.md](CONTRIBUTING.md) para detalhes.

## Créditos

Este pacote teve como base o trabalho do projeto original [nfse-nacional](https://github.com/Rainzart/nfse-nacional) de **Fernando Friedrich**, que por sua vez foi construído sobre o [NFePHP](https://github.com/nfephp-org) de **Roberto L. Machado**.

Agradecimento a todos os contribuidores que ajudaram a evoluir este projeto.

## Licença

MIT. Veja [LICENSE](LICENSE).
