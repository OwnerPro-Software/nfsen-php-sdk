<?php

use Pulsar\NfseNacional\Responses\MensagemProcessamento;
use Pulsar\NfseNacional\Responses\NfseResponse;

it('success response carries chave and empty erros', function () {
    $response = new NfseResponse(true, 'chave123', '<NFSe/>');

    expect($response)
        ->sucesso->toBeTrue()
        ->chave->toBe('chave123')
        ->xml->toBe('<NFSe/>')
        ->idDps->toBeNull()
        ->alertas->toBeEmpty()
        ->erros->toBeEmpty();
});

it('failure response carries erros and no chave', function () {
    $erros = [new MensagemProcessamento(descricao: 'E001 - Erro', codigo: 'E001')];

    $response = new NfseResponse(false, erros: $erros);

    expect($response)
        ->sucesso->toBeFalse()
        ->chave->toBeNull()
        ->xml->toBeNull()
        ->idDps->toBeNull()
        ->alertas->toBeEmpty();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('E001 - Erro');
});

it('success response can carry xml with decoded gzip content', function () {
    $originalXml = '<NFSe xmlns="http://www.sped.fazenda.gov.br/nfse"><infNFSe/></NFSe>';
    $decoded = gzdecode(base64_decode(base64_encode((string) gzencode($originalXml)))) ?: null;

    $response = new NfseResponse(true, xml: $decoded);

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toBe($originalXml);
});

it('success response without xml has null xml', function () {
    $response = new NfseResponse(true, 'CHAVE50');

    expect($response)
        ->sucesso->toBeTrue()
        ->chave->toBe('CHAVE50')
        ->xml->toBeNull();
});

it('success response without chave has null chave', function () {
    $response = new NfseResponse(true);

    expect($response)
        ->sucesso->toBeTrue()
        ->chave->toBeNull();
});

it('success response carries idDps', function () {
    $response = new NfseResponse(true, 'CHAVE123', idDps: 'DPS001');

    expect($response)
        ->sucesso->toBeTrue()
        ->chave->toBe('CHAVE123')
        ->idDps->toBe('DPS001');
});

it('success response carries alertas', function () {
    $alertas = [
        new MensagemProcessamento(codigo: 'A001', descricao: 'Alerta de teste'),
        new MensagemProcessamento(codigo: 'A002', descricao: 'Outro alerta'),
    ];

    $response = new NfseResponse(true, 'CHAVE123', alertas: $alertas);

    expect($response->alertas)->toHaveCount(2);
    expect($response->alertas[0]->codigo)->toBe('A001');
    expect($response->alertas[1]->descricao)->toBe('Outro alerta');
});

it('failure response carries multiple erros', function () {
    $erros = [
        new MensagemProcessamento(codigo: 'E001', descricao: 'Primeiro erro'),
        new MensagemProcessamento(codigo: 'E002', descricao: 'Segundo erro'),
    ];

    $response = new NfseResponse(false, erros: $erros);

    expect($response->erros)->toHaveCount(2);
    expect($response->erros[0]->codigo)->toBe('E001');
    expect($response->erros[1]->codigo)->toBe('E002');
});

it('defaults all optional fields to null or empty', function () {
    $response = new NfseResponse(true);

    expect($response)
        ->chave->toBeNull()
        ->xml->toBeNull()
        ->idDps->toBeNull()
        ->alertas->toBeEmpty()
        ->erros->toBeEmpty()
        ->tipoAmbiente->toBeNull()
        ->versaoAplicativo->toBeNull()
        ->dataHoraProcessamento->toBeNull();
});

it('carries metadata fields', function () {
    $response = new NfseResponse(
        sucesso: true,
        chave: 'CHAVE123',
        tipoAmbiente: 2,
        versaoAplicativo: '1.0.0',
        dataHoraProcessamento: '2026-03-02T12:00:00-03:00',
    );

    expect($response)
        ->tipoAmbiente->toBe(2)
        ->versaoAplicativo->toBe('1.0.0')
        ->dataHoraProcessamento->toBe('2026-03-02T12:00:00-03:00');
});
