<?php

use OwnerPro\Nfsen\Danfse\Identificacao;

covers(Identificacao::class);

beforeEach(function () {
    $this->identificacao = new Identificacao;
});

it('formats a CNPJ', function () {
    expect(($this->identificacao)('11222333000181', '', '', ''))->toBe('11.222.333/0001-81');
});

it('formats a CPF', function () {
    expect(($this->identificacao)('', '12345678901', '', ''))->toBe('123.456.789-01');
});

it('prefers CNPJ over CPF when both are present', function () {
    expect(($this->identificacao)('11222333000181', '12345678901', '', ''))->toBe('11.222.333/0001-81');
});

it('returns a foreign NIF untouched', function (string $nif) {
    // TSNIF é texto livre de até 40 caracteres. Passar pelo formatter de CNPJ/CPF
    // descartava todo não-dígito e mutilava o identificador sem aviso.
    expect(($this->identificacao)('', '', $nif, ''))->toBe($nif);
})->with(['PT501234567', 'ES-B12345678', 'IE1234567AB', 'GB123456789']);

it('explains the absence of a NIF via cNaoNIF', function () {
    expect(($this->identificacao)('', '', '', '0'))->toBe('Não informado na nota de origem');
    expect(($this->identificacao)('', '', '', '1'))->toBe('Dispensado do NIF');
    expect(($this->identificacao)('', '', '', '2'))->toBe('Não exigência do NIF');
});

it('returns dash when nothing identifies the participant', function () {
    expect(($this->identificacao)('', '', '', ''))->toBe('-');
});

it('returns dash for a cNaoNIF outside the XSD enumeration', function () {
    expect(($this->identificacao)('', '', '', '9'))->toBe('-');
});
