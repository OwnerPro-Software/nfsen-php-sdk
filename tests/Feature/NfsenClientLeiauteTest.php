<?php

use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Enums\Leiaute;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Xml\DpsBuilder;
use OwnerPro\Nfsen\Xml\Nt009Scope;

covers(NfsenClient::class, DpsBuilder::class, Nt009Scope::class);

function fakeEmissionAccepted(): void
{
    Http::fake(['*' => Http::response(
        json_decode((string) file_get_contents(__DIR__.'/../fixtures/responses/emitir_sucesso.json'), true),
        201,
    )]);
}

/** @return array<string, mixed> */
function consumerShapedPayload(bool $withIbsCbs): array
{
    $payload = [
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
        'toma' => ['CNPJ' => '11222333000181', 'xNome' => 'Tomador Ltda'],
        'serv' => [
            'cLocPrestacao' => '3501608',
            'cServ' => ['cTribNac' => '010101', 'xDescServ' => 'Serviço', 'cNBS' => '123456789'],
        ],
        'valores' => [
            'vServPrest' => ['vServ' => '100.00'],
            'trib' => [
                'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
                'indTotTrib' => '0',
            ],
        ],
    ];

    if ($withIbsCbs) {
        $payload['IBSCBS'] = [
            'finNFSe' => '0',
            'indFinal' => '0',
            'cIndOp' => '010101',
            'indDest' => '0',
            'valores' => ['trib' => ['gIBSCBS' => ['CST' => '000', 'cClassTrib' => '000001']]],
        ];
    }

    return $payload;
}

it('sends the production layout by default', function () {
    fakeEmissionAccepted();

    NfsenClient::forStandalone(makePfxContent(), 'secret', '9999999')->emitir(makeDpsNucleo());

    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS'))->not->toContain('finNFSe');
    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS/n:IBSCBS'))->toContain('finNFSe');
});

it('sends the NT 009 layout when asked', function () {
    fakeEmissionAccepted();

    NfsenClient::forStandalone(makePfxContent(), 'secret', '9999999', leiaute: Leiaute::Nt009)->emitir(makeDpsNucleo());

    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS'))->toContain('finNFSe', 'indDest');
});

it('follows nfsen.leiaute from the Laravel config', function () {
    fakeEmissionAccepted();
    config(['nfsen.leiaute' => 'nt009']);

    NfsenClient::for(makePfxContent(), 'secret', '9999999')->emitir(makeDpsNucleo());

    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS'))->toContain('finNFSe');
});

it('lets the leiaute argument override the config', function () {
    fakeEmissionAccepted();
    config(['nfsen.leiaute' => 'nt009']);

    NfsenClient::for(makePfxContent(), 'secret', '9999999', leiaute: Leiaute::V101)->emitir(makeDpsNucleo());

    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS'))->not->toContain('finNFSe');
});

it('falls back to the production layout when the config has no leiaute key', function () {
    fakeEmissionAccepted();
    $config = config('nfsen');
    unset($config['leiaute']);
    config(['nfsen' => $config]);

    NfsenClient::for(makePfxContent(), 'secret', '9999999')->emitir(makeDpsNucleo());

    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS'))->not->toContain('finNFSe');
});

it('uses the leiaute argument when there is no Laravel config', function () {
    fakeEmissionAccepted();
    config(['nfsen' => null]);

    NfsenClient::for(makePfxContent(), 'secret', '9999999', leiaute: Leiaute::Nt009)->emitir(makeDpsNucleo());

    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS'))->toContain('finNFSe');
});

it('falls back to the production layout when there is no Laravel config', function () {
    fakeEmissionAccepted();
    config(['nfsen' => null]);

    NfsenClient::for(makePfxContent(), 'secret', '9999999')->emitir(makeDpsNucleo());

    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS'))->not->toContain('finNFSe');
});

it('rejects an unknown leiaute in the config', function () {
    config(['nfsen.leiaute' => 'v2']);

    expect(fn () => NfsenClient::for(makePfxContent(), 'secret', '9999999'))
        ->toThrow(InvalidArgumentException::class, "Leiaute NFSe inválido: 'v2'.");
});

