<?php

use OwnerPro\Nfsen\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use OwnerPro\Nfsen\Dps\DTO\Valores\BM;
use OwnerPro\Nfsen\Dps\DTO\Valores\DocDedRed;
use OwnerPro\Nfsen\Dps\DTO\Valores\ExigSusp;
use OwnerPro\Nfsen\Dps\DTO\Valores\NFNFS;
use OwnerPro\Nfsen\Dps\DTO\Valores\NFSeMun;
use OwnerPro\Nfsen\Dps\DTO\Valores\Piscofins;
use OwnerPro\Nfsen\Dps\DTO\Valores\PTotTrib;
use OwnerPro\Nfsen\Dps\DTO\Valores\Trib;
use OwnerPro\Nfsen\Dps\DTO\Valores\TribFed;
use OwnerPro\Nfsen\Dps\DTO\Valores\TribMun;
use OwnerPro\Nfsen\Dps\DTO\Valores\Valores;
use OwnerPro\Nfsen\Dps\DTO\Valores\VDedRed;
use OwnerPro\Nfsen\Dps\DTO\Valores\VDescCondIncond;
use OwnerPro\Nfsen\Dps\DTO\Valores\VServPrest;
use OwnerPro\Nfsen\Dps\DTO\Valores\VTotTrib;
use OwnerPro\Nfsen\Dps\Enums\Valores\CST;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetPisCofins;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

covers(ValidatesExclusiveChoice::class, VServPrest::class, TribMun::class, TribFed::class, Piscofins::class, ExigSusp::class, BM::class, VTotTrib::class, PTotTrib::class, Trib::class, VDescCondIncond::class, NFSeMun::class, NFNFS::class, DocDedRed::class, VDedRed::class, Valores::class);

it('VServPrest::fromArray creates instance from array', function () {
    $dto = VServPrest::fromArray(['vServ' => '100.00']);
    expect($dto)->toBeInstanceOf(VServPrest::class);
});

it('TribMun::fromArray creates instance from array', function () {
    $dto = TribMun::fromArray([
        'tribISSQN' => '1',
        'tpRetISSQN' => '1',
        'cPaisResult' => '01058',
        'pAliq' => '5.00',
    ]);

    expect($dto)->toBeInstanceOf(TribMun::class)
        ->and($dto->cPaisResult)->toBe('01058')
        ->and($dto->pAliq)->toBe('5.00');
});

it('TribFed::fromArray creates instance from array', function () {
    $dto = TribFed::fromArray([
        'piscofins' => ['CST' => '00'],
        'vRetCP' => '10.00',
        'vRetIRRF' => '5.00',
        'vRetCSLL' => '3.00',
    ]);

    expect($dto)->toBeInstanceOf(TribFed::class)
        ->and($dto->piscofins)->toBeInstanceOf(Piscofins::class)
        ->and($dto->vRetCP)->toBe('10.00')
        ->and($dto->vRetIRRF)->toBe('5.00')
        ->and($dto->vRetCSLL)->toBe('3.00');
});

it('Piscofins::fromArray creates instance from array', function () {
    $dto = Piscofins::fromArray([
        'CST' => '00',
        'vBCPisCofins' => '1000.00',
        'pAliqPis' => '1.65',
        'pAliqCofins' => '7.60',
        'vPis' => '16.50',
        'vCofins' => '76.00',
        'tpRetPisCofins' => '1',
    ]);

    expect($dto)->toBeInstanceOf(Piscofins::class)
        ->and($dto->vBCPisCofins)->toBe('1000.00')
        ->and($dto->pAliqPis)->toBe('1.65')
        ->and($dto->pAliqCofins)->toBe('7.60')
        ->and($dto->vPis)->toBe('16.50')
        ->and($dto->vCofins)->toBe('76.00')
        ->and($dto->tpRetPisCofins)->toBe(TpRetPisCofins::PisCofinsRetidos);
});

it('ExigSusp::fromArray creates instance from array', function () {
    $dto = ExigSusp::fromArray(['tpSusp' => '1', 'nProcesso' => '12345']);
    expect($dto)->toBeInstanceOf(ExigSusp::class);
});

it('ExigSusp::fromArray throws ValueError for invalid tpSusp', function () {
    ExigSusp::fromArray(['tpSusp' => 'XYZ', 'nProcesso' => '12345']);
})->throws(ValueError::class);

