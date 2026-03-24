<?php

declare(strict_types=1);

// -------------------------------------------------------------------
// Exemplos usando a Facade do Laravel (dentro de uma aplicação Laravel)
// -------------------------------------------------------------------
// Configurar as variáveis de ambiente no .env:
//
//   NFSE_AMBIENTE=2
//   NFSE_PREFEITURA=PREFEITURA
//   NFSE_CERT_PATH=/caminho/certificado.pfx
//   NFSE_CERT_SENHA=senha_certificado
//   NFSE_TIMEOUT=30
//   NFSE_CONNECT_TIMEOUT=10
//   NFSE_SIGNING_ALGORITHM=sha1
//   NFSE_SSL_VERIFY=true
//
// Publicar a config: php artisan vendor:publish --tag=nfsen-config
// -------------------------------------------------------------------

use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Facades\Nfsen;

// -- Emitir NFSe ---------------------------------------------------
$response = Nfsen::emitir([
    'infDPS' => [
        'tpAmb' => '2',
        'dhEmi' => now()->format('Y-m-d\TH:i:sP'),
        'verAplic' => 'MeuSistema_v1.0',
        'serie' => '1',
        'nDPS' => '1',
        'dCompet' => now()->format('Y-m-d'),
        'tpEmit' => '1',
        'cLocEmi' => '3550308',
    ],
    'prest' => [
        'CNPJ' => '00000000000000',
        'fone' => '11999999999',
        'regTrib' => [
            'opSimpNac' => '2',
            'regEspTrib' => '0',
        ],
    ],
    'toma' => [
        'xNome' => 'Tomador Exemplo Ltda',
        'CPF' => '00000000000',
        'end' => [
            'xLgr' => 'Rua Exemplo',
            'nro' => '100',
            'xBairro' => 'Centro',
            'endNac' => ['cMun' => '3550308', 'CEP' => '01001000'],
        ],
    ],
    'serv' => [
        'cLocPrestacao' => '3550308',
        'cServ' => [
            'cTribNac' => '010101',
            'xDescServ' => 'Desenvolvimento de software sob encomenda',
            'cNBS' => '116030000',
            'cIntContrib' => '1234',
        ],
    ],
    'valores' => [
        'vServPrest' => ['vServ' => '1000.00'],
        'trib' => [
            'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
            'indTotTrib' => '0',
        ],
    ],
]);

// -- Cancelar NFSe -------------------------------------------------
$response = Nfsen::cancelar(
    chave: '00000000000000000000000000000000000000000000000000',
    codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
    descricao: 'Erro na emissão',
);

// -- Consultar NFSe ------------------------------------------------
$response = Nfsen::consultar()->nfse('00000000000000000000000000000000000000000000000000');

// -- Consultar DANFSE (URL do PDF) --------------------------------
$danfse = Nfsen::consultar()->danfse('00000000000000000000000000000000000000000000000000');

// -- Consultar eventos ---------------------------------------------
$eventos = Nfsen::consultar()->eventos(
    chave: '00000000000000000000000000000000000000000000000000',
    tipoEvento: TipoEvento::CancelamentoPorIniciativaPrestador,
    nSequencial: 1,
);

// -- Usar certificado diferente por requisição ---------------------
$client = Nfsen::for($pfxContent, $senha, 'PREFEITURA');
$response = $client->emitir($dps);
