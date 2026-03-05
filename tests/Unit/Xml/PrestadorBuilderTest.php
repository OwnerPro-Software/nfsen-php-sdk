<?php

covers(\Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder::class);

use Pulsar\NfseNacional\Dps\DTO\Prestador\Prestador;
use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Dps\DTO\Shared\EnderecoExterior;
use Pulsar\NfseNacional\Dps\DTO\Shared\EnderecoNacional;
use Pulsar\NfseNacional\Dps\DTO\Shared\RegTrib;
use Pulsar\NfseNacional\Dps\Enums\Prestador\OpSimpNac;
use Pulsar\NfseNacional\Dps\Enums\Prestador\RegApTribSN;
use Pulsar\NfseNacional\Dps\Enums\Prestador\RegEspTrib;
use Pulsar\NfseNacional\Dps\Enums\Shared\CNaoNIF;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;
use Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder;

function makeRegTrib(): RegTrib
{
    return new RegTrib(opSimpNac: OpSimpNac::NaoOptante, regEspTrib: RegEspTrib::Nenhum);
}

it('builds prest element with CNPJ', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(CNPJ: '12345678000195', regTrib: makeRegTrib(), xNome: 'Empresa Teste');

    $element = $builder->build($doc, $prest);
    $doc->appendChild($element);

    $xml = $doc->saveXML($element);
    expect($xml)->toContain('<CNPJ>12345678000195</CNPJ>');
    expect($xml)->toContain('<xNome>Empresa Teste</xNome>');
    expect($xml)->toContain('<regTrib>');
    expect($xml)->toContain('<opSimpNac>1</opSimpNac>');
    expect($xml)->toContain('<regEspTrib>0</regEspTrib>');
    expect($xml)->not->toContain('<end>');
    expect($xml)->not->toContain('<fone>');
    expect($xml)->not->toContain('<email>');
});

it('builds prest element with CPF when no CNPJ', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(
        CPF: '12345678901',
        regTrib: new RegTrib(opSimpNac: OpSimpNac::NaoOptante, regEspTrib: RegEspTrib::Nenhum),
        xNome: 'Pessoa Física',
    );

    $element = $builder->build($doc, $prest);

    expect($doc->saveXML($element))->toContain('<CPF>12345678901</CPF>');
});

it('includes CAEPF and IM when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(
        CNPJ: '12345678000195',
        regTrib: makeRegTrib(),
        CAEPF: '12345678901234',
        IM: '9876543',
        xNome: 'Empresa',
    );

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<CAEPF>12345678901234</CAEPF>')
        ->toContain('<IM>9876543</IM>');
});

it('builds prest element with NIF', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(
        NIF: 'NIF999',
        regTrib: new RegTrib(opSimpNac: OpSimpNac::NaoOptante, regEspTrib: RegEspTrib::Nenhum),
        xNome: 'Empresa Estrangeira',
    );

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<NIF>NIF999</NIF>')
        ->not->toContain('<CNPJ>')
        ->not->toContain('<CPF>');
});

it('builds prest element with cNaoNIF', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(
        cNaoNIF: CNaoNIF::Dispensado,
        regTrib: new RegTrib(opSimpNac: OpSimpNac::NaoOptante, regEspTrib: RegEspTrib::Nenhum),
        xNome: 'Empresa Estrangeira',
    );

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<cNaoNIF>1</cNaoNIF>')
        ->not->toContain('<CNPJ>')
        ->not->toContain('<CPF>')
        ->not->toContain('<NIF>');
});

it('throws when both CNPJ and CPF are set', function () {
    expect(fn () => new Prestador(
        CNPJ: '12345678000195',
        CPF: '12345678901',
        regTrib: makeRegTrib(),
        xNome: 'Empresa',
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when no identification is set', function () {
    expect(fn () => new Prestador(
        regTrib: makeRegTrib(),
        xNome: 'Empresa',
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('includes fone and email when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(
        CNPJ: '12345678000195',
        regTrib: makeRegTrib(),
        xNome: 'Empresa',
        fone: '11999998888',
        email: 'empresa@test.com',
    );

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<fone>11999998888</fone>')
        ->toContain('<email>empresa@test.com</email>');
});

it('includes regApTribSN when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(
        CNPJ: '12345678000195',
        regTrib: new RegTrib(
            opSimpNac: OpSimpNac::NaoOptante,
            regEspTrib: RegEspTrib::Nenhum,
            regApTribSN: RegApTribSN::ApuracaoSNIssqnFora,
        ),
        xNome: 'Empresa',
    );

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)->toContain('<regApTribSN>2</regApTribSN>');
});

it('builds endNac address block', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(
        CNPJ: '12345678000195',
        regTrib: makeRegTrib(),
        xNome: 'Empresa',
        end: new Endereco(
            xLgr: 'Rua Teste',
            nro: '100',
            xBairro: 'Centro',
            endNac: new EnderecoNacional(cMun: '3501608', CEP: '01001000'),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<endNac>')
        ->toContain('<cMun>3501608</cMun>')
        ->toContain('<CEP>01001000</CEP>')
        ->toContain('<xLgr>Rua Teste</xLgr>')
        ->toContain('<nro>100</nro>')
        ->toContain('<xBairro>Centro</xBairro>');
});

it('builds endExt address block', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(
        CNPJ: '12345678000195',
        regTrib: makeRegTrib(),
        xNome: 'Empresa',
        end: new Endereco(
            xLgr: '5th Avenue',
            nro: '200',
            xBairro: 'Manhattan',
            endExt: new EnderecoExterior(cPais: '01058', cEndPost: '10001', xCidade: 'New York', xEstProvReg: 'NY'),
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)
        ->toContain('<endExt>')
        ->toContain('<cPais>01058</cPais>')
        ->toContain('<cEndPost>10001</cEndPost>')
        ->toContain('<xCidade>New York</xCidade>')
        ->toContain('<xEstProvReg>NY</xEstProvReg>');
});

it('includes xCpl in address when set', function () {
    $builder = new PrestadorBuilder;
    $doc = new DOMDocument('1.0', 'UTF-8');

    $prest = new Prestador(
        CNPJ: '12345678000195',
        regTrib: makeRegTrib(),
        xNome: 'Empresa',
        end: new Endereco(
            xLgr: 'Rua Teste',
            nro: '100',
            xBairro: 'Centro',
            endNac: new EnderecoNacional(cMun: '3501608', CEP: '01001000'),
            xCpl: 'Andar 5',
        ),
    );

    $xml = $doc->saveXML($builder->build($doc, $prest));

    expect($xml)->toContain('<xCpl>Andar 5</xCpl>');
});