it('emits a consumer-shaped array payload in both layouts', function (Leiaute $leiaute, bool $withIbsCbs) {
    fakeEmissionAccepted();

    $response = NfsenClient::forStandalone(makePfxContent(), 'secret', '9999999', leiaute: $leiaute)
        ->emitir(consumerShapedPayload($withIbsCbs));

    expect($response->sucesso)->toBeTrue();
    expect(in_array('finNFSe', childNames(sentDpsXml(), '/n:DPS/n:infDPS'), true))->toBe($leiaute === Leiaute::Nt009);
})->with([
    'produção, com IBSCBS' => [Leiaute::V101, true],
    'produção, sem IBSCBS' => [Leiaute::V101, false],
    'NT 009, com IBSCBS' => [Leiaute::Nt009, true],
    'NT 009, sem IBSCBS' => [Leiaute::Nt009, false],
]);

it('builds the replacement DPS in the NT 009 layout', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => makeChaveAcesso(), 'nfseXmlGZipB64' => base64_encode((string) gzencode('<NFSe/>'))], 201)]);

    NfsenClient::forStandalone(makeIcpBrPfxContent(), 'secret', '9999999', leiaute: Leiaute::Nt009)->substituir(
        makeChaveAcesso(),
        new DpsData(makeInfDps(), makePrestadorCnpj(), makeServicoMinimo(), makeValoresMinimo()),
        CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
    );

    expect(nameAfter(sentDpsXml(), '/n:DPS/n:infDPS', 'tpEmit'))->toBe('finNFSe');
    expect(nameAfter(sentDpsXml(), '/n:DPS/n:infDPS', 'cLocEmi'))->toBe('subst');
});

it('reports a custom schemasPath that has no nt009 directory', function () {
    fakeEmissionAccepted();
    $client = NfsenClient::forStandalone(
        makePfxContent(), 'secret', '9999999',
        schemasPath: __DIR__.'/../fixtures',
        leiaute: Leiaute::Nt009,
    );

    expect(fn () => $client->emitir(makeDpsNucleo()))
        ->toThrow(NfseException::class, "Schema XSD não encontrado: 'DPS_v1.01.xsd' em '".__DIR__."/../fixtures/nt009'.");
});

it('reads the NT 009 schemas from the nt009 directory of a custom schemasPath', function () {
    fakeEmissionAccepted();
    $client = NfsenClient::forStandalone(
        makePfxContent(), 'secret', '9999999',
        schemasPath: __DIR__.'/../../storage/schemes',
        leiaute: Leiaute::Nt009,
    );

    expect($client->emitir(makeDpsNucleo())->sucesso)->toBeTrue();
});

it('keeps cancelling with the NT 009 layout selected', function () {
    Http::fake(['*' => Http::response(
        json_decode((string) file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        201,
    )]);

    $response = NfsenClient::forStandalone(makeIcpBrPfxContent(), 'secret', '9999999', leiaute: Leiaute::Nt009)
        ->cancelar(makeChaveAcesso(), CodigoJustificativaCancelamento::ErroEmissao, 'Erro na emissão da nota');

    expect($response->sucesso)->toBeTrue();
});

it('emits a consumer-shaped payload without cIndOp in the NT 009 layout', function () {
    fakeEmissionAccepted();
    $payload = consumerShapedPayload(withIbsCbs: true);
    unset($payload['IBSCBS']['cIndOp']);
    $payload['IBSCBS']['valores']['trib']['gIBSCBS'] = ['CST' => '410', 'cClassTrib' => '410001'];

    $response = NfsenClient::forStandalone(makePfxContent(), 'secret', '9999999', leiaute: Leiaute::Nt009)->emitir($payload);

    expect($response->sucesso)->toBeTrue();
    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS/n:IBSCBS'))->toBe(['indFinal', 'valores']);
    expect(childNames(sentDpsXml(), '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib'))->toBe(['CST', 'cClassTrib']);
});
