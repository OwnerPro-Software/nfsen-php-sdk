<?php

use Pulsar\NfseNacional\Signing\XmlSigner;

it('signs xml and injects Signature element', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha1');

    // XML mínimo com Id no elemento a ser assinado
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse">'
        . '<infDPS Id="DPS00000000000000000000000000000000000000001"/>'
        . '</DPS>';

    $signed = $signer->sign($xml, 'infDPS', 'DPS');

    expect($signed)->toContain('<Signature');
    expect($signed)->toContain('SignedInfo');
});

it('accepts sha256 algorithm', function () {
    $cert   = makeTestCertificate();
    $signer = new XmlSigner($cert, 'sha256');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse">'
        . '<infDPS Id="DPS00000000000000000000000000000000000000001"/>'
        . '</DPS>';

    $signed = $signer->sign($xml, 'infDPS', 'DPS');

    expect($signed)->toContain('<Signature');
});
