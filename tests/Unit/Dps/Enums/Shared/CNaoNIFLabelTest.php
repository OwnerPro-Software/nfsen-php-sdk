<?php

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;
use OwnerPro\Nfsen\Dps\Enums\Shared\CNaoNIF;

covers(CNaoNIF::class, HasLabelOf::class);

it('matches every label to the XSD documentation', function () {
    $doXsd = labelsDoXsd('TSCodNaoNIF');

    expect($doXsd)->toHaveCount(count(CNaoNIF::cases()));

    foreach (CNaoNIF::cases() as $case) {
        expect($doXsd)->toHaveKey($case->value);
        expect($case->label())->toBe($doXsd[$case->value]);
    }
});

it('labelOf returns dash for null/unknown', function () {
    expect(CNaoNIF::labelOf(null))->toBe('-');
    expect(CNaoNIF::labelOf('9'))->toBe('-');
});
