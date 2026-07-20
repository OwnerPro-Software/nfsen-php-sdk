<?php

use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;
use OwnerPro\Nfsen\Support\DpsId;

covers(DpsId::class);

it('generates id for CNPJ with tipo de inscricao 2', function () {
    $id = DpsId::generate('3550308', '12345678000195', null, '1', '42');

    expect($id)->toBe('DPS'.'3550308'.'2'.'12345678000195'.'00001'.'000000000000042')
        ->and(strlen($id))->toBe(45);
});

it('generates id for CPF with tipo de inscricao 1 and left-pads to 14 digits', function () {
    $id = DpsId::generate('3550308', null, '12345678901', '900', '7');

    expect($id)->toBe('DPS3550308'.'1'.'00012345678901'.'00900'.'000000000000007');
});

it('prefers CNPJ when both CNPJ and CPF are provided', function () {
    $id = DpsId::generate('3550308', '12345678000195', '12345678901', '1', '1');

    expect($id)->toStartWith('DPS3550308'.'2'.'12345678000195');
});

it('throws InvalidDpsArgument when both cnpj and cpf are null', function () {
    try {
        DpsId::generate('3550308', null, null, '1', '1');
        test()->fail('Expected InvalidDpsArgument');
    } catch (InvalidDpsArgument $e) {
        expect($e->getMessage())->toStartWith('Informe o CNPJ ou o CPF do emitente')
            ->and($e->getMessage())->toContain('allowEmptyInscricao: true');
    }
});

it('pads inscricao with 14 zeros for foreign prestador when explicitly allowed', function () {
    $id = DpsId::generate('3550308', null, null, '1', '1', allowEmptyInscricao: true);

    expect(substr($id, 10, 15))->toBe('1'.'00000000000000');
});

it('truncates cLocEmi to 7 digits', function () {
    $id = DpsId::generate('35503089', '12345678000195', null, '1', '1');

    expect(substr($id, 3, 7))->toBe('3550308');
});

it('left-pads serie to 5 and nDps to 15 digits', function () {
    $id = DpsId::generate('3550308', '12345678000195', null, '12', '345');

    expect(substr($id, 25, 5))->toBe('00012')
        ->and(substr($id, 30))->toBe('000000000000345');
});

it('matches the TSIdDPS pattern from the national schema', function () {
    $id = DpsId::generate('3550308', null, '12345678901', '1', '1');

    expect(preg_match('/^DPS[0-9]{42}$/', $id))->toBe(1);
});

it('throws InvalidDpsArgument when cLocEmi contains non-digits', function () {
    expect(fn () => DpsId::generate('ABC1234', '12345678000195', null, '1', '1'))
        ->toThrow(InvalidDpsArgument::class, 'Identificador de DPS gerado é inválido');
});

it('throws InvalidDpsArgument when cLocEmi is shorter than 7 digits', function () {
    expect(fn () => DpsId::generate('123', '12345678000195', null, '1', '1'))
        ->toThrow(InvalidDpsArgument::class, 'DPS[0-9]{42}');
});

it('throws InvalidDpsArgument when serie exceeds 5 digits', function () {
    expect(fn () => DpsId::generate('3550308', '12345678000195', null, '123456', '1'))
        ->toThrow(InvalidDpsArgument::class);
});
