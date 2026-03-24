<?php

use OwnerPro\Nfsen\Support\TempFileFactory;

covers(TempFileFactory::class);

it('creates a temporary file resource via invocation', function () {
    $factory = new TempFileFactory;

    $handle = $factory();

    expect($handle)->toBeResource();

    fclose($handle);
});
