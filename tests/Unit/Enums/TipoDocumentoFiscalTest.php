<?php

use OwnerPro\Nfsen\Enums\TipoDocumentoFiscal;

covers(TipoDocumentoFiscal::class);

it('has 6 cases', function () {
    expect(TipoDocumentoFiscal::cases())->toHaveCount(6);
});

it('maps correct string values', function (TipoDocumentoFiscal $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [TipoDocumentoFiscal::Nenhum, 'NENHUM'],
    [TipoDocumentoFiscal::Dps, 'DPS'],
    [TipoDocumentoFiscal::PedidoRegistroEvento, 'PEDIDO_REGISTRO_EVENTO'],
    [TipoDocumentoFiscal::Nfse, 'NFSE'],
    [TipoDocumentoFiscal::Evento, 'EVENTO'],
    [TipoDocumentoFiscal::Cnc, 'CNC'],
]);

it('creates from valid string', function () {
    expect(TipoDocumentoFiscal::from('NFSE'))
        ->toBe(TipoDocumentoFiscal::Nfse);
});

it('throws ValueError for invalid string', function () {
    expect(fn () => TipoDocumentoFiscal::from('INVALID'))
        ->toThrow(ValueError::class);
});

it('tryFrom returns null for invalid string', function () {
    expect(TipoDocumentoFiscal::tryFrom('INVALID'))->toBeNull();
});