it('BM::fromArray creates instance from array', function () {
    $dto = BM::fromArray(['nBM' => 'BM001']);
    expect($dto)->toBeInstanceOf(BM::class);
});

it('BM::fromArray allows one optional field', function () {
    $dto = BM::fromArray(['nBM' => 'BM001', 'vRedBCBM' => '100.00']);
    expect($dto->vRedBCBM)->toBe('100.00');
});

it('BM rejects when both vRedBCBM and pRedBCBM provided', function () {
    BM::fromArray(['nBM' => 'BM001', 'vRedBCBM' => '100.00', 'pRedBCBM' => '10.00']);
})->throws(InvalidDpsArgument::class);

it('VTotTrib::fromArray creates instance from array', function () {
    $dto = VTotTrib::fromArray(['vTotTribFed' => '10.00', 'vTotTribEst' => '5.00', 'vTotTribMun' => '3.00']);
    expect($dto)->toBeInstanceOf(VTotTrib::class);
});

it('PTotTrib::fromArray creates instance from array', function () {
    $dto = PTotTrib::fromArray(['pTotTribFed' => '10.00', 'pTotTribEst' => '5.00', 'pTotTribMun' => '3.00']);
    expect($dto)->toBeInstanceOf(PTotTrib::class);
});

it('Trib::fromArray creates instance from array', function () {
    $dto = Trib::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'indTotTrib' => '0',
    ]);

    expect($dto)->toBeInstanceOf(Trib::class)
        ->and($dto->indTotTrib)->toBe('0');
});

it('Trib::fromArray preserves pTotTribSN', function () {
    $dto = Trib::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'pTotTribSN' => '15.50',
    ]);

    expect($dto->pTotTribSN)->toBe('15.50');
});

it('Trib::fromArray creates with vTotTrib', function () {
    $dto = Trib::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'vTotTrib' => ['vTotTribFed' => '10.00', 'vTotTribEst' => '5.00', 'vTotTribMun' => '3.00'],
    ]);

    expect($dto->vTotTrib)->toBeInstanceOf(VTotTrib::class);
});

it('Trib::fromArray creates with pTotTrib', function () {
    $dto = Trib::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'pTotTrib' => ['pTotTribFed' => '10.00', 'pTotTribEst' => '5.00', 'pTotTribMun' => '3.00'],
    ]);

    expect($dto->pTotTrib)->toBeInstanceOf(PTotTrib::class);
});

it('Trib rejects when no totTrib variant provided', function () {
    Trib::fromArray(['tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1']]);
})->throws(InvalidDpsArgument::class);

it('Trib rejects when multiple totTrib variants provided', function () {
    Trib::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'indTotTrib' => '0',
        'pTotTribSN' => '15.50',
    ]);
})->throws(InvalidDpsArgument::class);

it('VDescCondIncond::fromArray creates instance from array', function () {
    $dto = VDescCondIncond::fromArray(['vDescIncond' => '5.00']);
    expect($dto)->toBeInstanceOf(VDescCondIncond::class);
});

it('VDescCondIncond rejects when all fields are null', function () {
    new VDescCondIncond;
})->throws(InvalidDpsArgument::class, 'vDescCondIncond deve conter ao menos um campo preenchido.');

it('VDescCondIncond accepts with only vDescIncond', function () {
    $dto = new VDescCondIncond(vDescIncond: '5.00');
    expect($dto->vDescIncond)->toBe('5.00')
        ->and($dto->vDescCond)->toBeNull();
});

it('VDescCondIncond accepts with only vDescCond', function () {
    $dto = new VDescCondIncond(vDescCond: '3.00');
    expect($dto->vDescCond)->toBe('3.00')
        ->and($dto->vDescIncond)->toBeNull();
});

it('TribFed rejects when all fields are null', function () {
    new TribFed;
})->throws(InvalidDpsArgument::class, 'tribFed deve conter ao menos um campo preenchido.');

it('TribFed accepts with only piscofins', function () {
    $dto = new TribFed(piscofins: new Piscofins(CST: CST::Nenhum));
    expect($dto->piscofins)->toBeInstanceOf(Piscofins::class);
});

it('TribFed accepts with only vRetCP', function () {
    $dto = new TribFed(vRetCP: '10.00');
    expect($dto->vRetCP)->toBe('10.00');
});

