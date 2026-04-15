<?php

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegApTribSN;

covers(RegApTribSN::class, HasLabelOf::class);

it('returns label for each case', function () {
    foreach (RegApTribSN::cases() as $case) {
        expect($case->label())->not->toBe('');
    }
});

it('labelOf SN Federal e Municipal', function () {
    expect(RegApTribSN::labelOf('1'))->toBe(
        'Regime de apuração dos tributos federais e municipal pelo Simples Nacional'
    );
});

it('labelOf SN Federal, ISSQN pela NFSe', function () {
    expect(RegApTribSN::labelOf('2'))->toBe(
        'Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo'
    );
});

it('labelOf NFSe federal e municipal', function () {
    expect(RegApTribSN::labelOf('3'))->toBe(
        'Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo'
    );
});

it('labelOf returns dash for null/unknown', function () {
    expect(RegApTribSN::labelOf(null))->toBe('-');
    expect(RegApTribSN::labelOf('99'))->toBe('-');
});
