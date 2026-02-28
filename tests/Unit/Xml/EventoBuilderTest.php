<?php

use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Xml\Builders\EventoBuilder;

it('builds evento xml for e101101', function () {
    $builder = new EventoBuilder();

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-02-27T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: 'CHAVE50CARACTERES1234567890123456789012345678901',
        motivo: MotivoCancelamento::ErroEmissao,
        descricao: 'Erro ao emitir',
    );

    expect($xml)->toContain('<pedRegEvento');
    expect($xml)->toContain('<infPedReg Id="PRECHAVE50CARACTERES1234567890123456789012345678901101101"');
    expect($xml)->toContain('<chNFSe>CHAVE50CARACTERES1234567890123456789012345678901</chNFSe>');
    expect($xml)->toContain('<e101101>');
    expect($xml)->toContain('<xDesc>Cancelamento de NFS-e</xDesc>');
    expect($xml)->toContain('<cMotivo>e101101</cMotivo>');
    expect($xml)->toContain('<xMotivo>Erro ao emitir</xMotivo>');
});

it('builds evento xml for e105102', function () {
    $builder = new EventoBuilder();

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-02-27T10:00:00-03:00',
        cnpjAutor: null,
        cpfAutor: '12345678901',
        chNFSe: 'CHAVE50CARACTERES1234567890123456789012345678901',
        motivo: MotivoCancelamento::Outros,
        descricao: 'Motivo diverso',
    );

    expect($xml)->toContain('<infPedReg Id="PRECHAVE50CARACTERES1234567890123456789012345678901105102"');
    expect($xml)->toContain('<e105102>');
    expect($xml)->toContain('<xDesc>Cancelamento de NFS-e por Substituicao</xDesc>');
    expect($xml)->toContain('<CPFAutor>12345678901</CPFAutor>');
});