it('TribFed accepts with only vRetIRRF', function () {
    $dto = new TribFed(vRetIRRF: '5.00');
    expect($dto->vRetIRRF)->toBe('5.00');
});

it('TribFed accepts with only vRetCSLL', function () {
    $dto = new TribFed(vRetCSLL: '3.00');
    expect($dto->vRetCSLL)->toBe('3.00');
});

it('NFSeMun::fromArray creates instance from array', function () {
    $dto = NFSeMun::fromArray(['cMunNFSeMun' => '3501608', 'nNFSeMun' => '123', 'cVerifNFSeMun' => 'ABC']);
    expect($dto)->toBeInstanceOf(NFSeMun::class);
});

it('NFNFS::fromArray creates instance from array', function () {
    $dto = NFNFS::fromArray(['nNFS' => '123', 'modNFS' => '55', 'serieNFS' => '1']);
    expect($dto)->toBeInstanceOf(NFNFS::class);
});

it('DocDedRed::fromArray preserves chNFSe and xDescOutDed', function () {
    $dto = DocDedRed::fromArray([
        'tpDedRed' => '1', 'dtEmiDoc' => '2026-01-01',
        'vDedutivelRedutivel' => '100.00', 'vDeducaoReducao' => '50.00',
        'chNFSe' => '12345678901234567890123456789012345678901234567890',
        'xDescOutDed' => 'Desc deducao',
    ]);

    expect($dto)->toBeInstanceOf(DocDedRed::class)
        ->and($dto->chNFSe)->toBe('12345678901234567890123456789012345678901234567890')
        ->and($dto->xDescOutDed)->toBe('Desc deducao');
});

it('DocDedRed::fromArray preserves chNFe', function () {
    $dto = DocDedRed::fromArray([
        'tpDedRed' => '1', 'dtEmiDoc' => '2026-01-01',
        'vDedutivelRedutivel' => '100.00', 'vDeducaoReducao' => '50.00',
        'chNFe' => 'NFE123',
    ]);

    expect($dto->chNFe)->toBe('NFE123');
});

it('DocDedRed::fromArray preserves nDocFisc', function () {
    $dto = DocDedRed::fromArray([
        'tpDedRed' => '1', 'dtEmiDoc' => '2026-01-01',
        'vDedutivelRedutivel' => '100.00', 'vDeducaoReducao' => '50.00',
        'nDocFisc' => 'DOC001',
    ]);

    expect($dto->nDocFisc)->toBe('DOC001');
});

it('DocDedRed::fromArray preserves nDoc', function () {
    $dto = DocDedRed::fromArray([
        'tpDedRed' => '1', 'dtEmiDoc' => '2026-01-01',
        'vDedutivelRedutivel' => '100.00', 'vDeducaoReducao' => '50.00',
        'nDoc' => 'NDOC002',
    ]);

    expect($dto->nDoc)->toBe('NDOC002');
});

it('VDedRed::fromArray creates instance with documentos', function () {
    $dto = VDedRed::fromArray([
        'documentos' => [[
            'tpDedRed' => '1', 'dtEmiDoc' => '2026-01-01',
            'vDedutivelRedutivel' => '100.00', 'vDeducaoReducao' => '50.00',
            'chNFSe' => '12345678901234567890123456789012345678901234567890',
        ]],
    ]);

    expect($dto)->toBeInstanceOf(VDedRed::class)
        ->and($dto->documentos)->toHaveCount(1)
        ->and($dto->documentos[0])->toBeInstanceOf(DocDedRed::class);
});

it('VDedRed::fromArray preserves pDR', function () {
    $dto = VDedRed::fromArray(['pDR' => '10.00']);
    expect($dto->pDR)->toBe('10.00');
});

it('VDedRed::fromArray preserves vDR', function () {
    $dto = VDedRed::fromArray(['vDR' => '50.00']);
    expect($dto->vDR)->toBe('50.00');
});

it('Valores::fromArray creates instance from array', function () {
    $dto = Valores::fromArray([
        'vServPrest' => ['vServ' => '100.00'],
        'trib' => [
            'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
            'indTotTrib' => '0',
        ],
    ]);

    expect($dto)->toBeInstanceOf(Valores::class);
});
