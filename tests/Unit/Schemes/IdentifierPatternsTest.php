<?php

function embeddedSimpleTypeAccepts(string $type, string $value): bool
{
    $schema = sprintf(
        '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns="http://www.sped.fazenda.gov.br/nfse"'
        .' targetNamespace="http://www.sped.fazenda.gov.br/nfse" elementFormDefault="qualified">'
        .'<xs:include schemaLocation="file://%s"/><xs:element name="value" type="%s"/></xs:schema>',
        realpath(__DIR__.'/../../../storage/schemes/tiposSimples_v1.01.xsd'),
        $type,
    );

    $document = new DOMDocument;
    $root = $document->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'value');
    $root->appendChild($document->createTextNode($value));
    $document->appendChild($root);

    $previous = libxml_use_internal_errors(true);

    try {
        return $document->schemaValidateSource($schema);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

const NUMERIC_CHAVE = '33033021211222333000181000000000001026010000010000';
const EDGE_LETTERS_CHAVE = '330330212'.'A234567890123Z'.'000000000000126010000010000';

it('accepts identifiers that carry an alphanumeric CNPJ', function (string $type, string $value) {
    expect(embeddedSimpleTypeAccepts($type, $value))->toBeTrue();
})->with([
    'TSCNPJ numérico' => ['TSCNPJ', '11222333000181'],
    'TSCNPJ alfanumérico' => ['TSCNPJ', '12ABC34501DE35'],
    'TSChaveNFSe numérica' => ['TSChaveNFSe', NUMERIC_CHAVE],
    'TSChaveNFSe com letra na 12ª posição do CNPJ' => ['TSChaveNFSe', '330330212'.'12ABC34501DE35'.'000000000000126010000010000'],
    'TSChaveNFSe com letras nas bordas da inscrição' => ['TSChaveNFSe', EDGE_LETTERS_CHAVE],
    'TSChaveNFSe sem tipo 1 ou 2 na 9ª posição' => ['TSChaveNFSe', '12345678901234567890123456789012345678901234567890'],
    'TSIdNFSe' => ['TSIdNFSe', 'NFS'.EDGE_LETTERS_CHAVE],
    'TSIdDPS de CNPJ alfanumérico' => ['TSIdDPS', 'DPS3501608'.'2'.'12ABC34501DE35'.'00001'.'000000000000001'],
    'TSIdPedRegEvt' => ['TSIdPedRegEvt', 'PRE'.EDGE_LETTERS_CHAVE.'101101'],
    'TSIdPedRegEvt sem tipo 1 ou 2 na 9ª posição' => ['TSIdPedRegEvt', 'PRE'.'12345678901234567890123456789012345678901234567890'.'101101'],
    'TSIdEvento' => ['TSIdEvento', 'EVT'.EDGE_LETTERS_CHAVE.'101101'.'001'],
    'TSIdNumEvento com as 59 posições do pacote de produção' => ['TSIdNumEvento', NUMERIC_CHAVE.'205208'.'001'],
    'TSIdNumEvento com as 56 posições do pacote de 24/11/2026' => ['TSIdNumEvento', NUMERIC_CHAVE.'205208'],
    'TSIdNumEvento alfanumérico' => ['TSIdNumEvento', EDGE_LETTERS_CHAVE.'205208'.'001'],
]);

it('refuses identifiers with letters outside the inscrição federal or of the wrong width', function (string $type, string $value) {
    expect(embeddedSimpleTypeAccepts($type, $value))->toBeFalse();
})->with([
    'TSCNPJ em minúsculas' => ['TSCNPJ', '12abc34501de35'],
    'TSCNPJ com máscara' => ['TSCNPJ', '12.ABC.345/01DE-35'],
    'TSCNPJ com 13 posições' => ['TSCNPJ', '12ABC34501DE3'],
    'TSChaveNFSe com letra na posição 9' => ['TSChaveNFSe', '33033021A'.'12345678000195'.'000000000000126010000010000'],
    'TSChaveNFSe com letra na posição 24' => ['TSChaveNFSe', '330330212'.'12345678000195'.'A00000000000126010000010000'],
    'TSChaveNFSe com 49 posições' => ['TSChaveNFSe', substr(NUMERIC_CHAVE, 1)],
    'TSIdDPS de CPF com letras' => ['TSIdDPS', 'DPS3501608'.'1'.'12ABC34501DE35'.'00001'.'000000000000001'],
    'TSIdPedRegEvt com letra na posição 9 da chave' => ['TSIdPedRegEvt', 'PRE'.'33033021A'.'12345678000195'.'000000000000126010000010000'.'101101'],
    'TSIdEvento com 61 posições' => ['TSIdEvento', 'EVT'.NUMERIC_CHAVE.'101101'.'00'],
    'TSIdNumEvento com 57 posições' => ['TSIdNumEvento', NUMERIC_CHAVE.'205208'.'0'],
    'TSIdNumEvento com 60 posições' => ['TSIdNumEvento', NUMERIC_CHAVE.'205208'.'0010'],
]);
