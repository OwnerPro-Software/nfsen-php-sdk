<?php

covers(\Pulsar\NfseNacional\NfseClient::class, \Pulsar\NfseNacional\Operations\NfseSubstitutor::class);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\NfseClient;
use Pulsar\NfseNacional\Responses\SubstituicaoResponse;
use Pulsar\NfseNacional\Support\GzipCompressor;

it('substituir returns success when both emission and event succeed', function (DpsData $dps) {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->substituir(
        $chave,
        $dps,
        CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
        'Desenquadramento do Simples Nacional',
    );

    expect($response)->toBeInstanceOf(SubstituicaoResponse::class);
    expect($response->sucesso)->toBeTrue();
    expect($response->emissao->sucesso)->toBeTrue();
    expect($response->emissao->chave)->toBe($chaveSub);
    expect($response->evento)->not->toBeNull();
    expect($response->evento->sucesso)->toBeTrue();
    expect($response->evento->chave)->toBe($chave);
    expect($response->evento->xml)->not->toBeNull();

    Http::assertSentInOrder([
        fn (Request $req) => $req->method() === 'POST' &&
            isset($req['dpsXmlGZipB64']),
        fn (Request $req) => str_contains($req->url(), $chave.'/eventos') &&
            $req->method() === 'POST' &&
            isset($req['pedidoRegistroEventoXmlGZipB64']),
    ]);
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
    expect($response->emissao->sucesso)->toBeFalse();
    expect($response->emissao->erros[0]->descricao)->toContain('DPS inválido');
    expect($response->evento)->toBeNull();
})->with('dpsData');

it('substituir returns failure when event is rejected', function (DpsData $dps) {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['erro' => ['descricao' => 'NFSe não encontrada', 'codigo' => 'E404']], 200);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->substituir(
        $chave,
        $dps,
        CodigoJustificativaSubstituicao::Outros,
        'Outro motivo para substituicao',
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->emissao->sucesso)->toBeTrue();
    expect($response->emissao->chave)->toBe($chaveSub);
    expect($response->evento)->not->toBeNull();
    expect($response->evento->sucesso)->toBeFalse();
    expect($response->evento->erros[0]->descricao)->toContain('não encontrada');
})->with('dpsData');

it('substituir accepts string codigoMotivo and coerces to enum', function (DpsData $dps) {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->substituir($chave, $dps, '01', 'Desenquadramento');

    expect($response->sucesso)->toBeTrue();
    expect($response->emissao->sucesso)->toBeTrue();
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
    ))->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
})->with('dpsData');

it('substituir throws HttpException on server error during event', function (DpsData $dps) {
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push('Server Error', 500);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->substituir(
        makeChaveAcesso(),
        $dps,
        CodigoJustificativaSubstituicao::Outros,
        'Outro motivo para substituicao',
    ))->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
})->with('dpsData');

it('substituir throws NfseException when cert has no CNPJ nor CPF for event', function (DpsData $dps) {
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->substituir(
        makeChaveAcesso(),
        $dps,
        CodigoJustificativaSubstituicao::Outros,
        'Outro motivo para substituicao',
    ))->toThrow(NfseException::class, 'Certificado não contém CNPJ nem CPF');
})->with('dpsData');

it('substituir sends event XML without xMotivo when descricao is null', function (DpsData $dps) {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->substituir($chave, $dps, CodigoJustificativaSubstituicao::EnquadramentoSimplesNacional);

    expect($response->sucesso)->toBeTrue();

    Http::assertSent(function (Request $req) {
        if (! isset($req['pedidoRegistroEventoXmlGZipB64'])) {
            return false;
        }

        $xml = gzdecode(base64_decode($req['pedidoRegistroEventoXmlGZipB64']));

        return ! str_contains($xml, '<nPedRegEvento>') &&
            ! str_contains($xml, '<xMotivo>');
    });
})->with('dpsData');

it('substituir uses Americana custom URL without operation path', function (DpsData $dps) {
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '3501608');
    $client->substituir(
        makeChaveAcesso(),
        $dps,
        CodigoJustificativaSubstituicao::Outros,
        'Outro motivo para substituicao',
    );

    Http::assertSent(fn (Request $req) => $req->url() === 'https://americanahomologacao.nfe.com.br/api/adn/dps/recepcao' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
})->with('dpsData');

it('substituir throws NfseException when gzip compression fails', function (DpsData $dps) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'X'], 201)]);

    $compressor = Mockery::mock(GzipCompressor::class);
    $compressor->shouldReceive('__invoke')->andReturn(false);

    $client = makeNfseClient(gzipCompressor: $compressor, pfxContent: makeIcpBrPfxContent());

    expect(fn () => $client->substituir(
        makeChaveAcesso(),
        $dps,
        CodigoJustificativaSubstituicao::Outros,
        'Outro motivo para substituicao',
    ))->toThrow(NfseException::class, 'comprimir XML');
})->with('dpsData');

it('substituir accepts array DPS and converts to DpsData', function () {
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201);

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
    expect($response->emissao->chave)->toBe($chaveSub);
});
