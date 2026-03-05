<?php

covers(
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosTribRegular::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosDif::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosSitClas::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosIBSCBS::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoValoresIBSCBS::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoDest::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoImovel::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocDFe::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocFiscalOutro::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocOutro::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocFornec::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocReeRepRes::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoReeRepRes::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoIBSCBS::class,
);

use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoDest;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoImovel;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoReeRepRes;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosDif;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosSitClas;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosTribRegular;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoValoresIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocDFe;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocFiscalOutro;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocFornec;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocOutro;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocReeRepRes;
use Pulsar\NfseNacional\Dps\Enums\Shared\CNaoNIF;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('InfoTributosTribRegular::fromArray creates instance from array', function () {
    $dto = InfoTributosTribRegular::fromArray(['CSTReg' => '00', 'cClassTribReg' => '001']);
    expect($dto)->toBeInstanceOf(InfoTributosTribRegular::class);
});

it('InfoTributosDif::fromArray creates instance from array', function () {
    $dto = InfoTributosDif::fromArray(['pDifUF' => '10.00', 'pDifMun' => '5.00', 'pDifCBS' => '3.00']);
    expect($dto)->toBeInstanceOf(InfoTributosDif::class);
});

it('InfoTributosSitClas::fromArray creates instance from array', function () {
    $dto = InfoTributosSitClas::fromArray(['CST' => '00', 'cClassTrib' => '001', 'cCredPres' => 'CRED123']);

    expect($dto)->toBeInstanceOf(InfoTributosSitClas::class)
        ->and($dto->cCredPres)->toBe('CRED123');
});

it('InfoTributosIBSCBS::fromArray creates instance from array', function () {
    $dto = InfoTributosIBSCBS::fromArray(['gIBSCBS' => ['CST' => '00', 'cClassTrib' => '001']]);
    expect($dto)->toBeInstanceOf(InfoTributosIBSCBS::class);
});

it('InfoValoresIBSCBS::fromArray creates instance from array', function () {
    $dto = InfoValoresIBSCBS::fromArray(['trib' => ['gIBSCBS' => ['CST' => '00', 'cClassTrib' => '001']]]);
    expect($dto)->toBeInstanceOf(InfoValoresIBSCBS::class);
});

it('InfoDest::fromArray preserves CNPJ and optional fields', function () {
    $dto = InfoDest::fromArray([
        'xNome' => 'Destinatario', 'CNPJ' => '12345678000195',
        'fone' => '11777777777', 'email' => 'dest@test.com',
    ]);

    expect($dto)->toBeInstanceOf(InfoDest::class)
        ->and($dto->CNPJ)->toBe('12345678000195')
        ->and($dto->fone)->toBe('11777777777')
        ->and($dto->email)->toBe('dest@test.com');
});

it('InfoDest::fromArray preserves CPF', function () {
    $dto = InfoDest::fromArray(['xNome' => 'D', 'CPF' => '12345678901']);
    expect($dto->CPF)->toBe('12345678901');
});

it('InfoDest::fromArray preserves NIF', function () {
    $dto = InfoDest::fromArray(['xNome' => 'D', 'NIF' => 'NIF789']);
    expect($dto->NIF)->toBe('NIF789');
});

it('InfoDest::fromArray preserves cNaoNIF', function () {
    $dto = InfoDest::fromArray(['xNome' => 'D', 'cNaoNIF' => '2']);
    expect($dto->cNaoNIF)->toBe(CNaoNIF::NaoExigencia);
});

it('InfoImovel::fromArray creates instance from array', function () {
    $dto = InfoImovel::fromArray(['inscImobFisc' => 'INSC456', 'cCIB' => '12345678901234']);

    expect($dto)->toBeInstanceOf(InfoImovel::class)
        ->and($dto->inscImobFisc)->toBe('INSC456')
        ->and($dto->cCIB)->toBe('12345678901234');
});

it('ListaDocDFe::fromArray creates instance from array', function () {
    $dto = ListaDocDFe::fromArray([
        'tipoChaveDFe' => '1',
        'chaveDFe' => '12345678901234567890123456789012345678901234',
        'xTipoChaveDFe' => 'Tipo Chave Desc',
    ]);

    expect($dto)->toBeInstanceOf(ListaDocDFe::class)
        ->and($dto->xTipoChaveDFe)->toBe('Tipo Chave Desc');
});

it('ListaDocFiscalOutro::fromArray creates instance from array', function () {
    $dto = ListaDocFiscalOutro::fromArray(['cMunDocFiscal' => '3501608', 'nDocFiscal' => '123', 'xDocFiscal' => 'Doc Fiscal']);
    expect($dto)->toBeInstanceOf(ListaDocFiscalOutro::class);
});

