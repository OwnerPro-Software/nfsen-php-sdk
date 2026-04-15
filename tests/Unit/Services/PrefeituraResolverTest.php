<?php

use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Support\FileReader;

$jsonPath = __DIR__.'/../../../storage/prefeituras.json';

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

    $path = $resolver->resolveOperation('9999999', 'query_nfse', ['chave' => 'ABC123']);

    expect($path)->toBe('nfse/ABC123');
});

it('resolves emitir_decisao_judicial operation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $path = $resolver->resolveOperation('9999999', 'emit_court_order');

    expect($path)->toBe('decisao-judicial/nfse');
});

it('resolves verificar_dps operation with id substitution', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $path = $resolver->resolveOperation('9999999', 'verify_dps', ['id' => 'DPS123']);

    expect($path)->toBe('dps/DPS123');
});

it('resolves custom operation for known prefeitura', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    // 3547304 (Santa Ana de Parnaiba) tem consultar_danfse customizado
    $path = $resolver->resolveOperation('3547304', 'query_danfse', ['chave' => 'ABC']);

    expect($path)->toContain('ABC');
});

it('returns empty string for empty operation override', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    // Americana (3501608) tem emitir_nfse: "" — URL já é completa
    $path = $resolver->resolveOperation('3501608', 'emit_nfse');

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
        ->toThrow(InvalidArgumentException::class, 'IBGE');
});

it('url-encodes special characters in template parameter values', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $path = $resolver->resolveOperation('9999999', 'query_nfse', ['chave' => 'ABC/../../admin']);

    expect($path)->toBe('nfse/ABC%2F..%2F..%2Fadmin');
});

it('throws InvalidArgumentException for missing template parameter', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    expect(fn () => $resolver->resolveOperation('9999999', 'query_events', ['chave' => 'ABC']))
        ->toThrow(InvalidArgumentException::class, "'{tipoEvento}'");
});

it('throws InvalidArgumentException for unknown operation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    expect(fn () => $resolver->resolveOperation('9999999', 'operacao_inexistente'))
        ->toThrow(InvalidArgumentException::class, 'Operação desconhecida');
});

it('throws InvalidArgumentException for missing json file', function () {
    expect(fn () => new PrefeituraResolver('/non/existent/path.json'))
        ->toThrow(InvalidArgumentException::class, 'não encontrado');
});

it('throws InvalidArgumentException when file_get_contents fails', function () {
    $tmpPath = tempnam(sys_get_temp_dir(), 'nfse_unreadable_');

    try {
        $reader = Mockery::mock(FileReader::class);
        $reader->shouldReceive('__invoke')->with($tmpPath)->andReturn(false);

        expect(fn () => new PrefeituraResolver($tmpPath, $reader))
            ->toThrow(InvalidArgumentException::class, 'Falha ao ler');
    } finally {
        unlink($tmpPath);
    }
});

it('throws InvalidArgumentException for invalid json content with parse error detail', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nfse_test_');
    file_put_contents($tmpFile, '{ invalid json }');

    try {
        expect(fn () => new PrefeituraResolver($tmpFile))
            ->toThrow(InvalidArgumentException::class, 'Syntax error');
    } finally {
        unlink($tmpFile);
    }
});

it('throws InvalidArgumentException for invalid ibge code on resolveAdnUrl', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    expect(fn () => $resolver->resolveAdnUrl('123', NfseAmbiente::HOMOLOGACAO))
        ->toThrow(InvalidArgumentException::class, 'IBGE');
});

it('resolves custom adn url for known prefeitura', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $url = $resolver->resolveAdnUrl('3547304', NfseAmbiente::PRODUCAO);

    expect($url)->toBe('https://nfsesantanadeparnaiba.simplissweb.com.br');
});

it('throws InvalidArgumentException for invalid ibge code on resolveOperation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    expect(fn () => $resolver->resolveOperation('abc', 'query_nfse', ['chave' => 'X']))
        ->toThrow(InvalidArgumentException::class, 'IBGE');
});

it('casts integer parameter values to string in template', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);

    $path = $resolver->resolveOperation('9999999', 'query_events', [
        'chave' => 'ABC123',
        'tipoEvento' => 101101,
        'nSequencial' => 1,
    ]);

    expect($path)->toBe('nfse/ABC123/eventos/101101/1');
});

it('resolves default query_dps operation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);
    $path = $resolver->resolveOperation('9999999', 'query_dps', ['id' => 'DPS456']);
    expect($path)->toBe('dps/DPS456');
});

it('resolves default query_events operation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);
    $path = $resolver->resolveOperation('9999999', 'query_events', [
        'chave' => 'CHAVE1',
        'tipoEvento' => '101101',
        'nSequencial' => '1',
    ]);
    expect($path)->toBe('nfse/CHAVE1/eventos/101101/1');
});

it('resolves default query_danfse operation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);
    $path = $resolver->resolveOperation('9999999', 'query_danfse', ['chave' => 'CHAVE2']);
    expect($path)->toBe('danfse/CHAVE2');
});

it('resolves default emit_nfse operation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);
    $path = $resolver->resolveOperation('9999999', 'emit_nfse');
    expect($path)->toBe('nfse');
});

it('resolves default cancel_nfse operation', function () use ($jsonPath) {
    $resolver = new PrefeituraResolver($jsonPath);
    $path = $resolver->resolveOperation('9999999', 'cancel_nfse', ['chave' => 'CHAVE3']);
    expect($path)->toBe('nfse/CHAVE3/eventos');
});

it('throws InvalidArgumentException when sefin url uses http scheme', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nfse_test_');
    file_put_contents($tmpFile, json_encode([
        '1234567' => ['urls' => ['sefin_staging' => 'http://insecure.example.com']],
    ]));

    try {
        $resolver = new PrefeituraResolver($tmpFile);
        expect(fn () => $resolver->resolveSeFinUrl('1234567', NfseAmbiente::HOMOLOGACAO))
            ->toThrow(InvalidArgumentException::class, 'HTTPS');
    } finally {
        unlink($tmpFile);
    }
});

it('throws InvalidArgumentException when adn url uses http scheme', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nfse_test_');
    file_put_contents($tmpFile, json_encode([
        '1234567' => ['urls' => ['adn_production' => 'http://insecure.example.com']],
    ]));

    try {
        $resolver = new PrefeituraResolver($tmpFile);
        expect(fn () => $resolver->resolveAdnUrl('1234567', NfseAmbiente::PRODUCAO))
            ->toThrow(InvalidArgumentException::class, 'HTTPS');
    } finally {
        unlink($tmpFile);
    }
});

it('accepts https urls from custom json', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'nfse_test_');
    file_put_contents($tmpFile, json_encode([
        '1234567' => ['urls' => ['sefin_staging' => 'https://secure.example.com']],
    ]));

    try {
        $resolver = new PrefeituraResolver($tmpFile);
        $url = $resolver->resolveSeFinUrl('1234567', NfseAmbiente::HOMOLOGACAO);
        expect($url)->toBe('https://secure.example.com');
    } finally {
        unlink($tmpFile);
    }
});
