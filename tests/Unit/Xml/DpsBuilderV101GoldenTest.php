<?php

use OwnerPro\Nfsen\Xml\DpsBuilder;

covers(DpsBuilder::class);

it('keeps the production layout XML byte for byte', function (string $fixture, Closure $data) {
    $xml = (new DpsBuilder(makeXsdValidator()))->buildAndValidate($data());

    expect($xml)->toBe(rtrim((string) file_get_contents(__DIR__.'/../../fixtures/dps/'.$fixture)));
})->with([
    'núcleo' => ['v101-nucleo.xml', fn () => makeDpsNucleo()],
    'completa' => ['v101-completa.xml', fn () => makeDpsCompleta()],
]);
