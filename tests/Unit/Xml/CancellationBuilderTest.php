<?php

covers(\OwnerPro\Nfsen\Xml\Builders\CancellationBuilder::class);

use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Support\XmlDocumentLoader;
use OwnerPro\Nfsen\Xml\Builders\CancellationBuilder;

function parseCancelamentoXml(string $xml): DOMXPath
{
    $doc = new DOMDocument;
    $doc->loadXML($xml);
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

    return $xpath;
}

it('builds valid cancelamento xml with CNPJ author', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());
    $chave = '12345678901234567890123456789012345678901234567890';

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: 'Erro na emissao da nota fiscal',
    );

    $xpath = parseCancelamentoXml($xml);

    expect($xpath->query('//n:pedRegEvento')->length)->toBe(1)
        ->and($xpath->query('//n:pedRegEvento')->item(0)->getAttribute('versao'))->toBe('1.01')
        ->and($xpath->query('//n:pedRegEvento')->item(0)->getAttribute('xmlns'))->toBe('http://www.sped.fazenda.gov.br/nfse')
        ->and($xpath->query('//n:infPedReg')->item(0)->getAttribute('Id'))
        ->toBe('PRE'.$chave.'101101')
        ->and($xpath->evaluate('string(//n:tpAmb)'))->toBe('2')
        ->and($xpath->evaluate('string(//n:verAplic)'))->toBe('1.0')
        ->and($xpath->evaluate('string(//n:dhEvento)'))->toBe('2026-03-01T10:00:00-03:00')
        ->and($xpath->evaluate('string(//n:CNPJAutor)'))->toBe('12345678000195')
        ->and($xpath->query('//n:CPFAutor')->length)->toBe(0)
        ->and($xpath->evaluate('string(//n:chNFSe)'))->toBe($chave)
        ->and($xpath->query('//n:e101101')->length)->toBe(1)
        ->and($xpath->evaluate('string(//n:e101101/n:xDesc)'))->toBe('Cancelamento de NFS-e')
        ->and($xpath->evaluate('string(//n:e101101/n:cMotivo)'))->toBe('1')
        ->and($xpath->evaluate('string(//n:e101101/n:xMotivo)'))->toBe('Erro na emissao da nota fiscal');
});

it('builds valid cancelamento xml with CPF author', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());
    $chave = '12345678901234567890123456789012345678901234567890';

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: null,
        cpfAutor: '12345678901',
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaCancelamento::ServicoNaoPrestado,
        descricao: 'Servico nao foi prestado',
    );

    $xpath = parseCancelamentoXml($xml);

    expect($xpath->query('//n:CNPJAutor')->length)->toBe(0)
        ->and($xpath->evaluate('string(//n:CPFAutor)'))->toBe('12345678901')
        ->and($xpath->evaluate('string(//n:e101101/n:cMotivo)'))->toBe('2');
});

it('validates against pedRegEvento XSD', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());
    $chave = '12345678901234567890123456789012345678901234567890';

    $xml = $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: 'Erro na emissao da nota fiscal',
    );

    $xpath = parseCancelamentoXml($xml);

    expect($xml)->toContain('<pedRegEvento')
        ->and($xpath->query('//n:infPedReg')->item(0)->getAttribute('Id'))
        ->toBe('PRE'.$chave.'101101');
});

it('throws when both cnpjAutor and cpfAutor are set', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());

    expect(fn () => $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: '12345678901',
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: 'Erro na emissao da nota fiscal',
    ))->toThrow(InvalidArgumentException::class, 'não ambos');
});

it('throws when neither cnpjAutor nor cpfAutor is set', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());

    expect(fn () => $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: null,
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: 'Erro na emissao da nota fiscal',
    ))->toThrow(InvalidArgumentException::class, 'obrigatório');
});

it('builds xml without formatting or newlines', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());

    $xml = $builder->build(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: 'Erro na emissao da nota fiscal',
    );

    expect($xml)->not->toContain("\n");
});

it('accepts descricao with exactly 15 characters', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());

    $xml = $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: str_repeat('A', 15),
    );

    expect($xml)->toContain('<pedRegEvento');
});

it('accepts descricao with exactly 255 characters', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());

    $xml = $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: str_repeat('A', 255),
    );

    expect($xml)->toContain('<pedRegEvento');
});

it('throws NfseException when descricao has 14 characters', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: str_repeat('A', 14),
    ))->toThrow(NfseException::class, 'O campo descricao deve ter entre 15 e 255 caracteres.');
});

it('throws NfseException when descricao has 256 characters', function (): void {
    $builder = new CancellationBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: '12345678901234567890123456789012345678901234567890',
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: str_repeat('A', 256),
    ))->toThrow(NfseException::class, 'O campo descricao deve ter entre 15 e 255 caracteres.');
});

it('throws NfseException when scheme file does not exist', function (): void {
    $builder = new CancellationBuilder(new \OwnerPro\Nfsen\Support\XsdValidator('/nonexistent/path'));
    $chave = '12345678901234567890123456789012345678901234567890';

    expect(fn () => $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: 'Erro na emissao da nota fiscal',
    ))->toThrow(NfseException::class, 'Schema XSD não encontrado');
});

it('throws NfseException when XML loading fails', function (): void {
    $loader = Mockery::mock(XmlDocumentLoader::class);
    $loader->shouldReceive('__invoke')->andReturn(false);

    $builder = new CancellationBuilder(new \OwnerPro\Nfsen\Support\XsdValidator(__DIR__.'/../../../storage/schemes', xmlDocumentLoader: $loader));
    $chave = '12345678901234567890123456789012345678901234567890';

    expect(fn () => $builder->buildAndValidate(
        tpAmb: 2,
        verAplic: '1.0',
        dhEvento: '2026-03-01T10:00:00-03:00',
        cnpjAutor: '12345678000195',
        cpfAutor: null,
        chNFSe: $chave,
        codigoMotivo: CodigoJustificativaCancelamento::ErroEmissao,
        descricao: 'Erro na emissao da nota fiscal',
    ))->toThrow(NfseException::class, 'falha ao carregar documento');
});
