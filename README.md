# NFSe Nacional

[![CI](https://github.com/jonathanpmartins/nfse-nacional/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/jonathanpmartins/nfse-nacional/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/jonathanpmartins/nfse-nacional.svg)](https://packagist.org/packages/jonathanpmartins/nfse-nacional)
[![PHP Version](https://img.shields.io/packagist/php-v/jonathanpmartins/nfse-nacional.svg)](https://packagist.org/packages/jonathanpmartins/nfse-nacional)
[![License](https://img.shields.io/packagist/l/jonathanpmartins/nfse-nacional.svg)](LICENSE)

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
composer require jonathanpmartins/nfse-nacional
```

## Configuração

### Laravel

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=nfse-nacional-config
```

Adicione as variáveis de ambiente no `.env`:

```env
NFSE_AMBIENTE=2                   # 1 = Producao, 2 = Homologacao
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
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\NfseClient;

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

### Cancelar NFSe

```php
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;

$response = $client->cancelar(
    chave: '00000000000000000000000000000000000000000000000000',
    codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
    descricao: 'Erro na emissao da nota fiscal',
);
```

Codigos de cancelamento: `ErroEmissao`, `ServicoNaoPrestado`, `Outros`.

### Substituir NFSe

```php
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;

$response = $client->substituir(
    chave: '00000000000000000000000000000000000000000000000000',
    chaveSubstituta: '11111111111111111111111111111111111111111111111111',
    codigoMotivo: CodigoJustificativaSubstituicao::Outros,
    descricao: 'Substituicao por correcao de dados',
);
```

### Consultas

```php
use Pulsar\NfseNacional\Enums\TipoEvento;

// Consultar NFSe por chave de acesso
$response = $client->consultar()->nfse($chave);

// Consultar DPS por ID
$response = $client->consultar()->dps($idDps);

// Obter PDF do DANFSE
$response = $client->consultar()->danfse($chave);
// $response->pdf contém o conteúdo binário do PDF
file_put_contents('danfse.pdf', $response->pdf);

// Consultar eventos
$response = $client->consultar()->eventos(
    chave: $chave,
    tipoEvento: TipoEvento::CancelamentoPorIniciativaPrestador,
    nSequencial: 1,
);

// Verificar se DPS foi processada
$processada = $client->consultar()->verificarDps($idDps); // true ou false
```

### Laravel Facade

```php
use Pulsar\NfseNacional\Facades\NfseNacional;

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
use Pulsar\NfseNacional\Enums\NfseAmbiente;

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
| `NfseFailed` | `operacao`, `message` | Falha na operação |

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
