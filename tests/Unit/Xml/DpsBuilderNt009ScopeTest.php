<?php

use OwnerPro\Nfsen\Dps\DTO\Valores\VDedRed;
use OwnerPro\Nfsen\Enums\Leiaute;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;
use OwnerPro\Nfsen\Xml\Builders\ValoresBuilder;
use OwnerPro\Nfsen\Xml\DpsBuilder;
use OwnerPro\Nfsen\Xml\Nt009Scope;

covers(DpsBuilder::class, ValoresBuilder::class, Nt009Scope::class);

it('translates pDR to vAjusteBC/pAjusteBCISSQN', function () {
    $xml = nt009Xml(makeDpsNucleo());

    expect(childNames($xml, '/n:DPS/n:infDPS/n:valores'))->toBe(['vServPrest', 'vDescCondIncond', 'vAjusteBC', 'trib']);
    expect(childNames($xml, '/n:DPS/n:infDPS/n:valores/n:vAjusteBC'))->toBe(['pAjusteBCISSQN']);
    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:pAjusteBCISSQN'))->toBe('10.00');
});

it('translates vDR to vAjusteBC/vAjusteBCISSQN', function () {
    $xml = nt009Xml(makeDpsNucleo(vDedRed: new VDedRed(vDR: '150.00')));

    expect(childNames($xml, '/n:DPS/n:infDPS/n:valores/n:vAjusteBC'))->toBe(['vAjusteBCISSQN']);
    expect(xpathValue($xml, '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:vAjusteBCISSQN'))->toBe('150.00');
});

it('refuses a group the NT 009 layout restructured', function (Closure $data, string $path) {
    $builder = new DpsBuilder(makeXsdValidator(Leiaute::Nt009), Leiaute::Nt009);
    $message = sprintf('O grupo %s ainda não é suportado no leiaute da NT 009.', $path);

    expect(fn () => $builder->build($data()))->toThrow(InvalidDpsArgument::class, $message);
    expect(fn () => $builder->buildAndValidate($data()))->toThrow(InvalidDpsArgument::class, $message);
})->with([
    'comExt' => [fn () => makeDpsNucleo(comExt: makeComExt()), 'serv/comExt'],
    'imovel' => [fn () => makeDpsNucleo(imovel: makeImovel()), 'IBSCBS/imovel'],
    'gReeRepRes' => [fn () => makeDpsNucleo(gReeRepRes: makeGReeRepRes()), 'IBSCBS/valores/gReeRepRes'],
    'documentos' => [fn () => makeDpsNucleo(vDedRed: makeVDedRedDocumentos()), 'valores/vDedRed/documentos'],
]);

it('accepts in the production layout the groups the NT 009 layout refuses', function () {
    $builder = new DpsBuilder(makeXsdValidator());

    expect(fn () => $builder->buildAndValidate(makeDpsCompleta()))->not->toThrow(Throwable::class);
});
