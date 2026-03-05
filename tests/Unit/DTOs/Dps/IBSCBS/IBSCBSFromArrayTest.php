<?php

covers(
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\GTribRegular::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\GDif::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\GIBSCBS::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\Trib::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\Valores::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\Dest::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\Imovel::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\DFeNacional::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\DocFiscalOutro::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\DocOutro::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\Fornec::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\Documentos::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\GReeRepRes::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\IBSCBS::class,
);

use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Dest;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\DFeNacional;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\DocFiscalOutro;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\DocOutro;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Documentos;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Fornec;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\GDif;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\GIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\GReeRepRes;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\GTribRegular;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\IBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Imovel;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Trib;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\Valores;
use Pulsar\NfseNacional\Dps\Enums\Shared\CNaoNIF;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('GTribRegular::fromArray creates instance from array', function () {
    $dto = GTribRegular::fromArray(['CSTReg' => '00', 'cClassTribReg' => '001']);
    expect($dto)->toBeInstanceOf(GTribRegular::class);
});

it('GDif::fromArray creates instance from array', function () {
    $dto = GDif::fromArray(['pDifUF' => '10.00', 'pDifMun' => '5.00', 'pDifCBS' => '3.00']);
    expect($dto)->toBeInstanceOf(GDif::class);
});

it('GIBSCBS::fromArray creates instance from array', function () {
    $dto = GIBSCBS::fromArray(['CST' => '00', 'cClassTrib' => '001', 'cCredPres' => 'CRED123']);

    expect($dto)->toBeInstanceOf(GIBSCBS::class)
        ->and($dto->cCredPres)->toBe('CRED123');
});

it('Trib::fromArray creates instance from array', function () {
    $dto = Trib::fromArray(['gIBSCBS' => ['CST' => '00', 'cClassTrib' => '001']]);
    expect($dto)->toBeInstanceOf(Trib::class);
});

it('Valores::fromArray creates instance from array', function () {
    $dto = Valores::fromArray(['trib' => ['gIBSCBS' => ['CST' => '00', 'cClassTrib' => '001']]]);
    expect($dto)->toBeInstanceOf(Valores::class);
});

it('Dest::fromArray preserves CNPJ and optional fields', function () {
    $dto = Dest::fromArray([
        'xNome' => 'Destinatario', 'CNPJ' => '12345678000195',
        'fone' => '11777777777', 'email' => 'dest@test.com',
    ]);

    expect($dto)->toBeInstanceOf(Dest::class)
        ->and($dto->CNPJ)->toBe('12345678000195')
        ->and($dto->fone)->toBe('11777777777')
        ->and($dto->email)->toBe('dest@test.com');
});

it('Dest::fromArray preserves CPF', function () {
    $dto = Dest::fromArray(['xNome' => 'D', 'CPF' => '12345678901']);
    expect($dto->CPF)->toBe('12345678901');
});

it('Dest::fromArray preserves NIF', function () {
    $dto = Dest::fromArray(['xNome' => 'D', 'NIF' => 'NIF789']);
    expect($dto->NIF)->toBe('NIF789');
});

it('Dest::fromArray preserves cNaoNIF', function () {
    $dto = Dest::fromArray(['xNome' => 'D', 'cNaoNIF' => '2']);
    expect($dto->cNaoNIF)->toBe(CNaoNIF::NaoExigencia);
});

it('Imovel::fromArray creates instance from array', function () {
    $dto = Imovel::fromArray(['inscImobFisc' => 'INSC456', 'cCIB' => '12345678901234']);

    expect($dto)->toBeInstanceOf(Imovel::class)
        ->and($dto->inscImobFisc)->toBe('INSC456')
        ->and($dto->cCIB)->toBe('12345678901234');
});

it('DFeNacional::fromArray creates instance from array', function () {
    $dto = DFeNacional::fromArray([
        'tipoChaveDFe' => '1',
        'chaveDFe' => '12345678901234567890123456789012345678901234',
        'xTipoChaveDFe' => 'Tipo Chave Desc',
    ]);

    expect($dto)->toBeInstanceOf(DFeNacional::class)
        ->and($dto->xTipoChaveDFe)->toBe('Tipo Chave Desc');
});

it('DocFiscalOutro::fromArray creates instance from array', function () {
    $dto = DocFiscalOutro::fromArray(['cMunDocFiscal' => '3501608', 'nDocFiscal' => '123', 'xDocFiscal' => 'Doc Fiscal']);
    expect($dto)->toBeInstanceOf(DocFiscalOutro::class);
});

