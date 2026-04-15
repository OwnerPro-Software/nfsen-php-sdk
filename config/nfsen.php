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

    // DANFSE auto-render. Quando `enabled=true`, `NfsenClient::for()` anexa o PDF
    // ao NfseResponse em emitir/emitirDecisaoJudicial/substituir/consultar()->nfse().
    // Em multi-tenant, passe o array explicitamente em NfsenClient::for(danfse: [...]).
    'danfse' => [
        'enabled' => (bool) env('NFSE_DANFSE_AUTO', false),
        // logo_path: se não setado (null), DanfseConfig usa o logo padrão embutido do pacote.
        // Para emitir sem logo algum, construir o cliente via código: NfsenClient::for(danfse: ['logo_path' => false]).
        // Não há forma de representar `false` via env (sempre string/null), e essa é uma escolha rara.
        'logo_path' => env('NFSE_DANFSE_LOGO_PATH'),
        'logo_data_uri' => env('NFSE_DANFSE_LOGO_DATA_URI'),
        // Bloco municipality é incluído apenas quando MUN_NAME tem valor real (!= ''/null).
        // env() retorna '' quando a var existe no .env mas está sem valor (NFSE_DANFSE_MUN_NAME=),
        // retorna null quando a var não está setada. Comparação !== '' cobre ambos os casos (default '').
        'municipality' => env('NFSE_DANFSE_MUN_NAME', '') !== '' ? [
            'name' => env('NFSE_DANFSE_MUN_NAME'),
            'department' => env('NFSE_DANFSE_MUN_DEPT', ''),
            'email' => env('NFSE_DANFSE_MUN_EMAIL', ''),
            'logo_path' => env('NFSE_DANFSE_MUN_LOGO_PATH'),
            'logo_data_uri' => env('NFSE_DANFSE_MUN_LOGO_DATA_URI'),
        ] : null,
    ],
];
