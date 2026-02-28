<?php

use Pulsar\NfseNacional\Signing\XmlSigner;

function parseSignedXml(string $signed): DOMDocument
{
    $doc = new DOMDocument();
    $doc->loadXML($signed);
    return $doc;
}

it('signs xml with sha1 and places Signature inside root element', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha1');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse">'
        . '<infDPS Id="DPS00000000000000000000000000000000000000001"/>'
        . '</DPS>';

    $signed = $signer->sign($xml, 'infDPS', 'DPS');
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

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse">'
        . '<infDPS Id="DPS00000000000000000000000000000000000000001"/>'
        . '</DPS>';

    $signed = $signer->sign($xml, 'infDPS', 'DPS');
    $doc    = parseSignedXml($signed);
    $dsNs   = 'http://www.w3.org/2000/09/xmldsig#';

    // Uses SHA-256 algorithm
    $digestMethod = $doc->getElementsByTagNameNS($dsNs, 'DigestMethod')->item(0);
    expect($digestMethod->getAttribute('Algorithm'))->toContain('sha256');

    $signatureMethod = $doc->getElementsByTagNameNS($dsNs, 'SignatureMethod')->item(0);
    expect($signatureMethod->getAttribute('Algorithm'))->toContain('sha256');
});
