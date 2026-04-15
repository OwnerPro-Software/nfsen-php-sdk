<?php

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegEspTrib;

covers(RegEspTrib::class, HasLabelOf::class);

it('labelOf returns expected labels', function () {
    expect(RegEspTrib::labelOf('0'))->toBe('Nenhum');
    expect(RegEspTrib::labelOf('1'))->toBe('Ato Cooperado (Cooperativa)');
    expect(RegEspTrib::labelOf('2'))->toBe('Estimativa');
    expect(RegEspTrib::labelOf('3'))->toBe('Microempresa Municipal');
    expect(RegEspTrib::labelOf('4'))->toBe('Notário ou Registrador');
    expect(RegEspTrib::labelOf('5'))->toBe('Profissional Autônomo');
    expect(RegEspTrib::labelOf('6'))->toBe('Sociedade de Profissionais');
    expect(RegEspTrib::labelOf('9'))->toBe('Outros');
});

it('labelOf returns dash for null/unknown', function () {
    expect(RegEspTrib::labelOf(null))->toBe('-');
    expect(RegEspTrib::labelOf('99'))->toBe('-');
});

it('each case has a non-empty label', function () {
    foreach (RegEspTrib::cases() as $case) {
        expect($case->label())->not->toBe('');
    }
});
