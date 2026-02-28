<?php

use Pulsar\NfseNacional\Signing\XmlSigner;

function parseSignedXml(string $signed): DOMDocument
{
    $doc = new DOMDocument();
    $doc->loadXML($signed);
    return $doc;
}

function makeTestXml(string $id = 'DPS00000000000000000000000000000000000000001'): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse">'
        . '<infDPS Id="' . $id . '"/>'
        . '</DPS>';
}

it('signs xml with sha1 and places Signature inside root element', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha1');

    $signed = $signer->sign(makeTestXml(), 'infDPS', 'DPS');
    $doc    = parseSignedXml($signed);
    $dsNs   = 'http://www.w3.org/2000/09/xmldsig#';

    // Signature is a child of DPS
    $dps       = $doc->getElementsByTagName('DPS')->item(0);
    $signature = $doc->getElementsByTagNameNS($dsNs, 'Signature')->item(0);
    expect($signature->parentNode->nodeName)->toBe($dps->nodeName);

    // Reference URI points to the signed element Id
    $reference = $doc->getElementsByTagNameNS($dsNs, 'Reference')->item(0);
    expect($reference->getAttribute('URI'))->toBe('#DPS00000000000000000000000000000000000000001');

    // Uses SHA-1 algorithm
    $digestMethod = $doc->getElementsByTagNameNS($dsNs, 'DigestMethod')->item(0);
    expect($digestMethod->getAttribute('Algorithm'))->toContain('sha1');

    $signatureMethod = $doc->getElementsByTagNameNS($dsNs, 'SignatureMethod')->item(0);
    expect($signatureMethod->getAttribute('Algorithm'))->toContain('sha1');
});

it('signs xml with sha256 algorithm', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha256');

    $signed = $signer->sign(makeTestXml(), 'infDPS', 'DPS');
    $doc    = parseSignedXml($signed);
    $dsNs   = 'http://www.w3.org/2000/09/xmldsig#';

    // Uses SHA-256 algorithm
    $digestMethod = $doc->getElementsByTagNameNS($dsNs, 'DigestMethod')->item(0);
    expect($digestMethod->getAttribute('Algorithm'))->toContain('sha256');

    $signatureMethod = $doc->getElementsByTagNameNS($dsNs, 'SignatureMethod')->item(0);
    expect($signatureMethod->getAttribute('Algorithm'))->toContain('sha256');
});

it('defaults to sha1 when no algorithm is specified', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert);

    $signed = $signer->sign(makeTestXml(), 'infDPS', 'DPS');
    $doc    = parseSignedXml($signed);
    $dsNs   = 'http://www.w3.org/2000/09/xmldsig#';

    $signatureMethod = $doc->getElementsByTagNameNS($dsNs, 'SignatureMethod')->item(0);
    expect($signatureMethod->getAttribute('Algorithm'))->toContain('sha1');
});

it('throws InvalidArgumentException for unrecognized algorithm string', function () {
    $cert = makeTestCertificate();

    expect(fn () => new XmlSigner($cert, 'md5'))
        ->toThrow(InvalidArgumentException::class, 'Algoritmo de assinatura não suportado: md5');
});

it('includes KeyInfo with X509Certificate element', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha1');

    $signed = $signer->sign(makeTestXml(), 'infDPS', 'DPS');
    $doc    = parseSignedXml($signed);
    $dsNs   = 'http://www.w3.org/2000/09/xmldsig#';

    $keyInfo = $doc->getElementsByTagNameNS($dsNs, 'KeyInfo')->item(0);
    expect($keyInfo)->not->toBeNull();

    $x509 = $doc->getElementsByTagNameNS($dsNs, 'X509Certificate')->item(0);
    expect($x509)->not->toBeNull();
    expect($x509->textContent)->not->toBeEmpty();
});

it('produces a DigestValue and SignatureValue that are non-empty base64', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha1');

    $signed = $signer->sign(makeTestXml(), 'infDPS', 'DPS');
    $doc    = parseSignedXml($signed);
    $dsNs   = 'http://www.w3.org/2000/09/xmldsig#';

    $digestValue = $doc->getElementsByTagNameNS($dsNs, 'DigestValue')->item(0);
    expect($digestValue->textContent)->not->toBeEmpty();
    expect(base64_decode($digestValue->textContent, true))->not->toBeFalse();

    $signatureValue = $doc->getElementsByTagNameNS($dsNs, 'SignatureValue')->item(0);
    expect($signatureValue->textContent)->not->toBeEmpty();
    expect(base64_decode($signatureValue->textContent, true))->not->toBeFalse();
});

it('signs pedRegEvento xml for cancelar workflow', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha1');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<pedRegEvento xmlns="http://www.sped.fazenda.gov.br/nfse">'
        . '<infPedReg Id="PRE00000000000000000000000000000000000000001101101"/>'
        . '</pedRegEvento>';

    $signed = $signer->sign($xml, 'infPedReg', 'pedRegEvento');
    $doc    = parseSignedXml($signed);
    $dsNs   = 'http://www.w3.org/2000/09/xmldsig#';

    $signature = $doc->getElementsByTagNameNS($dsNs, 'Signature')->item(0);
    expect($signature->parentNode->nodeName)->toBe('pedRegEvento');

    $reference = $doc->getElementsByTagNameNS($dsNs, 'Reference')->item(0);
    expect($reference->getAttribute('URI'))
        ->toBe('#PRE00000000000000000000000000000000000000001101101');
});
