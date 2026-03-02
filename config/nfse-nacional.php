<?php

use Pulsar\NfseNacional\Enums\NfseAmbiente;

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
];
