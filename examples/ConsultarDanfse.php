<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\NfseClient;

// -------------------------------------------------------------------
// Consultar DANFSE – PDF binário (Standalone – sem Laravel)
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
    file_put_contents('danfse.pdf', $response->pdf);
    echo "DANFSE salvo em danfse.pdf\n";
} else {
    echo "Falha na consulta do DANFSE:\n";
    foreach ($response->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} – {$erro->descricao}\n";
    }
}
