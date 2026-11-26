<?php

use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;
use OwnerPro\Nfsen\Support\DpsId;

covers(DpsId::class);

it('generates id for CNPJ with tipo de inscricao 2', function () {
    $id = DpsId::generate('3550308', '12345678000195', null, '1', '42');

    expect($id)->toBe('DPS'.'3550308'.'2'.'12345678000195'.'00001'.'000000000000042')
        ->and(strlen($id))->toBe(45);
});

it('generates id for an alphanumeric CNPJ', function () {
    $id = DpsId::generate('3550308', '12ABC34501DE35', null, '1', '42');

    expect($id)->toBe('DPS3550308'.'2'.'12ABC34501DE35'.'00001'.'000000000000042');
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

it('refuses a cLocEmi of the wrong width instead of trimming it to another município', function () {
    // O município entra com largura fixa, então o oitavo dígito era só descartado e o
    // identificador saía bem formado apontando para 3550308 — outro município.
    expect(fn () => DpsId::generate('35503089', '12345678000195', null, '1', '1'))
        ->toThrow(InvalidDpsArgument::class, 'cLocEmi inválido para o identificador da DPS: "35503089". Esperado sete dígitos (TSCodMunIBGE).');
});

it('refuses an inscription of the wrong width instead of padding it into another one', function (?string $cnpj, ?string $cpf, string $trecho) {
    // O zero-pad à esquerda acomoda qualquer tamanho: '12345' virava
    // 00000000012345, uma inscrição que existe e não é a informada.
    expect(fn () => DpsId::generate('3550308', $cnpj, $cpf, '1', '1'))
        ->toThrow(InvalidDpsArgument::class, $trecho);
})->with([
    'CNPJ curto' => ['12345', null, 'CNPJ inválido para o identificador da DPS: "12345". Esperado catorze posições, as doze primeiras com dígitos ou letras maiúsculas e as duas últimas com dígitos (TSCNPJ).'],
    'CNPJ vazio' => ['', null, 'CNPJ inválido para o identificador da DPS: "". Esperado catorze posições'],
    'CNPJ com máscara' => ['12.345.678/0001-95', null, 'CNPJ inválido para o identificador da DPS: "12.345.678/0001-95".'],
    'CNPJ alfanumérico em minúsculas' => ['12abc34501de35', null, 'CNPJ inválido para o identificador da DPS: "12abc34501de35".'],
    'CNPJ com letra no dígito verificador' => ['12ABC34501DE3X', null, 'CNPJ inválido para o identificador da DPS: "12ABC34501DE3X".'],
    'CNPJ alfanumérico com quinze posições' => ['12ABC34501DE350', null, 'CNPJ inválido para o identificador da DPS: "12ABC34501DE350".'],
    'CNPJ alfanumérico com quebra de linha' => ["12ABC34501DE35\n", null, 'CNPJ inválido para o identificador da DPS'],
    'CPF curto' => [null, '123', 'CPF inválido para o identificador da DPS: "123". Esperado onze dígitos (TSCPF).'],
    'CPF com máscara' => [null, '123.456.789-01', 'Esperado onze dígitos (TSCPF).'],
    'CPF com letras' => [null, '12ABC345678', 'CPF inválido para o identificador da DPS: "12ABC345678".'],
]);

it('left-pads serie to 5 and nDps to 15 digits', function () {
    $id = DpsId::generate('3550308', '12345678000195', null, '12', '345');

    expect(substr($id, 25, 5))->toBe('00012')
        ->and(substr($id, 30))->toBe('000000000000345');
});

it('matches the TSIdDPS pattern from the national schema', function (?string $cnpj, ?string $cpf) {
    $id = DpsId::generate('3550308', $cnpj, $cpf, '1', '1');

    expect(preg_match('/^DPS[0-9]{7}(1[0-9]{14}|2[0-9A-Z]{14})[0-9]{20}$/D', $id))->toBe(1);
})->with([
    'CPF' => [null, '12345678901'],
    'CNPJ numérico' => ['12345678000195', null],
    'CNPJ alfanumérico' => ['12ABC34501DE35', null],
    'CNPJ com letras na primeira e na décima segunda posição' => ['A2345678901Z35', null],
]);

it('throws InvalidDpsArgument when cLocEmi contains non-digits', function () {
    expect(fn () => DpsId::generate('ABC1234', '12345678000195', null, '1', '1'))
        ->toThrow(InvalidDpsArgument::class, 'cLocEmi inválido para o identificador da DPS');
});

it('throws InvalidDpsArgument when cLocEmi is shorter than 7 digits', function () {
    expect(fn () => DpsId::generate('123', '12345678000195', null, '1', '1'))
        ->toThrow(InvalidDpsArgument::class, 'Esperado sete dígitos (TSCodMunIBGE)');
});

it('throws InvalidDpsArgument when serie exceeds 5 digits', function () {
    expect(fn () => DpsId::generate('3550308', '12345678000195', null, '123456', '1'))
        ->toThrow(InvalidDpsArgument::class, 'Esperado o padrão DPS[0-9]{7}(1[0-9]{14}|2[0-9A-Z]{14})[0-9]{20} (TSIdDPS).');
});

it('refuses letters in serie and nDps, which only the inscription may carry', function (string $serie, string $nDps) {
    expect(fn () => DpsId::generate('3550308', '12ABC34501DE35', null, $serie, $nDps))
        ->toThrow(InvalidDpsArgument::class, 'Identificador de DPS gerado é inválido');
})->with([
    'série com letra' => ['A1', '1'],
    'nDps com letra' => ['1', '4B'],
    'nDps de 15 dígitos com quebra de linha' => ['1', "000000000000042\n"],
]);
