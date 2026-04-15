<?php

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;

covers(TpRetISSQN::class, HasLabelOf::class);

it('labelOf returns expected labels', function () {
    expect(TpRetISSQN::labelOf('1'))->toBe('Não Retido');
    expect(TpRetISSQN::labelOf('2'))->toBe('Retido pelo Tomador');
    expect(TpRetISSQN::labelOf('3'))->toBe('Retido pelo Intermediário');
});

it('labelOf returns dash for null/unknown', function () {
    expect(TpRetISSQN::labelOf(null))->toBe('-');
    expect(TpRetISSQN::labelOf('99'))->toBe('-');
});
