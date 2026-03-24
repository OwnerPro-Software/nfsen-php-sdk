<?php

covers(\OwnerPro\Nfsen\NfseClient::class, \OwnerPro\Nfsen\Operations\NfseSubstitutor::class);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\NfseClient;
use OwnerPro\Nfsen\Responses\NfseResponse;

it('substituir returns success when emission succeeds', function (DpsData $dps) {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fake(['*' => Http::response(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->substituir(
        $chave,
        $dps,
        CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
        'Desenquadramento do Simples Nacional',
    );

    expect($response)->toBeInstanceOf(NfseResponse::class);
    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe($chaveSub);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $req) => $req->method() === 'POST' && isset($req['dpsXmlGZipB64']));
})->with('dpsData');

it('substituir returns failure when emission is rejected', function (DpsData $dps) {
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'DPS inválido', 'codigo' => 'E001']]], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->substituir(
        makeChaveAcesso(),
        $dps,
        CodigoJustificativaSubstituicao::Outros,
        'Motivo qualquer',
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toContain('DPS inválido');
})->with('dpsData');

it('substituir accepts string codigoMotivo and coerces to enum', function (DpsData $dps) {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fake(['*' => Http::response(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->substituir($chave, $dps, '01', 'Desenquadramento');

    expect($response->sucesso)->toBeTrue();
})->with('dpsData');

it('substituir throws ValueError for invalid string codigoMotivo', function (DpsData $dps) {
    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->substituir(makeChaveAcesso(), $dps, 'INVALID', 'Motivo'))
        ->toThrow(ValueError::class);
})->with('dpsData');

it('substituir throws InvalidArgumentException for invalid chaveAcesso', function (DpsData $dps) {
    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->substituir('INVALID_CHAVE', $dps, CodigoJustificativaSubstituicao::Outros, 'Outro motivo'))
        ->toThrow(\InvalidArgumentException::class, 'chaveAcesso inválida');
})->with('dpsData');

it('substituir throws HttpException on server error during emission', function (DpsData $dps) {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->substituir(
        makeChaveAcesso(),
        $dps,
        CodigoJustificativaSubstituicao::Outros,
        'Outro motivo para substituicao',
    ))->toThrow(\OwnerPro\Nfsen\Exceptions\HttpException::class);
})->with('dpsData');

it('substituir accepts array DPS and converts to DpsData', function () {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fake(['*' => Http::response(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->substituir(
        $chave,
        [
            'infDPS' => [
                'tpAmb' => '2',
                'dhEmi' => '2026-02-27T10:00:00-03:00',
                'verAplic' => '1.0',
                'serie' => '1',
                'nDPS' => '1',
                'dCompet' => '2026-02-27',
                'tpEmit' => '1',
                'cLocEmi' => '3501608',
            ],
            'prest' => [
                'CNPJ' => '12345678000195',
                'regTrib' => ['opSimpNac' => '1', 'regEspTrib' => '0'],
            ],
            'serv' => [
                'cLocPrestacao' => '3501608',
                'cServ' => ['cTribNac' => '010101', 'xDescServ' => 'Servico', 'cNBS' => '123456789'],
            ],
            'valores' => [
                'vServPrest' => ['vServ' => '100.00'],
                'trib' => [
                    'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
                    'indTotTrib' => '0',
                ],
            ],
        ],
        CodigoJustificativaSubstituicao::Outros,
        'Substituicao por correcao de dados',
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe($chaveSub);
});

it('substituir injects subst into DPS XML payload', function (DpsData $dps) {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fake(['*' => Http::response(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $client->substituir($chave, $dps, CodigoJustificativaSubstituicao::Outros, 'Motivo para substituicao da nota fiscal');

    Http::assertSent(function (Request $req) use ($chave) {
        if (! isset($req['dpsXmlGZipB64'])) {
            return false;
        }

        $xml = gzdecode(base64_decode($req['dpsXmlGZipB64']));

        return str_contains($xml, '<chSubstda>'.$chave.'</chSubstda>');
    });
})->with('dpsData');

it('substituir does not inject xMotivo when descricao is null', function (DpsData $dps) {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fake(['*' => Http::response(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $client->substituir($chave, $dps, CodigoJustificativaSubstituicao::EnquadramentoSimplesNacional);

    Http::assertSent(function (Request $req) {
        if (! isset($req['dpsXmlGZipB64'])) {
            return false;
        }

        $xml = gzdecode(base64_decode($req['dpsXmlGZipB64']));

        return str_contains($xml, '<chSubstda>') && ! str_contains($xml, '<xMotivo>');
    });
})->with('dpsData');
