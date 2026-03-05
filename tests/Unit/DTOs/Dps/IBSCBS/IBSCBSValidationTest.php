<?php

covers(
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoDest::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoIBSCBS::class,
    \Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoImovel::class,
);
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoDest;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoImovel;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoTributosSitClas;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoValoresIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocDFe;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocFornec;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\ListaDocReeRepRes;
use Pulsar\NfseNacional\Dps\DTO\Servico\EnderecoObra;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\FinNFSe;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\IndDest;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\IndFinal;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TipoChaveDFe;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TpReeRepRes;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('throws when InfoIBSCBS refNFSe is empty array', function () {
    expect(fn () => new InfoIBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Sim,
        cIndOp: '01',
        indDest: IndDest::Tomador,
        valores: new InfoValoresIBSCBS(
            trib: new InfoTributosIBSCBS(
                gIBSCBS: new InfoTributosSitClas(CST: '100', cClassTrib: '010101'),
            ),
        ),
        refNFSe: [],
    ))->toThrow(InvalidDpsArgument::class, 'ao menos um');
});

it('throws when InfoDest has no identification', function () {
    expect(fn () => new InfoDest(xNome: 'Dest'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when InfoDest has multiple identifications', function () {
    expect(fn () => new InfoDest(xNome: 'Dest', CNPJ: '12345678000195', CPF: '12345678901'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('creates InfoDest with CNPJ', function () {
    $dest = new InfoDest(xNome: 'Destinatário', CNPJ: '12345678000195');
    expect($dest->CNPJ)->toBe('12345678000195');
});

it('throws when InfoImovel has no choice', function () {
    expect(fn () => new InfoImovel)
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when InfoImovel has both choices', function () {
    expect(fn () => new InfoImovel(
        cCIB: '12345678',
        end: new EnderecoObra(xLgr: 'Rua', nro: '1', xBairro: 'Centro', CEP: '01001000'),
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('creates InfoImovel with cCIB', function () {
    $imovel = new InfoImovel(cCIB: '12345678');
    expect($imovel->cCIB)->toBe('12345678');
});

it('throws when ListaDocFornec has no identification', function () {
    expect(fn () => new ListaDocFornec(xNome: 'Fornec'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('creates ListaDocFornec with CPF', function () {
    $fornec = new ListaDocFornec(xNome: 'Fornecedor', CPF: '12345678901');
    expect($fornec->CPF)->toBe('12345678901');
});

it('throws when ListaDocReeRepRes has no document', function () {
    expect(fn () => new ListaDocReeRepRes(
        dtEmiDoc: '2026-01-01',
        dtCompDoc: '2026-01-01',
        tpReeRepRes: TpReeRepRes::Outros,
        vlrReeRepRes: '100.00',
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('creates ListaDocReeRepRes with dFeNacional', function () {
    $doc = new ListaDocReeRepRes(
        dtEmiDoc: '2026-01-01',
        dtCompDoc: '2026-01-01',
        tpReeRepRes: TpReeRepRes::RepasseImoveis,
        vlrReeRepRes: '100.00',
        dFeNacional: new ListaDocDFe(tipoChaveDFe: TipoChaveDFe::NFSe, chaveDFe: '12345678901234567890123456789012345678901234567890'),
    );

    expect($doc->dFeNacional)->toBeInstanceOf(ListaDocDFe::class);
});
