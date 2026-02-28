<?php

use Pulsar\NfseNacional\Enums\NfseAmbiente;

return [
    'ambiente' => env('NFSE_AMBIENTE', NfseAmbiente::HOMOLOGACAO->value),
    'prefeitura' => env('NFSE_PREFEITURA', null),
    'certificado' => [
        'path' => env('NFSE_CERT_PATH'),
        'senha' => env('NFSE_CERT_SENHA'),
    ],
    'timeout' => env('NFSE_TIMEOUT', 30),
    'signing_algorithm' => env('NFSE_SIGNING_ALGORITHM', 'sha1'),
    'ssl_verify' => env('NFSE_SSL_VERIFY', true),
];
