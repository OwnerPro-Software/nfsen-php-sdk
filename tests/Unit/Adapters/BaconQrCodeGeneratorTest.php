<?php

use OwnerPro\Nfsen\Adapters\BaconQrCodeGenerator;

covers(BaconQrCodeGenerator::class);

it('produces an SVG data URI', function () {
    $gen = new BaconQrCodeGenerator;

    $dataUri = $gen->dataUri('https://example.com/test');

    expect($dataUri)->toStartWith('data:image/svg+xml;base64,');
});

it('encodes payload as valid base64 SVG', function () {
    $gen = new BaconQrCodeGenerator;

    $dataUri = $gen->dataUri('hello');

    $encoded = substr($dataUri, strlen('data:image/svg+xml;base64,'));
    $svg = base64_decode($encoded, strict: true);

    expect($svg)->toBeString();
    expect($svg)->toContain('<svg');
});
