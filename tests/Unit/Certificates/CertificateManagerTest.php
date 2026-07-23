<?php

use NFePHP\Common\Certificate;
use NFePHP\Common\Exception\CertificateException;
use OwnerPro\Nfsen\Adapters\CertificateManager;
use OwnerPro\Nfsen\Contracts\Driven\ExtractsAuthorIdentity;
use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;
use OwnerPro\Nfsen\Exceptions\CertificateNotYetValidException;

it('loads certificate from pfx content and exposes it via getter', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');

    $manager = new CertificateManager($pfxContent, 'secret');

    $cert = $manager->getCertificate();
    expect($cert)->toBeInstanceOf(Certificate::class);
    expect($cert->isExpired())->toBeFalse();
});

it('throws CertificateExpiredException naming when the cert expired', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/expired.pfx');
    $validTo = Certificate::readPfx($pfxContent, 'secret')->getValidTo()->format('d/m/Y H:i:s');

    expect(fn () => new CertificateManager($pfxContent, 'secret'))
        ->toThrow(CertificateExpiredException::class, sprintf('Certificado expirado em %s (UTC).', $validTo));
});

it('throws CertificateNotYetValidException naming the start of validity', function () {
    // fake.pfx tem validFrom 2026-02-27; um "now" antes disso — relógio adiantado
    // na emissão ou host com skew — não pode assinar com cert ainda sem vigência.
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');
    $validFrom = Certificate::readPfx($pfxContent, 'secret')->getValidFrom()->format('d/m/Y H:i:s');

    expect(fn () => new CertificateManager($pfxContent, 'secret', new DateTimeImmutable('2026-01-01')))
        ->toThrow(CertificateNotYetValidException::class, sprintf('início da vigência em %s (UTC).', $validFrom));
});

it('accepts the cert at the exact instant its validity begins', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');
    $validFrom = Certificate::readPfx($pfxContent, 'secret')->getValidFrom();

    $manager = new CertificateManager($pfxContent, 'secret', DateTimeImmutable::createFromInterface($validFrom));

    expect($manager->getCertificate())->toBeInstanceOf(Certificate::class);
});

it('accepts a cert whose validity window contains the given now', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');

    $manager = new CertificateManager($pfxContent, 'secret', new DateTimeImmutable('2030-06-01'));

    expect($manager->getCertificate())->toBeInstanceOf(Certificate::class);
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
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake-icpbr.pfx');
    $manager = new CertificateManager($pfxContent, 'secret');

    $identity = $manager->extract();

    expect($identity)
        ->toHaveKey('cnpj')
        ->toHaveKey('cpf')
        ->and($identity['cnpj'])->toBe('12345678000195')
        ->and($identity['cpf'])->toBeNull();
});

it('extracts CPF from certificate when present', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake-icpbr-cpf.pfx');
    $manager = new CertificateManager($pfxContent, 'secret');

    $identity = $manager->extract();

    expect($identity)
        ->toHaveKey('cnpj')
        ->toHaveKey('cpf')
        ->and($identity['cpf'])->toBe('12345678901')
        ->and($identity['cnpj'])->toBeNull();
});

it('implements ExtractsAuthorIdentity interface', function () {
    $pfxContent = file_get_contents(__DIR__.'/../../fixtures/certs/fake.pfx');
    $manager = new CertificateManager($pfxContent, 'secret');

    expect($manager)->toBeInstanceOf(ExtractsAuthorIdentity::class);
});
