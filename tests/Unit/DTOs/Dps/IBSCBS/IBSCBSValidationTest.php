<?php

use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Dest;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\DFeNacional;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Documentos;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Fornec;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\GIBSCBS;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\IBSCBS;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Imovel;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Trib;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Valores;
use OwnerPro\Nfsen\Dps\DTO\Serv\EndObra;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\FinNFSe;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\IndDest;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\IndFinal;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\TipoChaveDFe;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\TpReeRepRes;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

covers(Dest::class, IBSCBS::class, Imovel::class);

it('throws when IBSCBS refNFSe is empty array', function () {
    expect(fn () => new IBSCBS(
        finNFSe: FinNFSe::Regular,
        indFinal: IndFinal::Sim,
        cIndOp: '01',
        indDest: IndDest::Tomador,
        valores: new Valores(
            trib: new Trib(
                gIBSCBS: new GIBSCBS(CST: '100', cClassTrib: '010101'),
            ),
        ),
        refNFSe: [],
    ))->toThrow(InvalidDpsArgument::class, 'ao menos um');
});

it('throws when Dest has no identification', function () {
    expect(fn () => new Dest(xNome: 'Dest'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when Dest has multiple identifications', function () {
    expect(fn () => new Dest(xNome: 'Dest', CNPJ: '12345678000195', CPF: '12345678901'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('creates Dest with CNPJ', function () {
    $dest = new Dest(xNome: 'Destinatário', CNPJ: '12345678000195');
    expect($dest->CNPJ)->toBe('12345678000195');
});

it('throws when Imovel has no choice', function () {
    expect(fn () => new Imovel)
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('throws when Imovel has both choices', function () {
    expect(fn () => new Imovel(
        cCIB: '12345678',
        end: new EndObra(xLgr: 'Rua', nro: '1', xBairro: 'Centro', CEP: '01001000'),
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('creates Imovel with cCIB', function () {
    $imovel = new Imovel(cCIB: '12345678');
    expect($imovel->cCIB)->toBe('12345678');
});

it('throws when Fornec has no identification', function () {
    expect(fn () => new Fornec(xNome: 'Fornec'))
        ->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('creates Fornec with CPF', function () {
    $fornec = new Fornec(xNome: 'Fornecedor', CPF: '12345678901');
    expect($fornec->CPF)->toBe('12345678901');
});

it('throws when Documentos has no document', function () {
    expect(fn () => new Documentos(
        dtEmiDoc: '2026-01-01',
        dtCompDoc: '2026-01-01',
        tpReeRepRes: TpReeRepRes::Outros,
        vlrReeRepRes: '100.00',
    ))->toThrow(InvalidDpsArgument::class, 'deve ser informado');
});

it('creates Documentos with dFeNacional', function () {
    $doc = new Documentos(
        dtEmiDoc: '2026-01-01',
        dtCompDoc: '2026-01-01',
        tpReeRepRes: TpReeRepRes::RepasseImoveis,
        vlrReeRepRes: '100.00',
        dFeNacional: new DFeNacional(tipoChaveDFe: TipoChaveDFe::NFSe, chaveDFe: '12345678901234567890123456789012345678901234567890'),
    );

    expect($doc->dFeNacional)->toBeInstanceOf(DFeNacional::class);
});
