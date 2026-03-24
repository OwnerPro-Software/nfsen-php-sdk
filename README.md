# NFSe Nacional

[![CI](https://github.com/OwnerPro-Software/nfsen/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/OwnerPro-Software/nfsen/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/ownerpro/nfsen.svg)](https://packagist.org/packages/ownerpro/nfsen)
[![PHP Version](https://img.shields.io/packagist/php-v/ownerpro/nfsen.svg)](https://packagist.org/packages/ownerpro/nfsen)
[![License](https://img.shields.io/packagist/l/ownerpro/nfsen.svg)](LICENSE)

Pacote PHP para emissão, cancelamento, substituição e consulta de **NFSe Padrão Nacional** ([nfse.gov.br](https://www.nfse.gov.br/)) via API REST. Funciona com Laravel 11/12 ou standalone (sem framework).

## Funcionalidades

- Emissão de NFSe (`emitir`) e emissão por decisão judicial (`emitirDecisaoJudicial`)
- Cancelamento de NFSe (`cancelar`)
- Substituição de NFSe (`substituir`)
- Consulta por chave de acesso, DPS, DANFSE (URL do PDF), eventos e verificação de DPS
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
composer require ownerpro/nfsen
```

## Configuração

### Laravel

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=nfse-nacional-config
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
```

### Standalone (sem Laravel)

```php
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\NfseClient;

$client = NfseClient::forStandalone(
    pfxContent: file_get_contents('/caminho/certificado.pfx'),
    senha: 'senha_certificado',
    prefeitura: '3550308',
    ambiente: NfseAmbiente::HOMOLOGACAO,
);
```

## Uso

### Emitir NFSe

```php
$response = $client->emitir([
    'infDPS' => [
        'tpAmb'    => '2',                          // 1 = Producao, 2 = Homologacao
        'dhEmi'    => date('Y-m-d\TH:i:sP'),       // Data/hora emissao
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

// Obter PDF do DANFSE
$response = $client->consultar()->danfse($chave);
// $response->pdf contém o conteúdo binário do PDF
file_put_contents('danfse.pdf', $response->pdf);

// Consultar eventos (tipoEvento é obrigatório)
$response = $client->consultar()->eventos(
    chave: $chave,
    tipoEvento: TipoEvento::CancelamentoPorIniciativaPrestador, // e101101
    nSequencial: 1,
);
// Tipos disponíveis: CancelamentoPorIniciativaPrestador, CancelamentoPorIniciativaFisco,
// CancelamentoPorSubstituicao, AnulacaoCancelamento

// Verificar se DPS foi processada
$processada = $client->consultar()->verificarDps($idDps); // true ou false
```

### Laravel Facade

```php
use OwnerPro\Nfsen\Facades\NfseNacional;

// Emitir
$response = NfseNacional::emitir($dps);

// Cancelar
$response = NfseNacional::cancelar($chave, $motivo, $descricao);

// Consultar
$response = NfseNacional::consultar()->nfse($chave);
$danfse   = NfseNacional::consultar()->danfse($chave);

// Usar certificado diferente por requisicao
$client = NfseNacional::for($pfxContent, $senha, '3550308');
$response = $client->emitir($dps);

// Sobrescrever ambiente (ignorar config)
use OwnerPro\Nfsen\Enums\NfseAmbiente;

$client = NfseNacional::for($pfxContent, $senha, '3550308', NfseAmbiente::PRODUCAO);
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
| `NfseRejected` | `operacao`, `codigoErro` | Operação rejeitada pela API |
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

### `EventsResponse`

Retornado por `consultar()->eventos()`.

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `sucesso` | `bool` | Se a consulta teve sucesso |
| `xml` | `?string` | XML do evento |
| `erros` | `list<ProcessingMessage>` | Erros de processamento |
| `tipoAmbiente` | `?int` | 1 = Produção, 2 = Homologação |
| `versaoAplicativo` | `?string` | Versão do aplicativo da SEFIN |
| `dataHoraProcessamento` | `?string` | Data/hora do processamento |

### `ProcessingMessage`

Representa uma mensagem de erro ou alerta da API:

| Propriedade | Tipo | Descricao |
|-------------|------|-----------|
| `mensagem` | `?string` | Mensagem principal |
| `codigo` | `?string` | Código do erro/alerta |
| `descricao` | `?string` | Descrição detalhada |
| `complemento` | `?string` | Informação complementar |

## Exceções

| Exceção | Pai | Quando |
|---------|-----|--------|
| `NfseException` | `RuntimeException` | Erros gerais (XML inválido, falha de compressão, etc.) |
| `HttpException` | `NfseException` | Erros HTTP 5xx sem corpo JSON. Acesse `getResponseBody()` para detalhes |
| `CertificateExpiredException` | `NfseException` | Certificado PFX/P12 expirado |
| `InvalidDpsArgument` | `InvalidArgumentException` | Campos mutuamente exclusivos ou obrigatórios violados na DPS |

```php
use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;
use OwnerPro\Nfsen\Exceptions\NfseException;

try {
    $response = $client->emitir($dps);
} catch (CertificateExpiredException $e) {
    // Certificado expirado -- renovar
} catch (InvalidDpsArgument $e) {
    // Dados da DPS inválidos -- corrigir payload
} catch (HttpException $e) {
    // Erro HTTP -- $e->getCode() para status, $e->getResponseBody() para corpo
} catch (NfseException $e) {
    // Outros erros (XML, compressão, etc.)
}
```

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
