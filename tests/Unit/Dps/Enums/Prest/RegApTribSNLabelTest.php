<?php

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegApTribSN;

covers(RegApTribSN::class, HasLabelOf::class);

it('matches every label to the XSD documentation', function () {
    $doXsd = labelsDoXsd('TSRegimeApuracaoSimpNac');

    expect($doXsd)->toHaveCount(count(RegApTribSN::cases()));

    foreach (RegApTribSN::cases() as $case) {
        expect($doXsd)->toHaveKey($case->value);
        expect($case->label())->toBe($doXsd[$case->value]);
    }
});

it('labelOf resolves a raw XSD value to its label', function () {
    $doXsd = labelsDoXsd('TSRegimeApuracaoSimpNac');

    expect(RegApTribSN::labelOf('2'))->toBe($doXsd['2']);
});

it('labelOf returns dash for null/unknown', function () {
    expect(RegApTribSN::labelOf(null))->toBe('-');
    expect(RegApTribSN::labelOf('99'))->toBe('-');
});
