<?php

use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Exceptions\XmlParseException;

covers(XmlParseException::class);

it('extends NfseException', function () {
    expect(new XmlParseException('boom'))->toBeInstanceOf(NfseException::class);
});

it('preserves message and previous', function () {
    $prev = new RuntimeException('cause');
    $ex = new XmlParseException('boom', previous: $prev);

    expect($ex->getMessage())->toBe('boom');
    expect($ex->getPrevious())->toBe($prev);
});
