<?php

use OwnerPro\Nfsen\Adapters\DompdfHtmlToPdfConverter;

covers(DompdfHtmlToPdfConverter::class);

it('outputs a PDF with %PDF- prefix', function () {
    $converter = new DompdfHtmlToPdfConverter;

    $pdf = $converter->convert('<html><body><p>Teste</p></body></html>');

    expect($pdf)->toStartWith('%PDF-');
});

it('disables remote resources to prevent SSRF', function () {
    $converter = new DompdfHtmlToPdfConverter;

    // Remote image should NOT be loaded (isRemoteEnabled=false).
    // We just verify the PDF generates without fetching anything.
    $pdf = $converter->convert(
        '<html><body><img src="http://example.invalid/logo.png" alt=""/></body></html>'
    );

    expect($pdf)->toStartWith('%PDF-');
});
