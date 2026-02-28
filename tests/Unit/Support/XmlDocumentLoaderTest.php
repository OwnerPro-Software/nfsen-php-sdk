<?php

use Pulsar\NfseNacional\Support\XmlDocumentLoader;

it('loads valid XML and returns DOMDocument', function () {
    $loader = new XmlDocumentLoader();

    $doc = $loader('<?xml version="1.0" encoding="UTF-8"?><root/>');

    expect($doc)->toBeInstanceOf(DOMDocument::class);
});

it('returns false for invalid XML', function () {
    $loader = new XmlDocumentLoader();

    $result = @$loader('not valid xml <<<');

    expect($result)->toBeFalse();
});