it('ListaDocOutro::fromArray creates instance from array', function () {
    $dto = ListaDocOutro::fromArray(['nDoc' => '123', 'xDoc' => 'Outro Doc']);
    expect($dto)->toBeInstanceOf(ListaDocOutro::class);
});

it('ListaDocFornec::fromArray preserves CNPJ', function () {
    $dto = ListaDocFornec::fromArray(['xNome' => 'Fornecedor', 'CNPJ' => '98765432000100']);

    expect($dto)->toBeInstanceOf(ListaDocFornec::class)
        ->and($dto->CNPJ)->toBe('98765432000100');
});

it('ListaDocFornec::fromArray preserves CPF', function () {
    $dto = ListaDocFornec::fromArray(['xNome' => 'F', 'CPF' => '12345678901']);
    expect($dto->CPF)->toBe('12345678901');
});

it('ListaDocFornec::fromArray preserves NIF', function () {
    $dto = ListaDocFornec::fromArray(['xNome' => 'F', 'NIF' => 'NIF_FORNEC']);
    expect($dto->NIF)->toBe('NIF_FORNEC');
});

it('ListaDocFornec::fromArray preserves cNaoNIF', function () {
    $dto = ListaDocFornec::fromArray(['xNome' => 'F', 'cNaoNIF' => '0']);
    expect($dto->cNaoNIF)->toBe(CNaoNIF::NaoInformado);
});

it('ListaDocFornec rejects when no identifier provided', function () {
    ListaDocFornec::fromArray(['xNome' => 'F']);
})->throws(InvalidDpsArgument::class);

it('ListaDocReeRepRes::fromArray creates instance with dFeNacional', function () {
    $dto = ListaDocReeRepRes::fromArray([
        'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
        'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
        'xTpReeRepRes' => 'Desc tipo',
        'dFeNacional' => ['tipoChaveDFe' => '1', 'chaveDFe' => '12345678901234567890123456789012345678901234'],
    ]);

    expect($dto)->toBeInstanceOf(ListaDocReeRepRes::class)
        ->and($dto->xTpReeRepRes)->toBe('Desc tipo')
        ->and($dto->dFeNacional)->toBeInstanceOf(ListaDocDFe::class);
});

it('ListaDocReeRepRes::fromArray creates instance with docFiscalOutro', function () {
    $dto = ListaDocReeRepRes::fromArray([
        'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
        'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
        'docFiscalOutro' => ['cMunDocFiscal' => '3501608', 'nDocFiscal' => '1', 'xDocFiscal' => 'X'],
    ]);

    expect($dto->docFiscalOutro)->toBeInstanceOf(ListaDocFiscalOutro::class);
});

it('ListaDocReeRepRes::fromArray creates instance with docOutro', function () {
    $dto = ListaDocReeRepRes::fromArray([
        'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
        'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
        'docOutro' => ['nDoc' => '123', 'xDoc' => 'Outro'],
    ]);

    expect($dto->docOutro)->toBeInstanceOf(ListaDocOutro::class);
});

it('ListaDocReeRepRes rejects when no doc type provided', function () {
    ListaDocReeRepRes::fromArray([
        'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
        'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
    ]);
})->throws(InvalidDpsArgument::class);

it('InfoReeRepRes::fromArray creates instance from array', function () {
    $dto = InfoReeRepRes::fromArray([
        'documentos' => [[
            'dtEmiDoc' => '2026-01-01', 'dtCompDoc' => '2026-01-01',
            'tpReeRepRes' => '01', 'vlrReeRepRes' => '100.00',
            'dFeNacional' => ['tipoChaveDFe' => '1', 'chaveDFe' => '12345678901234567890123456789012345678901234'],
        ]],
    ]);

    expect($dto)->toBeInstanceOf(InfoReeRepRes::class)
        ->and($dto->documentos[0])->toBeInstanceOf(ListaDocReeRepRes::class);
});

it('InfoIBSCBS::fromArray creates instance from array', function () {
    $dto = InfoIBSCBS::fromArray([
        'finNFSe' => '0', 'indFinal' => '0', 'cIndOp' => '001', 'indDest' => '0',
        'valores' => ['trib' => ['gIBSCBS' => ['CST' => '00', 'cClassTrib' => '001']]],
        'refNFSe' => ['CHAVE1', 'CHAVE2'],
    ]);

    expect($dto)->toBeInstanceOf(InfoIBSCBS::class)
        ->and($dto->refNFSe)->toBe(['CHAVE1', 'CHAVE2']);
});

it('InfoIBSCBS::fromArray creates instance without optional indFinal', function () {
    $dto = InfoIBSCBS::fromArray([
        'finNFSe' => '0', 'cIndOp' => '001', 'indDest' => '0',
        'valores' => ['trib' => ['gIBSCBS' => ['CST' => '00', 'cClassTrib' => '001']]],
    ]);

    expect($dto)->toBeInstanceOf(InfoIBSCBS::class)
        ->and($dto->indFinal)->toBeNull();
});
