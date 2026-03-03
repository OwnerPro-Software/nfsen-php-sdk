<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\NfseClient;

// -------------------------------------------------------------------
// Consultar DANFSE – URL do PDF (Standalone – sem Laravel)
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

$chaveNfse = '00000000000000000000000000000000000000000000000000';

$response = $client->consultar()->danfse($chaveNfse);

if ($response->sucesso) {
    echo "DANFSE disponível!\n";
    echo "URL do PDF: {$response->url}\n";
} else {
    echo "Falha na consulta do DANFSE:\n";
    foreach ($response->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} – {$erro->descricao}\n";
    }
}
