<?php

use Pulsar\NfseNacional\Support\GzipCompressor;

it('compresses string data via invocation', function () {
    $compressor = new GzipCompressor();

    $result = $compressor('hello world');

    expect($result)->toBeString();
    expect(gzdecode($result))->toBe('hello world');
});
