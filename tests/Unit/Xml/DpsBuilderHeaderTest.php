<?php

use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\DTOs\Dps\Prestador\Prestador;
use Pulsar\NfseNacional\DTOs\Dps\Shared\RegTrib;
use Pulsar\NfseNacional\DTOs\Dps\Tomador\Tomador;
use Pulsar\NfseNacional\Enums\Dps\InfDPS\MotivoEmissaoTI;
use Pulsar\NfseNacional\Enums\Dps\Prestador\OpSimpNac;
use Pulsar\NfseNacional\Enums\Dps\Prestador\RegEspTrib;
use Pulsar\NfseNacional\Xml\DpsBuilder;

function buildDps(DpsData $data): string
{
    return (new DpsBuilder(makeXsdValidator()))->build($data);
}

function parseDpsXml(string $xml): DOMXPath
{
    $doc = new DOMDocument;
    $doc->loadXML($xml);
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

    return $xpath;
}

it('builds xml with DPS root element and correct attributes', function (DpsData $data) {
    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    $dps = $xpath->query('/n:DPS')->item(0);
    expect($dps)->not->toBeNull();
    expect($dps->getAttribute('versao'))->not->toBeEmpty();
    expect($dps->namespaceURI)->toBe('http://www.sped.fazenda.gov.br/nfse');
})->with('dpsData');

it('builds xml with infDPS Id as child of DPS', function (DpsData $data) {
    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps)->not->toBeNull();
    expect($infDps->getAttribute('Id'))->toStartWith('DPS');
})->with('dpsData');

it('includes tpAmb in infDPS', function (DpsData $data) {
    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    $tpAmb = $xpath->query('/n:DPS/n:infDPS/n:tpAmb')->item(0);
    expect($tpAmb->textContent)->toBe('2');
})->with('dpsData');

it('includes cMotivoEmisTI when set', function () {
    $data = new DpsData(
        makeInfDps(cMotivoEmisTI: MotivoEmissaoTI::ImportacaoServico),
        makePrestadorCnpj(),
        null,
        makeServicoMinimo(),
        makeValoresMinimo(),
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    $node = $xpath->query('//n:cMotivoEmisTI')->item(0);
    expect($node)->not->toBeNull();
    expect($node->textContent)->toBe('1');
});

it('includes chNFSeRej when set', function () {
    $data = new DpsData(
        makeInfDps(chNFSeRej: 'CHAVE_REJEITADA_123'),
        makePrestadorCnpj(),
        null,
        makeServicoMinimo(),
        makeValoresMinimo(),
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    $node = $xpath->query('//n:chNFSeRej')->item(0);
    expect($node)->not->toBeNull();
    expect($node->textContent)->toBe('CHAVE_REJEITADA_123');
});

it('includes toma element as child of infDPS when tomador has data', function () {
    $tomador = new Tomador(CNPJ: '98765432000111', xNome: 'Tomador Ltda');

    $data = new DpsData(makeInfDps(), makePrestadorCnpj(), $tomador, makeServicoMinimo(), makeValoresMinimo());

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    $toma = $xpath->query('/n:DPS/n:infDPS/n:toma')->item(0);
    expect($toma)->not->toBeNull();
    expect($xpath->query('n:CNPJ', $toma)->item(0)->textContent)->toBe('98765432000111');
    expect($xpath->query('n:xNome', $toma)->item(0)->textContent)->toBe('Tomador Ltda');
});

it('throws NfseException when scheme file does not exist', function (DpsData $data) {
    $builder = new DpsBuilder(new \Pulsar\NfseNacional\Support\XsdValidator('/nonexistent/path'));

    expect(fn () => $builder->buildAndValidate($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'Schema XSD não encontrado');
})->with('dpsData');

it('throws NfseException on invalid XSD', function () {
    $servico = new \Pulsar\NfseNacional\DTOs\Dps\Servico\Servico(
        cServ: new \Pulsar\NfseNacional\DTOs\Dps\Servico\CodigoServico(
            cTribNac: 'INVALID_LONG_VALUE_THAT_WILL_FAIL_XSD_VALIDATION_BECAUSE_IT_EXCEEDS_MAX_LENGTH',
            xDescServ: 'Serviço',
            cNBS: '123456789',
        ),
        cLocPrestacao: '3501608',
    );

    $data = new DpsData(makeInfDps(), makePrestadorCnpj(), null, $servico, makeValoresMinimo());

    $builder = new DpsBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'XML inválido');
});

it('generates correct Id for CNPJ prestador', function (DpsData $data) {
    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // DPS + cLocEmi(7) + tipo=2(CNPJ) + cnpj(14) + serie(5 padded) + nDPS(15 padded)
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160821234567800019500001000000000000001');
})->with('dpsData');

it('generates correct Id for CPF prestador', function () {
    $prestador = new Prestador(
        CPF: '12345678901',
        regTrib: new RegTrib(opSimpNac: OpSimpNac::NaoOptante, regEspTrib: RegEspTrib::Nenhum),
        xNome: 'Pessoa Física',
    );

    $data = new DpsData(makeInfDps(), $prestador, null, makeServicoMinimo(), makeValoresMinimo());

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // tipo=1(CPF) + CPF left-padded to 14 digits
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160810001234567890100001000000000000001');
});

it('generates Id with max serie and large ndps padding', function () {
    $data = new DpsData(
        makeInfDps(serie: '99999', nDPS: 999999999999999),
        makePrestadorCnpj(),
        null,
        makeServicoMinimo(),
        makeValoresMinimo(),
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // serie=99999 (no padding needed), nDPS=999999999999999 (no padding needed)
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160821234567800019599999999999999999999');
});

it('generates Id with single-digit serie and ndps left-padded', function () {
    $data = new DpsData(
        makeInfDps(nDPS: 42),
        makePrestadorCnpj(),
        null,
        makeServicoMinimo(),
        makeValoresMinimo(),
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // serie padded to 5 → 00001, nDPS padded to 15 → 000000000000042
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160821234567800019500001000000000000042');
});

it('generates Id truncating cLocEmi to 7 chars', function () {
    $data = new DpsData(
        makeInfDps(cLocEmi: '35016089999'),
        makePrestadorCnpj(),
        null,
        makeServicoMinimo(),
        makeValoresMinimo(),
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // Only first 7 chars of cLocEmi used
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160821234567800019500001000000000000001');
});
