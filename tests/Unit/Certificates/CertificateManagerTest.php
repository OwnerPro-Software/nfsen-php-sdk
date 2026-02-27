<?php

use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Certificates\CertificateManager;
use Pulsar\NfseNacional\Exceptions\CertificateExpiredException;

it('loads certificate from pfx content', function () {
    $pfxContent = file_get_contents(__DIR__ . '/../../fixtures/certs/fake.pfx');

    $manager = new CertificateManager($pfxContent, 'secret');

    expect($manager->getCertificate())->toBeInstanceOf(Certificate::class);
});

it('throws CertificateExpiredException for an expired cert', function () {
    // Usa fixture estática gerada na Task 1 (Step 4c)
    $pfxContent = file_get_contents(__DIR__ . '/../../fixtures/certs/expired.pfx');

    expect(fn () => new CertificateManager($pfxContent, 'secret'))
        ->toThrow(CertificateExpiredException::class);
});
