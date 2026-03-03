<?php

use Pulsar\NfseNacional\Builders\Xml\DpsBuilder;
use Pulsar\NfseNacional\Builders\Xml\Parts\TomadorBuilder;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\SubstituicaoData;
use Pulsar\NfseNacional\Dps\DTO\Tomador\Tomador;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;

it('builds DPS with subst element that validates against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        subst: new SubstituicaoData(
            chSubstda: '12345678901234567890123456789012345678901234567890',
            cMotivo: CodigoJustificativaSubstituicao::Outros,
            xMotivo: 'Motivo de teste para substituição da nota fiscal',
        ),
        prest: makePrestadorCnpj(),
        toma: null,
        interm: null,
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)
        ->toContain('<subst>')
        ->toContain('<chSubstda>12345678901234567890123456789012345678901234567890</chSubstda>')
        ->toContain('<cMotivo>99</cMotivo>')
        ->toContain('<xMotivo>Motivo de teste para substituição da nota fiscal</xMotivo>');
});

it('builds DPS with subst without xMotivo that validates against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        subst: new SubstituicaoData(
            chSubstda: '12345678901234567890123456789012345678901234567890',
            cMotivo: CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
        ),
        prest: makePrestadorCnpj(),
        toma: null,
        interm: null,
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)
        ->toContain('<subst>')
        ->toContain('<cMotivo>01</cMotivo>')
        ->not->toContain('<xMotivo>');
});

it('builds DPS with interm element that validates against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        subst: null,
        prest: makePrestadorCnpj(),
        toma: new Tomador(CNPJ: '98765432000111', xNome: 'Tomador Ltda'),
        interm: new Tomador(CNPJ: '11222333000144', xNome: 'Intermediário Ltda'),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)
        ->toContain('<interm>')
        ->toContain('<xNome>Intermediário Ltda</xNome>');
});

it('builds DPS with both subst and interm that validates against XSD', function () {
    $data = new DpsData(
        infDPS: makeInfDps(),
        subst: new SubstituicaoData(
            chSubstda: '12345678901234567890123456789012345678901234567890',
            cMotivo: CodigoJustificativaSubstituicao::RejeicaoTomadorIntermediario,
        ),
        prest: makePrestadorCnpj(),
        toma: new Tomador(CNPJ: '98765432000111', xNome: 'Tomador Ltda'),
        interm: new Tomador(CPF: '12345678901', xNome: 'Intermediário PF'),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
    );

    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->buildAndValidate($data);

    expect($xml)
        ->toContain('<subst>')
        ->toContain('<interm>');
});

it('builds interm element with custom element name via TomadorBuilder', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(CNPJ: '98765432000111', xNome: 'Intermediário');

    $xml = $doc->saveXML($builder->build($doc, $toma, 'interm'));

    expect($xml)
        ->toContain('<interm>')
        ->toContain('</interm>')
        ->not->toContain('<toma>');
});
