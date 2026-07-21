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

    // Quando true, falhas comprovadamente anteriores ao envio (DNS, TCP, TLS)
    // lançam RequestNotDeliveredException (retry direto seguro) em vez de
    // IndeterminateResultException (reconciliar antes de retry). Opt-in para
    // não alterar catches existentes de IndeterminateResultException.
    'detect_not_delivered' => (bool) env('NFSE_DETECT_NOT_DELIVERED', false),

    // DANFSE auto-render. Quando `enabled=true`, `NfsenClient::for()` anexa o PDF
    // ao NfseResponse em emitir/emitirDecisaoJudicial/substituir/consultar()->nfse().
    // Sobrescreva por chamada com NfsenClient::for(danfse: true|false).
    //
    // Não há mais o que configurar além disso: o layout inteiro do DANFSe vem da
    // NT 008 e do XML da NFS-e.
    'danfse' => [
        'enabled' => (bool) env('NFSE_DANFSE_AUTO', false),
    ],
];
