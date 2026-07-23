<?php

use OwnerPro\Nfsen\Responses\ProcessingMessage;

covers(ProcessingMessage::class);

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

it('fromApiResult prefers the singular erro when the plural key is empty', function () {
    $list = ProcessingMessage::fromApiResult([
        'erros' => [],
        'erro' => ['codigo' => 'E777', 'descricao' => 'Só o singular veio preenchido'],
    ]);

    expect($list)->toHaveCount(1)
        ->and($list[0]->codigo)->toBe('E777');
});

it('hasApiError agrees with fromApiResult on every shape the API produces', function (array $result, bool $expected) {
    // A classificação (rejeitado vs. processado) e as mensagens extraídas têm de sair
    // da mesma regra — divergir foi o que descartou a chaveAcesso de nota autorizada.
    expect(ProcessingMessage::hasApiError($result))->toBe($expected)
        ->and(ProcessingMessage::fromApiResult($result) !== [])->toBe($expected);
})->with([
    'sem chave de erro' => [[], false],
    'erros preenchido' => [['erros' => [['codigo' => 'E001']]], true],
    'erros vazio' => [['erros' => []], false],
    'erro preenchido' => [['erro' => ['codigo' => 'E999']], true],
    'erro vazio' => [['erro' => []], false],
    'erro vazio junto de payload de sucesso' => [['erro' => [], 'chaveAcesso' => '35123'], false],
    'ambos vazios' => [['erros' => [], 'erro' => []], false],
    'plural vazio, singular preenchido' => [['erros' => [], 'erro' => ['codigo' => 'E777']], true],
    // Um proxy/WAF antes da SEFIN responde com JSON próprio, muitas vezes com
    // `erro`/`erros` escalar. Não é rejeição da SEFIN: tem de sair da
    // classificação para o 5xx cair em IndeterminateResultException, e sem isso
    // fromArray() ainda estourava TypeError ao receber a string.
    'erro escalar de proxy' => [['erro' => 'Bad Gateway'], false],
    'erros escalar (nem lista)' => [['erros' => 'Bad Gateway'], false],
    'erros como lista de strings' => [['erros' => ['Bad Gateway']], false],
    'erros com item não-mensagem no meio' => [['erros' => [['codigo' => 'E1'], 'lixo']], true],
]);

it('fromApiResult descarta um erro escalar sem estourar', function () {
    /** @phpstan-ignore argument.type (resposta fora do contrato: proxy devolve erro escalar) */
    $list = ProcessingMessage::fromApiResult(['erro' => 'Bad Gateway']);

    expect($list)->toBeEmpty();
});

it('fromApiResult filtra itens não-mensagem da lista erros', function () {
    $list = ProcessingMessage::fromApiResult([
        'erros' => [
            ['codigo' => 'E1', 'descricao' => 'Erro real'],
            'lixo escalar',
            ['codigo' => 'E2'],
        ],
    ]);

    expect($list)->toHaveCount(2)
        ->and($list[0]->codigo)->toBe('E1')
        ->and($list[1]->codigo)->toBe('E2');
});

it('coerces non-string Mensagem to json preserving unicode and slashes', function () {
    /** @phpstan-ignore argument.type (testing runtime coercion of non-string values from API) */
    $msg = ProcessingMessage::fromArray([
        'Mensagem' => ['Tipo' => 'ALERTA', 'Descrição' => 'caminho/para/recurso'],
        'Codigo' => 'A001',
    ]);

    expect($msg)
        ->mensagem->toBe('{"Tipo":"ALERTA","Descrição":"caminho/para/recurso"}')
        ->codigo->toBe('A001');
});

it('coerces non-string values in all fields to json', function () {
    /** @phpstan-ignore argument.type (testing runtime coercion of non-string values from API) */
    $msg = ProcessingMessage::fromArray([
        'mensagem' => ['type' => 'error'],
        'codigo' => 123,
        'descricao' => ['detail' => 'oops'],
        'complemento' => true,
    ]);

    expect($msg)
        ->mensagem->toBe('{"type":"error"}')
        ->codigo->toBe('123')
        ->descricao->toBe('{"detail":"oops"}')
        ->complemento->toBe('true');
});

it('returns null for non-string non-encodable values', function () {
    /** @phpstan-ignore argument.type (testing runtime coercion of non-string values from API) */
    $msg = ProcessingMessage::fromArray([
        'mensagem' => null,
        'codigo' => null,
    ]);

    expect($msg)
        ->mensagem->toBeNull()
        ->codigo->toBeNull();
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

it('constructs with parametros', function () {
    $msg = new ProcessingMessage(
        mensagem: 'Msg',
        codigo: 'E001',
        parametros: ['param1', 'param2'],
    );

    expect($msg->parametros)->toBe(['param1', 'param2']);
});

it('defaults parametros to empty array', function () {
    $msg = new ProcessingMessage;

    expect($msg->parametros)->toBe([]);
});

it('creates from array with Parametros key', function () {
    $msg = ProcessingMessage::fromArray([
        'Codigo' => 'E001',
        'Parametros' => ['param1', 'param2'],
    ]);

    expect($msg->parametros)->toBe(['param1', 'param2']);
    expect($msg->codigo)->toBe('E001');
});

// Tolerância deliberada, não contrato: nenhum swagger declara `parametros` em
// minúscula — o da SEFIN não traz o campo em casing algum, e o do ADN só declara
// `Parametros`. A leitura existe porque a SEFIN nomeia todos os demais campos de
// mensagem em minúscula (`mensagem`, `codigo`, `descricao`, `complemento`), então é
// esse o casing que ela usaria se viesse a expor o campo. Manter é mais barato que
// perder dado em silêncio caso o swagger esteja incompleto.
it('tolerates a lowercase parametros key that no swagger declares', function () {
    $msg = ProcessingMessage::fromArray([
        'parametros' => ['p1'],
    ]);

    expect($msg->parametros)->toBe(['p1']);
});

it('defaults parametros to empty array when key absent in fromArray', function () {
    $msg = ProcessingMessage::fromArray(['codigo' => 'E001']);

    expect($msg->parametros)->toBe([]);
});
