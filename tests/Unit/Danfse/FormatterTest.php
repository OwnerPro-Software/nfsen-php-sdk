<?php

use OwnerPro\Nfsen\Danfse\Formatter;

covers(Formatter::class);

beforeEach(function () {
    $this->fmt = new Formatter;
});

it('cnpjCpf formats 14-digit CNPJ', function () {
    expect($this->fmt->cnpjCpf('11222333000181'))->toBe('11.222.333/0001-81');
});

it('cnpjCpf formats 11-digit CPF', function () {
    expect($this->fmt->cnpjCpf('12345678901'))->toBe('123.456.789-01');
});

it('cnpjCpf returns dash for empty or dash', function () {
    expect($this->fmt->cnpjCpf(''))->toBe('-');
    expect($this->fmt->cnpjCpf('-'))->toBe('-');
});

it('cnpjCpf returns input when length is not 11 or 14', function () {
    expect($this->fmt->cnpjCpf('123'))->toBe('123');
});

it('phone formats 11 digits', function () {
    expect($this->fmt->phone('11987654321'))->toBe('(11) 98765-4321');
});

it('phone formats 10 digits', function () {
    expect($this->fmt->phone('1133334444'))->toBe('(11) 3333-4444');
});

it('phone returns dash for empty or dash', function () {
    expect($this->fmt->phone(''))->toBe('-');
    expect($this->fmt->phone('-'))->toBe('-');
});

it('phone returns input when length differs', function () {
    expect($this->fmt->phone('123'))->toBe('123');
});

it('cep formats 8 digits', function () {
    expect($this->fmt->cep('01310100'))->toBe('01.310-100');
});

it('cep returns dash for empty or dash', function () {
    expect($this->fmt->cep(''))->toBe('-');
    expect($this->fmt->cep('-'))->toBe('-');
});

it('cep returns input when length differs', function () {
    expect($this->fmt->cep('123'))->toBe('123');
});

it('date formats ISO to BR', function () {
    expect($this->fmt->date('2026-01-15'))->toBe('15/01/2026');
});

it('date returns dash for empty or dash', function () {
    expect($this->fmt->date(''))->toBe('-');
    expect($this->fmt->date('-'))->toBe('-');
});

it('date returns input when invalid', function () {
    expect($this->fmt->date('not-a-date'))->toBe('not-a-date');
});

it('dateTime formats ISO to BR', function () {
    expect($this->fmt->dateTime('2026-01-15T14:30:00-03:00'))->toBe('15/01/2026 14:30:00');
});

it('dateTime returns dash for empty or dash', function () {
    expect($this->fmt->dateTime(''))->toBe('-');
});

it('dateTime returns input when invalid', function () {
    expect($this->fmt->dateTime('not-a-date'))->toBe('not-a-date');
});

it('currency formats float', function () {
    expect($this->fmt->currency(1500.5))->toBe('R$ 1.500,50');
});

it('currency formats string', function () {
    expect($this->fmt->currency('1292.75'))->toBe('R$ 1.292,75');
});

it('currency returns dash for empty or dash', function () {
    expect($this->fmt->currency(''))->toBe('-');
    expect($this->fmt->currency('-'))->toBe('-');
});

it('currency formats zero', function () {
    expect($this->fmt->currency('0'))->toBe('R$ 0,00');
});

it('codTribNacional formats 6-digit code', function () {
    expect($this->fmt->codTribNacional('010700'))->toBe('01.07.00');
});

it('codTribNacional returns dash for empty or dash', function () {
    expect($this->fmt->codTribNacional(''))->toBe('-');
    expect($this->fmt->codTribNacional('-'))->toBe('-');
});

it('codTribNacional returns input when length differs', function () {
    expect($this->fmt->codTribNacional('1'))->toBe('1');
});

it('codNbs formats the 9-digit code the way the notice masks it', function () {
    expect($this->fmt->codNbs('115011000'))->toBe('1.1501.10.00');
});

it('codNbs returns dash for empty or dash', function () {
    expect($this->fmt->codNbs(''))->toBe('-');
    expect($this->fmt->codNbs('-'))->toBe('-');
});

it('codNbs returns input when length differs', function () {
    expect($this->fmt->codNbs('1'))->toBe('1');
});

it('joinPresent drops the parts the XML left out', function () {
    expect($this->fmt->joinPresent('Niterói / RJ', 'BR'))->toBe('Niterói / RJ / BR');
    expect($this->fmt->joinPresent('Niterói / RJ', ''))->toBe('Niterói / RJ');
    expect($this->fmt->joinPresent('-', 'BR'))->toBe('BR');
});

it('joinPresent returns dash when no part came', function () {
    expect($this->fmt->joinPresent('', '-'))->toBe('-');
});

// Nota 12 sobre máscara posicional: a posição vazia vira traço em vez de sumir, para
// as demais não escorregarem para a casa da vizinha.
it('joinSlots keeps the empty position instead of shifting the others', function () {
    expect($this->fmt->joinSlots('', '', '1,00%'))->toBe('- / - / 1,00%');
    expect($this->fmt->joinSlots('10,00%', '', '1,00%'))->toBe('10,00% / - / 1,00%');
    expect($this->fmt->joinSlots('000', '000001'))->toBe('000 / 000001');
});

it('joinSlots collapses to a single dash when no position came', function () {
    // NFS-e anterior à reforma não traz o grupo inteiro, e "- / - / -" seria ruído.
    expect($this->fmt->joinSlots('', '', ''))->toBe('-');
    expect($this->fmt->joinSlots('-', '-'))->toBe('-');
});

it('percent writes the decimal separator the rest of the document uses', function () {
    expect($this->fmt->percent('2.00'))->toBe('2,00%');
});

it('percent keeps the precision the XML brought', function () {
    // pAliq vem com duas casas, mas as alíquotas de IBS/CBS admitem mais: reformatar
    // com número fixo inventaria ou perderia precisão de um campo fiscal.
    expect($this->fmt->percent('8.8'))->toBe('8,8%');
    expect($this->fmt->percent('0.1234'))->toBe('0,1234%');
    expect($this->fmt->percent('5'))->toBe('5%');
});

it('limit truncates long strings', function () {
    expect($this->fmt->limit('abcdefghij', 5))->toBe('abcde...');
});

it('limit preserves short strings', function () {
    expect($this->fmt->limit('abc', 5))->toBe('abc');
});

it('limit preserves exact-length strings at boundary', function () {
    // Boundary: length === limit não deve truncar
    expect($this->fmt->limit('abcde', 5))->toBe('abcde');
});

it('limit respects custom suffix', function () {
    expect($this->fmt->limit('abcdefghij', 5, '>>'))->toBe('abcde>>');
});

it('limit breaks at word boundary instead of mid-word', function () {
    // Portal corta em palavra completa para manter legibilidade visual.
    $value = 'Licenciamento ou cessão de direito de uso de programas de computação.';
    // 60 chars bruto corta no "co" ("programas de co"). Deve retroceder ao último espaço.
    expect($this->fmt->limit($value, 60))->toBe('Licenciamento ou cessão de direito de uso de programas de...');
});

it('limit preserves long single word when no space exists before limit', function () {
    // Fallback: string sem espaço até o limite, trunca no limite bruto (sem perder info silenciosamente).
    expect($this->fmt->limit('abcdefghijklmnop', 5))->toBe('abcde...');
});

it('limit preserves short strings even when they contain spaces', function () {
    // Boundary: string <= limit não deve ser tocada, mesmo com espaços.
    expect($this->fmt->limit('ab cd', 5))->toBe('ab cd');
});
