<?php

use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Certificates\CertificateManager;

function makePfxContent(): string
{
    return file_get_contents(__DIR__ . '/fixtures/certs/fake.pfx');
}

function makeTestCertificate(): Certificate
{
    return (new CertificateManager(makePfxContent(), 'secret'))->getCertificate();
}
