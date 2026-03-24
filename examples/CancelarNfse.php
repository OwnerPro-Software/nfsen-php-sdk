<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\NfsenClient;

// -------------------------------------------------------------------
// Cancelar NFSe (Standalone – sem Laravel)
// -------------------------------------------------------------------

$pfxContent = file_get_contents(__DIR__.'/certificado.pfx');
$senha = 'senha_certificado';
$prefeitura = 'PREFEITURA';

$client = NfsenClient::forStandalone(
    pfxContent: $pfxContent,
    senha: $senha,
    prefeitura: $prefeitura,
    ambiente: NfseAmbiente::PRODUCAO,
);

$chaveNfse = '00000000000000000000000000000000000000000000000000';

$response = $client->cancelar(
    chave: $chaveNfse,
    codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao, // ou ::ServicoNaoPrestado, ::Outros
    descricao: 'Erro na emissão da nota fiscal',
);

if ($response->sucesso) {
    echo "NFSe cancelada com sucesso!\n";
    echo "Chave: {$response->chave}\n";
} else {
    echo "Falha no cancelamento:\n";
    foreach ($response->erros as $erro) {
        echo "  [{$erro->codigo}] {$erro->mensagem} – {$erro->descricao}\n";
    }
}
