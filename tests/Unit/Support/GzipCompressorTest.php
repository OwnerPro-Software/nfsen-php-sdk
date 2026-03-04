<?php

covers(\Pulsar\NfseNacional\Support\GzipCompressor::class);

use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Support\GzipCompressor;

it('compresses string data via invocation', function () {
    $compressor = new GzipCompressor;

    $result = $compressor('hello world');

    expect($result)->toBeString();
    expect(gzdecode($result))->toBe('hello world');
});

it('decompressB64 returns null for null input', function () {
    expect(GzipCompressor::decompressB64(null))->toBeNull();
});

it('decompressB64 returns null for empty string input', function () {
    expect(GzipCompressor::decompressB64(''))->toBeNull();
});

it('decompressB64 decompresses valid gzip base64', function () {
    $original = '<Evento/>';
    $compressed = base64_encode(gzencode($original));

    expect(GzipCompressor::decompressB64($compressed))->toBe($original);
});

it('decompressB64 throws NfseException on invalid base64', function () {
    expect(fn () => GzipCompressor::decompressB64('!!!invalid-base64!!!'))
        ->toThrow(NfseException::class, 'base64');
});

it('decompressB64 throws NfseException on invalid gzip data', function () {
    expect(fn () => GzipCompressor::decompressB64(base64_encode('not-gzip-data')))
        ->toThrow(NfseException::class, 'descomprimir');
});
