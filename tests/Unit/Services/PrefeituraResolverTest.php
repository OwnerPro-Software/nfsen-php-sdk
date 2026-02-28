<?php

use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Services\PrefeituraResolver;

$jsonPath = __DIR__ . '/../../../storage/prefeituras.json';

afterEach(fn () => PrefeituraResolver::clearCache());

it('resolves default sefin url for unknown prefeitura in homologacao', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $url = $resolver->resolveSeFinUrl('9999999', NfseAmbiente::HOMOLOGACAO);

    expect($url)->toBe('https://sefin.producaorestrita.nfse.gov.br/SefinNacional');
});

it('resolves custom sefin url for known prefeitura', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $url = $resolver->resolveSeFinUrl('3501608', NfseAmbiente::HOMOLOGACAO);

    expect($url)->toContain('americanahomologacao');
});

it('resolves default adn url for unknown prefeitura in producao', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $url = $resolver->resolveAdnUrl('9999999', NfseAmbiente::PRODUCAO);

    expect($url)->toBe('https://adn.nfse.gov.br');
});

it('resolves operation path with substitution', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $path = $resolver->resolveOperation('9999999', 'consultar_nfse', ['chave' => 'ABC123']);

    expect($path)->toBe('nfse/ABC123');
});

it('resolves custom operation for known prefeitura', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    // 3547304 (Santa Ana de Parnaiba) tem consultar_danfse customizado
    $path = $resolver->resolveOperation('3547304', 'consultar_danfse', ['chave' => 'ABC']);

    expect($path)->toContain('ABC');
});

it('returns empty string for empty operation override', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    // Americana (3501608) tem emitir_nfse: "" — URL já é completa
    $path = $resolver->resolveOperation('3501608', 'emitir_nfse');

    expect($path)->toBe('');
});

it('resolves default sefin url for unknown prefeitura in producao', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $url = $resolver->resolveSeFinUrl('9999999', NfseAmbiente::PRODUCAO);

    expect($url)->toBe('https://sefin.nfse.gov.br/sefinnacional');
});

it('resolves default adn url for unknown prefeitura in homologacao', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $url = $resolver->resolveAdnUrl('9999999', NfseAmbiente::HOMOLOGACAO);

    expect($url)->toBe('https://adn.producaorestrita.nfse.gov.br');
});

it('throws InvalidArgumentException for non-7-digit ibge code', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    expect(fn () => $resolver->resolveSeFinUrl('123', NfseAmbiente::HOMOLOGACAO))
        ->toThrow(\InvalidArgumentException::class, 'IBGE');
});

it('clearCache resets the static cache', function () use ($jsonPath) {
    // Load data into cache
    $resolver = new PrefeituraResolver($jsonPath);
    $resolver->resolveSeFinUrl('9999999', NfseAmbiente::HOMOLOGACAO);

    // Clear cache
    PrefeituraResolver::clearCache();

    // Should still work after clearing cache (re-reads file)
    $resolver2 = new PrefeituraResolver($jsonPath);
    $url = $resolver2->resolveSeFinUrl('9999999', NfseAmbiente::HOMOLOGACAO);

    expect($url)->toBe('https://sefin.producaorestrita.nfse.gov.br/SefinNacional');
});
