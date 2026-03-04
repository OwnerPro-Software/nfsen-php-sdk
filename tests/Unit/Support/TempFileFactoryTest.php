<?php

covers(\Pulsar\NfseNacional\Support\TempFileFactory::class);

use Pulsar\NfseNacional\Support\TempFileFactory;

it('creates a temporary file resource via invocation', function () {
    $factory = new TempFileFactory;

    $handle = $factory();

    expect($handle)->toBeResource();

    fclose($handle);
});
