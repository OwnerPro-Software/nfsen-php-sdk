<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\Decorators\ConsulterWithDanfse;
use OwnerPro\Nfsen\Operations\Decorators\EmitterWithDanfse;
use OwnerPro\Nfsen\Operations\Decorators\SubstitutorWithDanfse;
use Smalot\PdfParser\Parser as PdfParser;

covers(
    NfsenClient::class,
    EmitterWithDanfse::class,
    SubstitutorWithDanfse::class,
    ConsulterWithDanfse::class,
);

// Payload API autorizado (chaveAcesso + nfseXmlGZipB64) no formato esperado por
// NfseEmitter; conteúdo XML vem do fixture de NFS-e autorizada. Função prefixada
// com `makeDanfseAutorizadoApiResponse` para minimizar risco de colisão no namespace
// global do pest — helpers de teste em tests/helpers.php seguem padrão similar.
function makeDanfseAutorizadoApiResponse(): array
{
    $xml = (string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-autorizada.xml');
    $gzip = base64_encode((string) gzencode($xml));

    return [
        'chaveAcesso' => '3303302112233450000195000000000000100000000001',
        'nfseXmlGZipB64' => $gzip,
        'idDps' => 'DPS1',
        'tipoAmbiente' => 2,
        'versaoAplicativo' => '1.0',
        'dataHoraProcessamento' => '2026-04-15T10:00:00-03:00',
    ];
}

it('forStandalone com danfse array: emit retorna pdf %PDF- e conteúdo esperado', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    $client = NfsenClient::forStandalone(
        pfxContent: makePfxContent(),
        senha: 'secret',
        prefeitura: '9999999',
        validateIdentity: false,
        danfse: ['logo_path' => false],
    );

    $resp = $client->emitir($data);

    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->not->toBeNull();
    expect($resp->pdf)->toStartWith('%PDF-');

    $text = (new PdfParser)->parseContent((string) $resp->pdf)->getText();

    expect($text)->toContain('3303302112233450000195000000000000100000000001');
})->with('dpsData');

it('forStandalone sem danfse: pdf é null e pdfErrors vazio', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    $client = NfsenClient::forStandalone(
        pfxContent: makePfxContent(),
        senha: 'secret',
        prefeitura: '9999999',
        validateIdentity: false,
    );

    $resp = $client->emitir($data);

    expect($resp->sucesso)->toBeTrue();
    expect($resp->pdf)->toBeNull();
    expect($resp->pdfErrors)->toBe([]);
})->with('dpsData');

// NOTA dos testes NfsenClient::for(...) abaixo: quando a chamada passa $pfxContent e $senha
// explícitos, NfsenClient::for() NÃO consulta nfsen.certificado.* do config — ela delega a
// forStandalone() que recebe o cert direto. Os keys 'nfsen.certificado.path', 'senha',
// 'prefeitura' abaixo são inofensivos (dead config) mas ajudam caso o teste seja refatorado
// para exercitar app(NfsenClient::class) (ServiceProvider path). Mantidos por defense-in-depth.

it('for() com config.enabled=true ativa auto-render', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => true,
        'nfsen.danfse.logo_path' => false,
        'nfsen.danfse.municipality' => null,
    ]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    $resp = $client->emitir($data);

    expect($resp->pdf)->not->toBeNull();
})->with('dpsData');

it('for() com config.enabled=false não ativa auto-render', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => false,
    ]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    $resp = $client->emitir($data);

    expect($resp->pdf)->toBeNull();
})->with('dpsData');

it('for(danfse: [...]) sobrescreve config global — município do array aparece', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => true,
        'nfsen.danfse.municipality' => ['name' => 'Município Global'],
    ]);

    $client = NfsenClient::for(
        makePfxContent(),
        'secret',
        '9999999',
        danfse: [
            'logo_path' => false,
            'municipality' => ['name' => 'Município Tenant X'],
        ],
    );

    $resp = $client->emitir($data);

    expect($resp->pdf)->not->toBeNull();

    $text = (new PdfParser)->parseContent((string) $resp->pdf)->getText();
    expect($text)->toContain('Município Tenant X');
    expect($text)->not->toContain('Município Global');
})->with('dpsData');

it('for(danfse: false) força desligar mesmo com config.enabled=true', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => true,
        'nfsen.danfse.logo_path' => false,
    ]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999', danfse: false);

    $resp = $client->emitir($data);

    expect($resp->pdf)->toBeNull();
})->with('dpsData');

it('forStandalone(danfse: false) equivale a null (paridade)', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    $client = NfsenClient::forStandalone(
        pfxContent: makePfxContent(),
        senha: 'secret',
        prefeitura: '9999999',
        validateIdentity: false,
        danfse: false,
    );

    $resp = $client->emitir($data);

    expect($resp->pdf)->toBeNull();
    expect($resp->pdfErrors)->toBe([]);
})->with('dpsData');

it('for() com municipality=null (ternário do config retornou null): boot OK e emit gera PDF', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.validate_identity' => false,
        'nfsen.danfse.enabled' => true,
        'nfsen.danfse.logo_path' => false,
        'nfsen.danfse.municipality' => null, // estado resultante quando env NFSE_DANFSE_MUN_NAME não setado (ternário do config/nfsen.php)
    ]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');
    $resp = $client->emitir($data);

    // Boot não explodiu + render rodou sem cabeçalho municipal (name filtrado upstream).
    expect($resp->pdf)->not->toBeNull();
    expect($resp->pdfErrors)->toBe([]);
})->with('dpsData');

it('defesa em profundidade: municipality {name: null} vira ausente — emit também gera PDF', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    // Cenário hipotético: array parcial chega via código (não pelo config/nfsen.php
    // que já tem o ternário). fromArray deve tolerar sem throw E render deve completar.
    $client = NfsenClient::forStandalone(
        pfxContent: makePfxContent(),
        senha: 'secret',
        prefeitura: '9999999',
        validateIdentity: false,
        danfse: [
            'logo_path' => false,
            'municipality' => ['name' => null, 'department' => 'X'],
        ],
    );
    $resp = $client->emitir($data);

    expect($resp->pdf)->not->toBeNull();
    expect($resp->pdfErrors)->toBe([]);
})->with('dpsData');

it('for(danfse: []) ativa auto-render com defaults (logo padrão, sem município)', function (DpsData $data) {
    Http::fake(['*' => Http::response(makeDanfseAutorizadoApiResponse(), 201)]);

    config(['nfsen.validate_identity' => false]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999', danfse: []);

    $resp = $client->emitir($data);

    expect($resp->pdf)->not->toBeNull();
    expect($resp->pdf)->toStartWith('%PDF-');
})->with('dpsData');
