<?php

use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Dps\DTO\Shared\EnderecoExterior;
use Pulsar\NfseNacional\Dps\DTO\Shared\EnderecoNacional;
use Pulsar\NfseNacional\Dps\DTO\Tomador\Tomador;
use Pulsar\NfseNacional\Dps\Enums\Shared\CodNaoNIF;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;
use Pulsar\NfseNacional\Xml\Builders\TomadorBuilder;

it('builds toma element with CNPJ', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(CNPJ: '98765432000111', xNome: 'Tomador Ltda');

    $element = $builder->build($doc, $toma);

    expect($doc->saveXML($element))->toContain('<CNPJ>98765432000111</CNPJ>');
    expect($doc->saveXML($element))->toContain('<xNome>Tomador Ltda</xNome>');
});

it('builds toma element with CPF', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(CPF: '12345678901', xNome: 'Pessoa Física');

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<CPF>12345678901</CPF>')
        ->not->toContain('<CNPJ>');
});

it('builds toma element with NIF', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(NIF: 'NIF12345', xNome: 'Estrangeiro');

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<NIF>NIF12345</NIF>')
        ->not->toContain('<CNPJ>')
        ->not->toContain('<CPF>');
});

it('builds toma element with cNaoNIF', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(cNaoNIF: CodNaoNIF::Dispensado, xNome: 'Estrangeiro');

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<cNaoNIF>1</cNaoNIF>')
        ->not->toContain('<NIF>');
});

it('includes CAEPF and IM when set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(CPF: '12345678901', xNome: 'Pessoa', CAEPF: '12345678901234', IM: '1234567');

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<CAEPF>12345678901234</CAEPF>')
        ->toContain('<IM>1234567</IM>');
});

it('builds endNac address block', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(
        CNPJ: '98765432000111',
        xNome: 'Tomador',
        end: new Endereco(
            xLgr: 'Rua Teste',
            nro: '100',
            xBairro: 'Centro',
            endNac: new EnderecoNacional(cMun: '3501608', CEP: '01001000'),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<endNac>')
        ->toContain('<cMun>3501608</cMun>')
        ->toContain('<CEP>01001000</CEP>')
        ->toContain('<xLgr>Rua Teste</xLgr>')
        ->toContain('<nro>100</nro>')
        ->toContain('<xBairro>Centro</xBairro>')
        ->not->toContain('<endExt>');
});

it('builds endExt address block', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(
        CNPJ: '98765432000111',
        xNome: 'Tomador',
        end: new Endereco(
            xLgr: '5th Avenue',
            nro: '200',
            xBairro: 'Manhattan',
            endExt: new EnderecoExterior(cPais: '01058', cEndPost: '10001', xCidade: 'New York', xEstProvReg: 'NY'),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<endExt>')
        ->toContain('<cPais>01058</cPais>')
        ->toContain('<cEndPost>10001</cEndPost>')
        ->toContain('<xCidade>New York</xCidade>')
        ->toContain('<xEstProvReg>NY</xEstProvReg>')
        ->not->toContain('<endNac>');
});

it('includes xCpl in address when set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(
        CNPJ: '98765432000111',
        xNome: 'Tomador',
        end: new Endereco(
            xLgr: 'Rua Teste',
            nro: '100',
            xBairro: 'Centro',
            endNac: new EnderecoNacional(cMun: '3501608', CEP: '01001000'),
            xCpl: 'Sala 5',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)->toContain('<xCpl>Sala 5</xCpl>');
});

it('throws when both CNPJ and CPF are set', function () {
    expect(fn () => new Tomador(CNPJ: '98765432000111', CPF: '12345678901', xNome: 'Tomador'))
        ->toThrow(InvalidDpsArgument::class, 'exatamente um');
});

it('throws when no identification is set', function () {
    expect(fn () => new Tomador(xNome: 'Tomador'))
        ->toThrow(InvalidDpsArgument::class, 'exatamente um');
});

it('includes fone and email when set', function () {
    $builder = new TomadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $toma = new Tomador(
        CNPJ: '98765432000111',
        xNome: 'Tomador',
        fone: '11999998888',
        email: 'tomador@test.com',
    );

    $xml = $doc->saveXML($builder->build($doc, $toma));

    expect($xml)
        ->toContain('<fone>11999998888</fone>')
        ->toContain('<email>tomador@test.com</email>');
});
