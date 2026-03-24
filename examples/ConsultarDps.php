<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\NfsenClient;

// -------------------------------------------------------------------
// Consultar DPS por ID (Standalone – sem Laravel)
// -------------------------------------------------------------------

$pfxContent = file_get_contents(__DIR__.'/certificado.pfx');
$senha = 'senha_certificado';
$prefeitura = 'PREFEITURA';

$client = NfsenClient::forStandalone(
    pfxContent: $pfxContent,
    senha: $senha,
    prefeitura: $prefeitura,
    ambiente: NfseAmbiente::HOMOLOGACAO,
);

$idDps = 'DPS000000000000000000000000000000000000000000';

$response = $client->consultar()->dps($idDps);

if ($response->sucesso) {
    echo "DPS encontrada!\n";
    echo "Chave NFSe: {$response->chave}\n";
    echo "XML:\n{$response->xml}\n";
} else {
    echo "Falha na consulta:\n";
    foreach ($response->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} – {$erro->descricao}\n";
    }
}
