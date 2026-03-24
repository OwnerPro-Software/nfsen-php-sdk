<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\NfsenClient;

// -------------------------------------------------------------------
// Consultar eventos de uma NFSe (Standalone – sem Laravel)
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

$chaveNfse = '00000000000000000000000000000000000000000000000000';

$response = $client->consultar()->eventos(
    chave: $chaveNfse,
    tipoEvento: TipoEvento::CancelamentoPorIniciativaPrestador,
    nSequencial: 1,
);

if ($response->sucesso) {
    echo "Evento encontrado!\n";
    echo "XML:\n{$response->xml}\n";
} else {
    echo "Falha na consulta de eventos:\n";
    foreach ($response->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} – {$erro->descricao}\n";
    }
}
