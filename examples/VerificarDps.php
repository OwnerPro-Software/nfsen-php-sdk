<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\NfsenClient;

// -------------------------------------------------------------------
// Verificar se uma DPS foi processada (Standalone – sem Laravel)
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

$processada = $client->consultar()->verificarDps($idDps);

echo $processada
    ? "DPS já foi processada.\n"
    : "DPS ainda não foi processada.\n";
