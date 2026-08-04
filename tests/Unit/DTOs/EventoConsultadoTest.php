<?php

use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Responses\EventoConsultado;

covers(EventoConsultado::class);

it('constructs with all fields', function () {
    $chave = makeChaveAcesso();

    $evento = new EventoConsultado(
        chaveAcesso: $chave,
        tipoEvento: TipoEvento::CancelamentoDeferidoAnaliseFiscal,
        numeroPedidoRegistroEvento: 1,
        dataHoraRecebimento: '2026-08-03T07:31:39.737',
        xml: '<Evento/>',
    );

    expect($evento)
        ->chaveAcesso->toBe($chave)
        ->tipoEvento->toBe(TipoEvento::CancelamentoDeferidoAnaliseFiscal)
        ->numeroPedidoRegistroEvento->toBe(1)
        ->dataHoraRecebimento->toBe('2026-08-03T07:31:39.737')
        ->xml->toBe('<Evento/>')
        ->parseError->toBeNull();
});

it('reads the item as the SefinNacional sends it', function () {
    $chave = makeChaveAcesso();

    $evento = EventoConsultado::fromArray([
        'chaveAcesso' => $chave,
        'tipoEvento' => 105104,
        'numeroPedidoRegistroEvento' => 1,
        'dataHoraRecebimento' => '2026-08-03T07:31:39.737',
        'arquivoXml' => base64_encode(base64_encode((string) gzencode('<Evento/>'))),
    ]);

    expect($evento)
        ->chaveAcesso->toBe($chave)
        ->tipoEvento->toBe(TipoEvento::CancelamentoDeferidoAnaliseFiscal)
        ->numeroPedidoRegistroEvento->toBe(1)
        ->dataHoraRecebimento->toBe('2026-08-03T07:31:39.737')
        ->xml->toBe('<Evento/>')
        ->parseError->toBeNull();
});

it('accepts an empty item, leaving every field null and nothing to report', function () {
    $evento = EventoConsultado::fromArray([]);

    expect($evento)
        ->chaveAcesso->toBeNull()
        ->tipoEvento->toBeNull()
        ->numeroPedidoRegistroEvento->toBeNull()
        ->dataHoraRecebimento->toBeNull()
        ->xml->toBeNull()
        ->parseError->toBeNull();
});

it('accepts numeric text where the swagger declares integer', function () {
    $evento = EventoConsultado::fromArray([
        'tipoEvento' => '105104',
        'numeroPedidoRegistroEvento' => '7',
    ]);

    expect($evento)
        ->tipoEvento->toBe(TipoEvento::CancelamentoDeferidoAnaliseFiscal)
        ->numeroPedidoRegistroEvento->toBe(7)
        ->parseError->toBeNull();
});

it('reports the unknown tipoEvento instead of dropping the item', function () {
    $evento = EventoConsultado::fromArray(['tipoEvento' => 999999]);

    expect($evento->tipoEvento)->toBeNull()
        ->and($evento->parseError)->toBe('TipoEvento desconhecido: "999999".');
});

it('reports a field that came outside its declared type', function (array $data, string $expectedError) {
    expect(EventoConsultado::fromArray($data)->parseError)->toContain($expectedError);
})->with([
    'chaveAcesso' => [['chaveAcesso' => 42], 'Campo chaveAcesso veio como int, e não como texto.'],
    'dataHoraRecebimento' => [['dataHoraRecebimento' => []], 'Campo dataHoraRecebimento veio como array, e não como texto.'],
    'arquivoXml' => [['arquivoXml' => 42], 'Campo arquivoXml veio como int, e não como texto.'],
    'tipoEvento' => [['tipoEvento' => 1.5], 'Campo tipoEvento veio como float, e não como número inteiro.'],
    'numeroPedidoRegistroEvento' => [['numeroPedidoRegistroEvento' => '1a'], 'Campo numeroPedidoRegistroEvento veio como string, e não como número inteiro.'],
]);

it('reports an unreadable arquivoXml without losing the rest of the item', function () {
    $evento = EventoConsultado::fromArray([
        'numeroPedidoRegistroEvento' => 3,
        'arquivoXml' => '!!!nao-e-base64!!!',
    ]);

    expect($evento->numeroPedidoRegistroEvento)->toBe(3)
        ->and($evento->xml)->toBeNull()
        ->and($evento->parseError)->toBe('Falha ao decodificar base64 do XML.');
});

it('joins every problem found in the same item', function () {
    $evento = EventoConsultado::fromArray([
        'chaveAcesso' => 42,
        'tipoEvento' => 999999,
    ]);

    expect($evento->parseError)->toBe(
        'TipoEvento desconhecido: "999999". Campo chaveAcesso veio como int, e não como texto.'
    );
});

it('turns a scalar item into an empty event with the reason, instead of a TypeError', function () {
    $evento = EventoConsultado::fromArray('lixo');

    expect($evento->xml)->toBeNull()
        ->and($evento->parseError)->toBe('Item de eventos veio como string, e não como objeto.');
});
