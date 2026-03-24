<?php

use OwnerPro\Nfsen\Support\FileReader;

covers(FileReader::class);

it('reads file contents via invocation', function () {
    $reader = new FileReader;
    $path = __DIR__.'/../../../storage/prefeituras.json';

    $contents = $reader($path);

    expect($contents)->toBeString()->toContain('3501608');
});

it('returns false for non-existent file', function () {
    $reader = new FileReader;

    $result = @$reader('/non/existent/file.json');

    expect($result)->toBeFalse();
});
