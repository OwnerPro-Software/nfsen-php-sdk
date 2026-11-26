<?php

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\GIBSCBS;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\GTribRegular;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\IBSCBS;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Trib;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Valores;
use OwnerPro\Nfsen\Dps\DTO\Toma\Toma;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\FinNFSe;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\IndDest;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\CMotivoEmisTI;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\TpEmit;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;
use OwnerPro\Nfsen\Xml\Builders\DestinatarioBuilder;
use OwnerPro\Nfsen\Xml\Builders\IBSCBSBuilder;
use OwnerPro\Nfsen\Xml\Builders\ServicoBuilder;
use OwnerPro\Nfsen\Xml\DpsBuilder;
use OwnerPro\Nfsen\Xml\Nt009Scope;

covers(DpsBuilder::class, ServicoBuilder::class, IBSCBSBuilder::class, DestinatarioBuilder::class, Nt009Scope::class);

it('declares finNFSe and indDest in infDPS when the DPS has no IBSCBS group', function () {
    $xml = nt009Xml(new DpsData(makeInfDps(), makePrestadorCnpj(), makeServicoMinimo(), makeValoresMinimo()));

    expect(childNames($xml, '/n:DPS/n:infDPS'))->toBe(
        ['tpAmb', 'dhEmi', 'verAplic', 'serie', 'nDPS', 'dCompet', 'tpEmit', 'finNFSe', 'cLocEmi', 'prest', 'indDest', 'serv', 'valores'],
    );
    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:finNFSe'))->toBe('0');
    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:indDest'))->toBe('0');
});

it('writes xDescServ as the last child of cServ', function () {
    expect(childNames(nt009Xml(makeDpsNucleo()), '/n:DPS/n:infDPS/n:serv/n:cServ'))
        ->toBe(['cTribNac', 'cTribMun', 'cNBS', 'cIntContrib', 'xDescServ']);
});

it('moves finNFSe, indDest and dest from IBSCBS to infDPS', function () {
    $xml = nt009Xml(makeDpsNucleo());

    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:finNFSe'))->toBe('0');
    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:indDest'))->toBe('1');
    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:dest/n:xNome'))->toBe('Destinatária S.A.');
    expect(nameAfter($xml, '/n:DPS/n:infDPS', 'toma'))->toBe('indDest');
    expect(nameAfter($xml, '/n:DPS/n:infDPS', 'indDest'))->toBe('dest');
    expect(nameAfter($xml, '/n:DPS/n:infDPS', 'dest'))->toBe('interm');
    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS'))->toBe(['indFinal', 'cIndOp', 'valores']);
});

it('writes CST and cClassTrib before gIBSCBS', function () {
    $xml = nt009Xml(makeDpsNucleo());

    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib'))->toBe(['CST', 'cClassTrib', 'gIBSCBS']);
    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:CST'))->toBe('000');
    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:cClassTrib'))->toBe('000001');
    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS'))->toBe(['gTribRegular']);
});

it('emits an empty gIBSCBS when only CST and cClassTrib were informed', function () {
    $xml = nt009Xml(new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
        IBSCBS: new IBSCBS(
            finNFSe: FinNFSe::Regular,
            cIndOp: '010101',
            indDest: IndDest::Tomador,
            valores: new Valores(trib: new Trib(gIBSCBS: new GIBSCBS(CST: '000', cClassTrib: '000001'))),
        ),
    ));

    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib'))->toBe(['CST', 'cClassTrib', 'gIBSCBS']);
    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS'))->toBe([]);
    expect(childNames($xml, '/n:DPS/n:infDPS'))->not->toContain('dest');
});

it('places finNFSe between cMotivoEmisTI and chNFSeRej when the taker emits', function () {
    $xml = nt009Xml(new DpsData(
        infDPS: makeInfDps(tpEmit: TpEmit::Tomador, cMotivoEmisTI: CMotivoEmisTI::RejeicaoNfsePrestador, chNFSeRej: makeChaveAcesso()),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
        toma: new Toma(xNome: 'Tomador Ltda', CNPJ: '11222333000181'),
    ));

    expect(nameAfter($xml, '/n:DPS/n:infDPS', 'cMotivoEmisTI'))->toBe('finNFSe');
    expect(nameAfter($xml, '/n:DPS/n:infDPS', 'finNFSe'))->toBe('chNFSeRej');
});

it('writes indDest right after prest when there is no taker', function () {
    $xml = nt009Xml(new DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
        interm: new Toma(xNome: 'Intermediária Ltda', CNPJ: '99888777000166', path: 'infDPS/interm'),
    ));

    expect(nameAfter($xml, '/n:DPS/n:infDPS', 'prest'))->toBe('indDest');
    expect(nameAfter($xml, '/n:DPS/n:infDPS', 'indDest'))->toBe('interm');
});

it('omits cIndOp and gIBSCBS when the DPS declares no IBS/CBS taxation', function () {
    $xml = nt009Xml(makeDpsSemTributacaoIbsCbs());

    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS'))->toBe(['valores']);
    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib'))->toBe(['CST', 'cClassTrib']);
    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:CST'))->toBe('410');
});

it('keeps a gIBSCBS that has content even when cIndOp was not informed', function () {
    $xml = nt009Xml(makeDpsSemTributacaoIbsCbs(new GTribRegular(CSTReg: '000', cClassTribReg: '000001')));

    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS'))->toBe(['valores']);
    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib'))->toBe(['CST', 'cClassTrib', 'gIBSCBS']);
    expect(childNames($xml, '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS'))->toBe(['gTribRegular']);
});

it('refuses a DPS without cIndOp in the production layout, which requires it', function () {
    $builder = new DpsBuilder(makeXsdValidator());
    $message = 'IBSCBS/cIndOp é obrigatório no leiaute v1.01.';

    expect(fn () => $builder->build(makeDpsSemTributacaoIbsCbs()))->toThrow(InvalidDpsArgument::class, $message);
    expect(fn () => $builder->buildAndValidate(makeDpsSemTributacaoIbsCbs()))->toThrow(InvalidDpsArgument::class, $message);
});
