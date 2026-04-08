<?php

use OwnerPro\Nfsen\Enums\StatusDistribuicao;

covers(StatusDistribuicao::class);

it('has 3 cases', function () {
    expect(StatusDistribuicao::cases())->toHaveCount(3);
});

it('maps correct string values', function (StatusDistribuicao $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [StatusDistribuicao::Rejeicao, 'REJEICAO'],
    [StatusDistribuicao::NenhumDocumentoLocalizado, 'NENHUM_DOCUMENTO_LOCALIZADO'],
    [StatusDistribuicao::DocumentosLocalizados, 'DOCUMENTOS_LOCALIZADOS'],
]);

it('creates from valid string', function () {
    expect(StatusDistribuicao::from('DOCUMENTOS_LOCALIZADOS'))
        ->toBe(StatusDistribuicao::DocumentosLocalizados);
});

it('throws ValueError for invalid string', function () {
    expect(fn () => StatusDistribuicao::from('INVALID'))
        ->toThrow(ValueError::class);
});

it('tryFrom returns null for invalid string', function () {
    expect(StatusDistribuicao::tryFrom('INVALID'))->toBeNull();
});
