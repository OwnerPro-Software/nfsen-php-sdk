<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\NfseClient;

// -------------------------------------------------------------------
// Substituir NFSe (Standalone – sem Laravel)
// -------------------------------------------------------------------

$pfxContent = file_get_contents(__DIR__.'/certificado.pfx');
$senha = 'senha_certificado';
$prefeitura = 'PREFEITURA';

$client = NfseClient::forStandalone(
    pfxContent: $pfxContent,
    senha: $senha,
    prefeitura: $prefeitura,
    ambiente: NfseAmbiente::HOMOLOGACAO,
);

$chaveOriginal = '00000000000000000000000000000000000000000000000000';

// DPS da nota substituta (dados corrigidos)
$dpsSubstituta = [
    'infDPS' => [
        'tpAmb' => '2',
        'dhEmi' => date('Y-m-d\TH:i:sP'),
        'verAplic' => 'MeuSistema_v1.0',
        'serie' => '1',
        'nDPS' => '2',
        'dCompet' => date('Y-m-d'),
        'tpEmit' => '1',
        'cLocEmi' => '3550308',
    ],
    'prest' => [
        'CNPJ' => '00000000000000',
        'regTrib' => [
            'opSimpNac' => '2',
            'regEspTrib' => '0',
        ],
    ],
    'serv' => [
        'cLocPrestacao' => '3550308',
        'cServ' => [
            'cTribNac' => '010101',
            'xDescServ' => 'Desenvolvimento de software sob encomenda',
            'cNBS' => '116030000',
        ],
    ],
    'valores' => [
        'vServPrest' => ['vServ' => '1000.00'],
        'trib' => [
            'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
            'indTotTrib' => '0',
        ],
    ],
];

$response = $client->substituir(
    chave: $chaveOriginal,
    dps: $dpsSubstituta,
    codigoMotivo: CodigoJustificativaSubstituicao::Outros,
    descricao: 'Substituicao por correcao de dados',
);

if ($response->sucesso) {
    echo "NFSe substituida com sucesso!\n";
    echo "Chave substituta: {$response->emissao->chave}\n";
} elseif (! $response->emissao->sucesso) {
    echo "Falha na emissao da nota substituta:\n";
    foreach ($response->emissao->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} - {$erro->descricao}\n";
    }
} else {
    echo "Nota emitida (chave: {$response->emissao->chave}), mas registro do evento falhou:\n";
    foreach ($response->evento->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} - {$erro->descricao}\n";
    }
}
