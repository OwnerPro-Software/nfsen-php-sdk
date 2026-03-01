<?php

use Pulsar\NfseNacional\DTOs\DpsData;
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
        makeInfDps(['cmotivoemisti' => '1']),
        makePrestadorCnpj(),
        new stdClass,
        makeServicoMinimo(),
        new stdClass,
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    $node = $xpath->query('//n:cMotivoEmisTI')->item(0);
    expect($node)->not->toBeNull();
    expect($node->textContent)->toBe('1');
});

it('includes chNFSeRej when set', function () {
    $data = new DpsData(
        makeInfDps(['chnfserej' => 'CHAVE_REJEITADA_123']),
        makePrestadorCnpj(),
        new stdClass,
        makeServicoMinimo(),
        new stdClass,
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    $node = $xpath->query('//n:chNFSeRej')->item(0);
    expect($node)->not->toBeNull();
    expect($node->textContent)->toBe('CHAVE_REJEITADA_123');
});

it('includes toma element as child of infDPS when tomador has data', function () {
    $tomador = new stdClass;
    $tomador->cnpj = '98765432000111';
    $tomador->xnome = 'Tomador Ltda';

    $data = new DpsData(makeInfDps(), makePrestadorCnpj(), $tomador, makeServicoMinimo(), new stdClass);

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
    $servico = makeServicoMinimo();
    $servico->cserv->ctribnac = 'INVALID_LONG_VALUE_THAT_WILL_FAIL_XSD_VALIDATION_BECAUSE_IT_EXCEEDS_MAX_LENGTH';

    $data = new DpsData(makeInfDps(), makePrestadorCnpj(), new stdClass, $servico, new stdClass);

    $builder = new DpsBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'XML inválido');
});

it('generates correct Id for CNPJ prestador', function (DpsData $data) {
    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // DPS + clocemi(7) + tipo=2(CNPJ) + cnpj(14) + serie(5 padded) + ndps(15 padded)
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160821234567800019500001000000000000001');
})->with('dpsData');

it('generates correct Id for CPF prestador', function () {
    $prestador = new stdClass;
    $prestador->cpf = '12345678901';
    $prestador->xnome = 'Pessoa Física';
    $regTrib = new stdClass;
    $regTrib->opsimpnac = 0;
    $regTrib->regesptrib = 0;
    $prestador->regtrib = $regTrib;

    $data = new DpsData(makeInfDps(), $prestador, new stdClass, makeServicoMinimo(), new stdClass);

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // tipo=1(CPF) + CPF left-padded to 14 digits
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160810001234567890100001000000000000001');
});

it('generates Id with max serie and large ndps padding', function () {
    $data = new DpsData(
        makeInfDps(['serie' => '99999', 'ndps' => 999999999999999]),
        makePrestadorCnpj(),
        new stdClass,
        makeServicoMinimo(),
        new stdClass,
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // serie=99999 (no padding needed), ndps=999999999999999 (no padding needed)
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160821234567800019599999999999999999999');
});

it('generates Id with single-digit serie and ndps left-padded', function () {
    $data = new DpsData(
        makeInfDps(['ndps' => 42]),
        makePrestadorCnpj(),
        new stdClass,
        makeServicoMinimo(),
        new stdClass,
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // serie padded to 5 → 00001, ndps padded to 15 → 000000000000042
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160821234567800019500001000000000000042');
});

it('generates Id truncating clocemi to 7 chars', function () {
    $data = new DpsData(
        makeInfDps(['clocemi' => '35016089999']),
        makePrestadorCnpj(),
        new stdClass,
        makeServicoMinimo(),
        new stdClass,
    );

    $xml = buildDps($data);
    $xpath = parseDpsXml($xml);

    // Only first 7 chars of clocemi used
    $infDps = $xpath->query('/n:DPS/n:infDPS')->item(0);
    expect($infDps->getAttribute('Id'))->toBe('DPS350160821234567800019500001000000000000001');
});
