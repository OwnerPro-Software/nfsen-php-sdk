<?php

use OwnerPro\Nfsen\Enums\NfseAmbiente;

return [
    'ambiente' => env('NFSE_AMBIENTE', NfseAmbiente::HOMOLOGACAO->value),
    'prefeitura' => env('NFSE_PREFEITURA', null),
    'certificado' => [
        'path' => env('NFSE_CERT_PATH'),
        'senha' => env('NFSE_CERT_SENHA'),
    ],
    'timeout' => (int) env('NFSE_TIMEOUT', 30),
    'connect_timeout' => (int) env('NFSE_CONNECT_TIMEOUT', 10),
    'signing_algorithm' => env('NFSE_SIGNING_ALGORITHM', 'sha1'),
    'ssl_verify' => (bool) env('NFSE_SSL_VERIFY', true),
    'validate_identity' => (bool) env('NFSE_VALIDATE_IDENTITY', true),

    // Anexa o DANFSe em PDF ao NfseResponse de emitir(), substituir() e
    // consultar()->nfse(). Desligado por padrão: são ~300ms e ~15KB por nota,
    // gastos mesmo que ninguém abra o PDF. Sem a flag, gere sob demanda com
    // $client->danfse()->toPdf($xml).
    //
    // Sobrescreva por chamada com NfsenClient::for(danfse: true|false). O documento em
    // si não tem configuração: o layout vem da NT 008 e o conteúdo, do XML.
    'auto_danfse' => (bool) env('NFSE_AUTO_DANFSE', false),
];