it('DocOutro::fromArray creates instance from array', function () {
    $dto = DocOutro::fromArray(['nDoc' => '123', 'xDoc' => 'Outro Doc']);
    expect($dto)->toBeInstanceOf(DocOutro::class);
});

it('Fornec::fromArray preserves CNPJ', function () {
    $dto = Fornec::fromArray(['xNome' => 'Fornecedor', 'CNPJ' => '98765432000100']);

    expect($dto)->toBeInstanceOf(Fornec::class)
        ->and($dto->CNPJ)->toBe('98765432000100');
});

it('Fornec::fromArray preserves CPF', function () {
    $dto = Fornec::fromArray(['xNome' => 'F', 'CPF' => '12345678901']);
    expect($dto->CPF)->toBe('12345678901');
});

it('Fornec::fromArray preserves NIF', function () {
    $dto = Fornec::fromArray(['xNome' => 'F', 'NIF' => 'NIF_FORNEC']);
    expect($dto->NIF)->toBe('NIF_FORNEC');
});

it('Fornec::fromArray preserves cNaoNIF', function () {
    $dto = Fornec::fromArray(['xNome' => 'F', 'cNaoNIF' => '0']);
    expect($dto->cNaoNIF)->toBe(CNaoNIF::NaoInformado);
});

it('Fornec rejects when no identifier provided', function () {
    Fornec::fromArray(['xNome' => 'F']);
})->throws(InvalidDpsArgument::class);

it('Documentos::fromArray creates instance with dFeNacional', function () {
    $dto = Documentos::fromArray([
        'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
        'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
        'xTpReeRepRes' => 'Desc tipo',
        'dFeNacional' => ['tipoChaveDFe' => '1', 'chaveDFe' => '12345678901234567890123456789012345678901234'],
    ]);

    expect($dto)->toBeInstanceOf(Documentos::class)
        ->and($dto->xTpReeRepRes)->toBe('Desc tipo')
        ->and($dto->dFeNacional)->toBeInstanceOf(DFeNacional::class);
});

it('Documentos::fromArray creates instance with docFiscalOutro', function () {
    $dto = Documentos::fromArray([
        'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
        'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
        'docFiscalOutro' => ['cMunDocFiscal' => '3501608', 'nDocFiscal' => '1', 'xDocFiscal' => 'X'],
    ]);

    expect($dto->docFiscalOutro)->toBeInstanceOf(DocFiscalOutro::class);
});

it('Documentos::fromArray creates instance with docOutro', function () {
    $dto = Documentos::fromArray([
        'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
        'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
        'docOutro' => ['nDoc' => '123', 'xDoc' => 'Outro'],
    ]);

    expect($dto->docOutro)->toBeInstanceOf(DocOutro::class);
});

it('Documentos rejects when no doc type provided', function () {
    Documentos::fromArray([
        'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
        'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
    ]);
})->throws(InvalidDpsArgument::class);

it('GReeRepRes::fromArray creates instance from array', function () {
    $dto = GReeRepRes::fromArray([
        'documentos' => [[
            'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
            'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
            'dFeNacional' => ['tipoChaveDFe' => '1', 'chaveDFe' => '12345678901234567890123456789012345678901234'],
        ]],
    ]);

    expect($dto)->toBeInstanceOf(GReeRepRes::class)
        ->and($dto->documentos[0])->toBeInstanceOf(Documentos::class);
});

it('IBSCBS::fromArray creates instance from array', function () {
    $dto = IBSCBS::fromArray([
        'finNFSe' => '0', 'indFinal' => '0', 'cIndOp' => '001', 'indDest' => '0',
        'valores' => ['trib' => ['gIBSCBS' => ['CST' => '00', 'cClassTrib' => '001']]],
        'refNFSe' => ['CHAVE1', 'CHAVE2'],
    ]);

    expect($dto)->toBeInstanceOf(IBSCBS::class)
        ->and($dto->refNFSe)->toBe(['CHAVE1', 'CHAVE2']);
});

it('IBSCBS::fromArray creates instance without optional indFinal', function () {
    $dto = IBSCBS::fromArray([
        'finNFSe' => '0', 'cIndOp' => '001', 'indDest' => '0',
        'valores' => ['trib' => ['gIBSCBS' => ['CST' => '00', 'cClassTrib' => '001']]],
    ]);

    expect($dto)->toBeInstanceOf(IBSCBS::class)
        ->and($dto->indFinal)->toBeNull();
});
