<?php

use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Support\XmlDocumentLoader;
use Pulsar\NfseNacional\Xml\Builders\SubstituicaoBuilder;

function parseSubstituicaoXml(string $xml): DOMXPath
{
    $doc = new DOMDocument;
    $doc->loadXML($xml);
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

    return $xpath;
}

it('builds valid substituicao xml with chSubstituta', function (): void {
    $builder = new SubstituicaoBuilder(makeXsdValidator());
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
        ->toBe('PRE'.$chave.'105102001')
        ->and($xpath->evaluate('string(//n:chNFSe)'))->toBe($chave)
        ->and($xpath->evaluate('string(//n:nPedRegEvento)'))->toBe('1')
        ->and($xpath->query('//n:e105102')->length)->toBe(1)
        ->and($xpath->evaluate('string(//n:e105102/n:xDesc)'))->toBe('Cancelamento de NFS-e por Substituicao')
        ->and($xpath->evaluate('string(//n:e105102/n:cMotivo)'))->toBe('01')
        ->and($xpath->evaluate('string(//n:e105102/n:xMotivo)'))->toBe('Desenquadramento do Simples Nacional')
        ->and($xpath->evaluate('string(//n:e105102/n:chSubstituta)'))->toBe($chaveSub);
});

it('omits xMotivo when descricao is empty', function (): void {
    $builder = new SubstituicaoBuilder(makeXsdValidator());
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

it('generates correct Id with tipo 105102 and padded nPedRegEvento', function (): void {
    $builder = new SubstituicaoBuilder(makeXsdValidator());
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
        nPedRegEvento: 12,
    );

    $xpath = parseSubstituicaoXml($xml);

    expect($xpath->query('//n:infPedReg')->item(0)->getAttribute('Id'))
        ->toBe('PRE'.$chave.'105102012')
        ->and($xpath->evaluate('string(//n:nPedRegEvento)'))->toBe('12')
        ->and($xpath->evaluate('string(//n:CPFAutor)'))->toBe('12345678901')
        ->and($xpath->query('//n:CNPJAutor')->length)->toBe(0);
});

it('validates against pedRegEvento XSD', function (): void {
    $builder = new SubstituicaoBuilder(makeXsdValidator());
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
        descricao: 'Desenquadramento do Simples Nacional',
    );

    expect($xml)->toContain('<pedRegEvento');
});

it('throws NfseException when descricao is too short', function (): void {
    $builder = new SubstituicaoBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaSubstituicao::Outros,
        chSubstituta: '98765432109876543210987654321098765432109876543210',
        descricao: 'curto',
    ))->toThrow(NfseException::class, 'O campo descricao deve ter entre 15 e 255 caracteres.');
});

it('throws NfseException when descricao is too long', function (): void {
    $builder = new SubstituicaoBuilder(makeXsdValidator());

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
    $builder = new SubstituicaoBuilder(makeXsdValidator());

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
    $builder = new SubstituicaoBuilder(new \Pulsar\NfseNacional\Support\XsdValidator('/nonexistent/path'));
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

    $builder = new SubstituicaoBuilder(new \Pulsar\NfseNacional\Support\XsdValidator(__DIR__.'/../../../storage/schemes', xmlDocumentLoader: $loader));
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
