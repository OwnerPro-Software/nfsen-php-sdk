<?php

covers(\OwnerPro\Nfsen\Responses\ProcessingMessage::class);

use OwnerPro\Nfsen\Responses\ProcessingMessage;

it('constructs with all fields', function () {
    $msg = new ProcessingMessage(
        mensagem: 'Mensagem teste',
        codigo: 'E001',
        descricao: 'Descrição do erro',
        complemento: 'Informação adicional',
    );

    expect($msg)
        ->mensagem->toBe('Mensagem teste')
        ->codigo->toBe('E001')
        ->descricao->toBe('Descrição do erro')
        ->complemento->toBe('Informação adicional');
});

it('constructs with defaults when no arguments provided', function () {
    $msg = new ProcessingMessage;

    expect($msg)
        ->mensagem->toBeNull()
        ->codigo->toBeNull()
        ->descricao->toBeNull()
        ->complemento->toBeNull();
});

it('creates from array with full data', function () {
    $msg = ProcessingMessage::fromArray([
        'mensagem' => 'Mensagem',
        'codigo' => 'A001',
        'descricao' => 'Descrição',
        'complemento' => 'Complemento',
    ]);

    expect($msg)
        ->toBeInstanceOf(ProcessingMessage::class)
        ->mensagem->toBe('Mensagem')
        ->codigo->toBe('A001')
        ->descricao->toBe('Descrição')
        ->complemento->toBe('Complemento');
});

it('creates from array with partial data', function () {
    $msg = ProcessingMessage::fromArray([
        'descricao' => 'Apenas descrição',
    ]);

    expect($msg)
        ->mensagem->toBeNull()
        ->codigo->toBeNull()
        ->descricao->toBe('Apenas descrição')
        ->complemento->toBeNull();
});

it('creates from empty array', function () {
    $msg = ProcessingMessage::fromArray([]);

    expect($msg)
        ->mensagem->toBeNull()
        ->codigo->toBeNull()
        ->descricao->toBeNull()
        ->complemento->toBeNull();
});

it('creates list from array of items', function () {
    $items = [
        ['codigo' => 'E001', 'descricao' => 'Primeiro erro'],
        ['codigo' => 'E002', 'descricao' => 'Segundo erro', 'mensagem' => 'Msg'],
    ];

    $list = ProcessingMessage::fromArrayList($items);

    expect($list)->toHaveCount(2);
    expect($list[0])
        ->toBeInstanceOf(ProcessingMessage::class)
        ->codigo->toBe('E001')
        ->descricao->toBe('Primeiro erro');
    expect($list[1])
        ->codigo->toBe('E002')
        ->descricao->toBe('Segundo erro')
        ->mensagem->toBe('Msg');
});

it('creates empty list from empty array', function () {
    $list = ProcessingMessage::fromArrayList([]);

    expect($list)->toBeEmpty();
});

it('fromApiResult normalizes plural erros key', function () {
    $result = ['erros' => [
        ['codigo' => 'E001', 'descricao' => 'Primeiro'],
        ['codigo' => 'E002', 'descricao' => 'Segundo'],
    ]];

    $list = ProcessingMessage::fromApiResult($result);

    expect($list)->toHaveCount(2);
    expect($list[0]->codigo)->toBe('E001');
    expect($list[1]->codigo)->toBe('E002');
});

it('fromApiResult normalizes singular erro key', function () {
    $result = ['erro' => ['codigo' => 'E999', 'descricao' => 'Erro único']];

    $list = ProcessingMessage::fromApiResult($result);

    expect($list)->toHaveCount(1);
    expect($list[0]->codigo)->toBe('E999');
    expect($list[0]->descricao)->toBe('Erro único');
});

it('fromApiResult returns empty list when no error keys', function () {
    $list = ProcessingMessage::fromApiResult([]);

    expect($list)->toBeEmpty();
});

it('fromApiResult discards empty erro array', function () {
    $list = ProcessingMessage::fromApiResult(['erro' => []]);

    expect($list)->toBeEmpty();
});

it('creates from array with capitalized keys', function () {
    $msg = ProcessingMessage::fromArray([
        'Mensagem' => 'Mensagem',
        'Codigo' => 'E0037',
        'Descricao' => 'Descrição do erro',
        'Complemento' => 'Info adicional',
    ]);

    expect($msg)
        ->mensagem->toBe('Mensagem')
        ->codigo->toBe('E0037')
        ->descricao->toBe('Descrição do erro')
        ->complemento->toBe('Info adicional');
});

it('prefers lowercase keys over capitalized keys', function () {
    $msg = ProcessingMessage::fromArray([
        'codigo' => 'lowercase',
        'Codigo' => 'Capitalized',
    ]);

    expect($msg->codigo)->toBe('lowercase');
});

it('fromApiResult normalizes capitalized keys in erros list', function () {
    $result = ['erros' => [
        ['Codigo' => 'E0037', 'Descricao' => 'Município inexistente'],
    ]];

    $list = ProcessingMessage::fromApiResult($result);

    expect($list)->toHaveCount(1);
    expect($list[0]->codigo)->toBe('E0037');
    expect($list[0]->descricao)->toBe('Município inexistente');
});
