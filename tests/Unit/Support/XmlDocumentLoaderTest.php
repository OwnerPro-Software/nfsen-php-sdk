<?php

covers(\OwnerPro\Nfsen\Support\XmlDocumentLoader::class);

use OwnerPro\Nfsen\Support\XmlDocumentLoader;

it('loads valid XML and returns DOMDocument', function () {
    $loader = new XmlDocumentLoader;

    $doc = $loader('<?xml version="1.0" encoding="UTF-8"?><root/>');

    expect($doc)->toBeInstanceOf(DOMDocument::class);
});

it('returns false for invalid XML without emitting warnings', function () {
    $loader = new XmlDocumentLoader;

    $result = $loader('not valid xml <<<');

    expect($result)->toBeFalse();
});

it('does not emit PHP warnings when loading invalid XML', function () {
    $loader = new XmlDocumentLoader;

    // If libxml_use_internal_errors is not set to true, this would emit PHP warnings
    // which Pest converts to exceptions, failing the test
    set_error_handler(function (int $errno, string $errstr): never {
        throw new RuntimeException("Unexpected warning: $errstr", $errno);
    }, E_WARNING);

    try {
        $result = $loader('not valid xml <<<');
    } finally {
        restore_error_handler();
    }

    expect($result)->toBeFalse();
});

it('restores libxml error handling after loading', function () {
    $loader = new XmlDocumentLoader;

    $prevState = libxml_use_internal_errors(false);

    $loader('<?xml version="1.0" encoding="UTF-8"?><root/>');

    $restoredState = libxml_use_internal_errors($prevState);

    // Must be restored to false
    expect($restoredState)->toBeFalse();
});

it('clears libxml errors after loading', function () {
    $loader = new XmlDocumentLoader;

    libxml_use_internal_errors(true);

    $loader('not valid xml <<<');

    $errors = libxml_get_errors();
    libxml_use_internal_errors(false);

    expect($errors)->toBeEmpty();
});
