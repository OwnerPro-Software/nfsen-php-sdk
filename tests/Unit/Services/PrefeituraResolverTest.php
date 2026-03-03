<?php

use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Adapters\PrefeituraResolver;
use Pulsar\NfseNacional\Support\FileReader;

$jsonPath = __DIR__.'/../../../storage/prefeituras.json';

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

it('resolves emitir_decisao_judicial operation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $path = $resolver->resolveOperation('9999999', 'emitir_decisao_judicial');

    expect($path)->toBe('decisao-judicial/nfse');
});

it('resolves verificar_dps operation with id substitution', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $path = $resolver->resolveOperation('9999999', 'verificar_dps', ['id' => 'DPS123']);

    expect($path)->toBe('dps/DPS123');
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

    expect($url)->toBe('https://sefin.nfse.gov.br/SefinNacional');
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

it('url-encodes special characters in template parameter values', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $path = $resolver->resolveOperation('9999999', 'consultar_nfse', ['chave' => 'ABC/../../admin']);

    expect($path)->toBe('nfse/ABC%2F..%2F..%2Fadmin');
});

it('throws InvalidArgumentException for missing template parameter', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    expect(fn () => $resolver->resolveOperation('9999999', 'consultar_eventos', ['chave' => 'ABC']))
        ->toThrow(\InvalidArgumentException::class, "'{tipoEvento}'");
});

it('throws InvalidArgumentException for unknown operation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    expect(fn () => $resolver->resolveOperation('9999999', 'operacao_inexistente'))
        ->toThrow(\InvalidArgumentException::class, 'Operação desconhecida');
});

it('throws InvalidArgumentException for missing json file', function () {
    expect(fn () => new PrefeituraResolver('/non/existent/path.json'))
        ->toThrow(\InvalidArgumentException::class, 'não encontrado');
});

it('throws InvalidArgumentException when file_get_contents fails', function () use ($jsonPath) {
    $reader = Mockery::mock(FileReader::class);
    $reader->shouldReceive('__invoke')->with($jsonPath)->andReturn(false);

    expect(fn () => new PrefeituraResolver($jsonPath, $reader))
        ->toThrow(\InvalidArgumentException::class, 'Falha ao ler');
});

it('throws InvalidArgumentException for invalid json content with parse error detail', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nfse_test_');
    file_put_contents($tmpFile, '{ invalid json }');

    try {
        expect(fn () => new PrefeituraResolver($tmpFile))
            ->toThrow(\InvalidArgumentException::class, 'Syntax error');
    } finally {
        unlink($tmpFile);
    }
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
