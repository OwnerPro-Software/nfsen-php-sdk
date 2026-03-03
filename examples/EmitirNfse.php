<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\NfseClient;

// -------------------------------------------------------------------
// Emitir NFSe (Standalone – sem Laravel)
// -------------------------------------------------------------------

$pfxContent = file_get_contents(__DIR__.'/certificado.pfx');
$senha = 'senha_certificado';
$prefeitura = 'PREFEITURA'; // código da prefeitura conforme prefeituras.json

$client = NfseClient::forStandalone(
    pfxContent: $pfxContent,
    senha: $senha,
    prefeitura: $prefeitura,
    ambiente: NfseAmbiente::HOMOLOGACAO,
);

// Aceita array ou DpsData DTO – aqui usamos array por simplicidade.
$dps = [
    'infDPS' => [
        'tpAmb' => '2',                                  // 1 = Produção, 2 = Homologação
        'dhEmi' => date('Y-m-d\TH:i:sP'),               // Data/hora emissão
        'verAplic' => 'MeuSistema_v1.0',
        'serie' => '1',
        'nDPS' => '1',
        'dCompet' => date('Y-m-d'),                      // Data de competência
        'tpEmit' => '1',                                  // 1 = Prestador
        'cLocEmi' => '3550308',                           // Código IBGE 7 dígitos
    ],

    'prest' => [
        'CNPJ' => '00000000000000',
        'fone' => '11999999999',
        'regTrib' => [
            'opSimpNac' => '2',   // 1 = Não Optante, 2 = MEI, 3 = ME/EPP
            'regEspTrib' => '0',  // 0 = Nenhum
        ],
    ],

    'toma' => [
        'xNome' => 'Tomador Exemplo Ltda',
        'CPF' => '00000000000',
        'end' => [
            'xLgr' => 'Rua Exemplo',
            'nro' => '100',
            'xBairro' => 'Centro',
            'endNac' => [
                'cMun' => '3550308',
                'CEP' => '01001000',
            ],
        ],
    ],

    'serv' => [
        'cLocPrestacao' => '3550308',
        'cServ' => [
            'cTribNac' => '010101',
            'xDescServ' => 'Desenvolvimento de software sob encomenda',
            'cNBS' => '116030000',
            'cIntContrib' => '1234',
        ],
    ],

    'valores' => [
        'vServPrest' => [
            'vServ' => '1000.00',
        ],
        'trib' => [
            'tribMun' => [
                'tribISSQN' => '1',    // 1 = Operação tributável
                'tpRetISSQN' => '1',   // 1 = Não retido
            ],
            'indTotTrib' => '0',
        ],
    ],
];

$response = $client->emitir($dps);

if ($response->sucesso) {
    echo "NFSe emitida com sucesso!\n";
    echo "Chave: {$response->chave}\n";
    echo "ID DPS: {$response->idDps}\n";
    echo "XML:\n{$response->xml}\n";
} else {
    echo "Falha na emissão:\n";
    foreach ($response->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} – {$erro->descricao}\n";
    }
}
