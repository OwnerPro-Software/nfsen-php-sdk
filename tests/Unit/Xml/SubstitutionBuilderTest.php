<?php

covers(\Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder::class);

use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Support\XmlDocumentLoader;
use Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder;

function parseSubstituicaoXml(string $xml): DOMXPath
{
    $doc = new DOMDocument;
    $doc->loadXML($xml);
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

    return $xpath;
}

it('builds valid substituicao xml with chSubstituta', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
        chSubstituta: $chaveSub,
        descricao: 'Desenquadramento do Simples Nacional',
    );

    $xpath = parseSubstituicaoXml($xml);

    expect($xpath->query('//n:pedRegEvento')->length)->toBe(1)
        ->and($xpath->query('//n:infPedReg')->item(0)->getAttribute('Id'))
        ->toBe('PRE'.$chave.'105102')
        ->and($xpath->evaluate('string(//n:chNFSe)'))->toBe($chave)
        ->and($xpath->query('//n:e105102')->length)->toBe(1)
        ->and($xpath->evaluate('string(//n:e105102/n:xDesc)'))->toBe('Cancelamento de NFS-e por Substituição')
        ->and($xpath->evaluate('string(//n:e105102/n:cMotivo)'))->toBe('01')
        ->and($xpath->evaluate('string(//n:e105102/n:xMotivo)'))->toBe('Desenquadramento do Simples Nacional')
        ->and($xpath->evaluate('string(//n:e105102/n:chSubstituta)'))->toBe($chaveSub);
});

it('omits xMotivo when descricao is null', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: $chaveSub,
    );

    $xpath = parseSubstituicaoXml($xml);

    expect($xpath->query('//n:e105102/n:xMotivo')->length)->toBe(0)
        ->and($xpath->evaluate('string(//n:e105102/n:cMotivo)'))->toBe('99');
});

it('validates against pedRegEvento XSD with empty descricao', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    $xml = $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
        chSubstituta: $chaveSub,
    );

    $xpath = parseSubstituicaoXml($xml);

    expect($xml)->toContain('<pedRegEvento')
        ->and($xpath->query('//n:infPedReg')->item(0)->getAttribute('Id'))
        ->toBe('PRE'.$chave.'105102')
        ->and($xpath->query('//n:e105102/n:xMotivo')->length)->toBe(0);
});

it('builds valid substituicao xml with CPF author', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    $xml = $builder->build(
        tpAmb: 1,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: null,
        cpfAutor: '12345678901',
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaSubstituicao::EnquadramentoSimplesNacional,
        chSubstituta: $chaveSub,
        descricao: 'Enquadramento do Simples Nacional',
    );

    $xpath = parseSubstituicaoXml($xml);

    expect($xpath->evaluate('string(//n:CPFAutor)'))->toBe('12345678901')
        ->and($xpath->query('//n:CNPJAutor')->length)->toBe(0);
});

it('throws when both cnpjAutor and cpfAutor are set', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());

    expect(fn () => $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: '12345678901',
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: '98765432109876543210987654321098765432109876543210',
        descricao: 'Outro motivo para substituicao',
    ))->toThrow(InvalidArgumentException::class, 'não ambos');
});

it('throws when neither cnpjAutor nor cpfAutor is set', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());

    expect(fn () => $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: null,
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: '98765432109876543210987654321098765432109876543210',
        descricao: 'Outro motivo para substituicao',
    ))->toThrow(InvalidArgumentException::class, 'obrigatório');
});

it('builds xml without formatting or newlines', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: '98765432109876543210987654321098765432109876543210',
        descricao: 'Outro motivo para substituicao',
    );

    expect($xml)->not->toContain("\n");
});

it('accepts descricao with exactly 15 characters', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());

    $xml = $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: '98765432109876543210987654321098765432109876543210',
        descricao: str_repeat('A', 15),
    );

    expect($xml)->toContain('<pedRegEvento');
});

it('accepts descricao with exactly 255 characters', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());

    $xml = $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: '98765432109876543210987654321098765432109876543210',
        descricao: str_repeat('A', 255),
    );

    expect($xml)->toContain('<pedRegEvento');
});

it('throws NfseException when descricao has 14 characters', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: '98765432109876543210987654321098765432109876543210',
        descricao: str_repeat('A', 14),
    ))->toThrow(NfseException::class, 'O campo descricao deve ter entre 15 e 255 caracteres.');
});

it('throws NfseException when descricao has 256 characters', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: '98765432109876543210987654321098765432109876543210',
        descricao: str_repeat('A', 256),
    ))->toThrow(NfseException::class, 'O campo descricao deve ter entre 15 e 255 caracteres.');
});

it('skips descricao validation when empty', function (): void {
    $builder = new SubstitutionBuilder(makeXsdValidator());

    $xml = $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: '98765432109876543210987654321098765432109876543210',
    );

    expect($xml)->toContain('<pedRegEvento');
});

it('throws NfseException when scheme file does not exist', function (): void {
    $builder = new SubstitutionBuilder(new \Pulsar\NfseNacional\Support\XsdValidator('/nonexistent/path'));
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: $chaveSub,
    ))->toThrow(NfseException::class, 'Schema XSD não encontrado');
});

it('throws NfseException when XML loading fails', function (): void {
    $loader = Mockery::mock(XmlDocumentLoader::class);
    $loader->shouldReceive('__invoke')->andReturn(false);

    $builder = new SubstitutionBuilder(new \Pulsar\NfseNacional\Support\XsdValidator(__DIR__.'/../../../storage/schemes', xmlDocumentLoader: $loader));
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: $chaveSub,
    ))->toThrow(NfseException::class, 'falha ao carregar documento');
});
