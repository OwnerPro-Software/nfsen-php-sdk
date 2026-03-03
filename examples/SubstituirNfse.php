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
$chaveSubstituta = '11111111111111111111111111111111111111111111111111';

$response = $client->substituir(
    chave: $chaveOriginal,
    chaveSubstituta: $chaveSubstituta,
    codigoMotivo: CodigoJustificativaSubstituicao::Outros,
    descricao: 'Substituição por correção de dados',
);

if ($response->sucesso) {
    echo "NFSe substituída com sucesso!\n";
    echo "Chave: {$response->chave}\n";
} else {
    echo "Falha na substituição:\n";
    foreach ($response->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} – {$erro->descricao}\n";
    }
}
