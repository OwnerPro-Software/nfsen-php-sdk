<?php

use OwnerPro\Nfsen\Responses\HttpResponse;

covers(HttpResponse::class);

it('constructs with all fields', function () {
    $response = new HttpResponse(
        statusCode: 200,
        json: ['StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS'],
        body: '{"StatusProcessamento":"DOCUMENTOS_LOCALIZADOS"}',
    );

    expect($response)
        ->statusCode->toBe(200)
        ->body->toBe('{"StatusProcessamento":"DOCUMENTOS_LOCALIZADOS"}');
    expect($response->json)->toBe(['StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS']);
});

it('constructs with empty json and non-json body', function () {
    $response = new HttpResponse(
        statusCode: 429,
        json: [],
        body: 'Rate limit exceeded',
    );

    expect($response)
        ->statusCode->toBe(429)
        ->body->toBe('Rate limit exceeded');
    expect($response->json)->toBe([]);
});
