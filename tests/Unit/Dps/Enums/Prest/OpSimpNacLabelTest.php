<?php

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;
use OwnerPro\Nfsen\Dps\Enums\Prest\OpSimpNac;

covers(OpSimpNac::class, HasLabelOf::class);

it('returns label for each case', function () {
    expect(OpSimpNac::NaoOptante->label())->toBe('Não Optante');
    expect(OpSimpNac::OptanteMEI->label())->toBe('Optante - Microempreendedor Individual (MEI)');
    expect(OpSimpNac::OptanteMEEPP->label())->toBe('Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)');
});

it('labelOf returns label for valid string value', function () {
    expect(OpSimpNac::labelOf('1'))->toBe('Não Optante');
    expect(OpSimpNac::labelOf('2'))->toBe('Optante - Microempreendedor Individual (MEI)');
    expect(OpSimpNac::labelOf('3'))->toBe('Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)');
});

it('labelOf returns dash for null', function () {
    expect(OpSimpNac::labelOf(null))->toBe('-');
});

it('labelOf returns dash for unknown value', function () {
    expect(OpSimpNac::labelOf('99'))->toBe('-');
    expect(OpSimpNac::labelOf(''))->toBe('-');
});
