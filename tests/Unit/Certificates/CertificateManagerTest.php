<?php

use NFePHP\Common\Certificate;
use NFePHP\Common\Exception\CertificateException;
use Pulsar\NfseNacional\Adapters\CertificateManager;
use Pulsar\NfseNacional\Exceptions\CertificateExpiredException;

it('loads certificate from pfx content and exposes it via getter', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');

    $manager = new CertificateManager($pfxContent, 'secret');

    $cert = $manager->getCertificate();
    expect($cert)->toBeInstanceOf(Certificate::class);
    expect($cert->isExpired())->toBeFalse();
});

it('throws CertificateExpiredException for an expired cert', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/expired.pfx');

    expect(fn () => new CertificateManager($pfxContent, 'secret'))
        ->toThrow(CertificateExpiredException::class, 'expired');
});

it('throws CertificateException for wrong password', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');

    expect(fn () => new CertificateManager($pfxContent, 'wrong-password'))
        ->toThrow(CertificateException::class);
});

it('throws CertificateException for invalid pfx content', function () {
    expect(fn () => new CertificateManager('not-a-valid-pfx', 'secret'))
        ->toThrow(CertificateException::class);
});

it('throws CertificateException for empty pfx content', function () {
    expect(fn () => new CertificateManager('', 'secret'))
        ->toThrow(CertificateException::class);
});

it('extracts CNPJ from certificate via ExtractsAuthorIdentity port', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');
    $manager = new CertificateManager($pfxContent, 'secret');

    $identity = $manager->extract();

    expect($identity)->toBeArray()
        ->toHaveKeys(['cnpj', 'cpf']);
});

it('implements ExtractsAuthorIdentity interface', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');
    $manager = new CertificateManager($pfxContent, 'secret');

    expect($manager)->toBeInstanceOf(\Pulsar\NfseNacional\Contracts\Ports\Driven\ExtractsAuthorIdentity::class);
});
