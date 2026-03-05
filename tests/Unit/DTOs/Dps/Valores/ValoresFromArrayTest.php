<?php

covers(
    \Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoFederal::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\PisCofins::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\ExigibilidadeSuspensa::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\BeneficioMunicipal::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\TotTribValor::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\TotTribPercentual::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\DescontoCondIncond::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\DocOutNFSe::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\DocNFNFS::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\DocDedRed::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\InfoDedRed::class,
    \Pulsar\NfseNacional\Dps\DTO\Valores\Valores::class,
);

use Pulsar\NfseNacional\Dps\DTO\Valores\BeneficioMunicipal;
use Pulsar\NfseNacional\Dps\DTO\Valores\DescontoCondIncond;
use Pulsar\NfseNacional\Dps\DTO\Valores\DocDedRed;
use Pulsar\NfseNacional\Dps\DTO\Valores\DocNFNFS;
use Pulsar\NfseNacional\Dps\DTO\Valores\DocOutNFSe;
use Pulsar\NfseNacional\Dps\DTO\Valores\ExigibilidadeSuspensa;
use Pulsar\NfseNacional\Dps\DTO\Valores\InfoDedRed;
use Pulsar\NfseNacional\Dps\DTO\Valores\PisCofins;
use Pulsar\NfseNacional\Dps\DTO\Valores\TotTribPercentual;
use Pulsar\NfseNacional\Dps\DTO\Valores\TotTribValor;
use Pulsar\NfseNacional\Dps\DTO\Valores\Tributacao;
use Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoFederal;
use Pulsar\NfseNacional\Dps\DTO\Valores\TributacaoMunicipal;
use Pulsar\NfseNacional\Dps\DTO\Valores\Valores;
use Pulsar\NfseNacional\Dps\DTO\Valores\ValorServicoPrestado;
use Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetPisCofins;
use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

it('ValorServicoPrestado::fromArray creates instance from array', function () {
    $dto = ValorServicoPrestado::fromArray(['vServ' => '100.00']);
    expect($dto)->toBeInstanceOf(ValorServicoPrestado::class);
});

it('TributacaoMunicipal::fromArray creates instance from array', function () {
    $dto = TributacaoMunicipal::fromArray([
        'tribISSQN' => '1',
        'tpRetISSQN' => '1',
        'cPaisResult' => '01058',
        'pAliq' => '5.00',
    ]);

    expect($dto)->toBeInstanceOf(TributacaoMunicipal::class)
        ->and($dto->cPaisResult)->toBe('01058')
        ->and($dto->pAliq)->toBe('5.00');
});

it('TributacaoFederal::fromArray creates instance from array', function () {
    $dto = TributacaoFederal::fromArray([
        'piscofins' => ['CST' => '00'],
        'vRetCP' => '10.00',
        'vRetIRRF' => '5.00',
        'vRetCSLL' => '3.00',
    ]);

    expect($dto)->toBeInstanceOf(TributacaoFederal::class)
        ->and($dto->piscofins)->toBeInstanceOf(PisCofins::class)
        ->and($dto->vRetCP)->toBe('10.00')
        ->and($dto->vRetIRRF)->toBe('5.00')
        ->and($dto->vRetCSLL)->toBe('3.00');
});

it('PisCofins::fromArray creates instance from array', function () {
    $dto = PisCofins::fromArray([
        'CST' => '00',
        'vBCPisCofins' => '1000.00',
        'pAliqPis' => '1.65',
        'pAliqCofins' => '7.60',
        'vPis' => '16.50',
        'vCofins' => '76.00',
        'tpRetPisCofins' => '1',
    ]);

    expect($dto)->toBeInstanceOf(PisCofins::class)
        ->and($dto->vBCPisCofins)->toBe('1000.00')
        ->and($dto->pAliqPis)->toBe('1.65')
        ->and($dto->pAliqCofins)->toBe('7.60')
        ->and($dto->vPis)->toBe('16.50')
        ->and($dto->vCofins)->toBe('76.00')
        ->and($dto->tpRetPisCofins)->toBe(TipoRetPisCofins::PisCofinsRetidos);
});

it('ExigibilidadeSuspensa::fromArray creates instance from array', function () {
    $dto = ExigibilidadeSuspensa::fromArray(['tpSusp' => '1', 'nProcesso' => '12345']);
    expect($dto)->toBeInstanceOf(ExigibilidadeSuspensa::class);
});

it('ExigibilidadeSuspensa::fromArray throws ValueError for invalid tpSusp', function () {
    ExigibilidadeSuspensa::fromArray(['tpSusp' => 'XYZ', 'nProcesso' => '12345']);
})->throws(ValueError::class);

it('BeneficioMunicipal::fromArray creates instance from array', function () {
    $dto = BeneficioMunicipal::fromArray(['nBM' => 'BM001']);
    expect($dto)->toBeInstanceOf(BeneficioMunicipal::class);
});

it('BeneficioMunicipal::fromArray allows one optional field', function () {
    $dto = BeneficioMunicipal::fromArray(['nBM' => 'BM001', 'vRedBCBM' => '100.00']);
    expect($dto->vRedBCBM)->toBe('100.00');
});

it('BeneficioMunicipal rejects when both vRedBCBM and pRedBCBM provided', function () {
    BeneficioMunicipal::fromArray(['nBM' => 'BM001', 'vRedBCBM' => '100.00', 'pRedBCBM' => '10.00']);
})->throws(InvalidDpsArgument::class);

it('TotTribValor::fromArray creates instance from array', function () {
    $dto = TotTribValor::fromArray(['vTotTribFed' => '10.00', 'vTotTribEst' => '5.00', 'vTotTribMun' => '3.00']);
    expect($dto)->toBeInstanceOf(TotTribValor::class);
});

it('TotTribPercentual::fromArray creates instance from array', function () {
    $dto = TotTribPercentual::fromArray(['pTotTribFed' => '10.00', 'pTotTribEst' => '5.00', 'pTotTribMun' => '3.00']);
    expect($dto)->toBeInstanceOf(TotTribPercentual::class);
});

it('Tributacao::fromArray creates instance from array', function () {
    $dto = Tributacao::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'indTotTrib' => '0',
    ]);

    expect($dto)->toBeInstanceOf(Tributacao::class)
        ->and($dto->indTotTrib)->toBe('0');
});

it('Tributacao::fromArray preserves pTotTribSN', function () {
    $dto = Tributacao::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'pTotTribSN' => '15.50',
    ]);

    expect($dto->pTotTribSN)->toBe('15.50');
});

it('Tributacao::fromArray creates with vTotTrib', function () {
    $dto = Tributacao::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'vTotTrib' => ['vTotTribFed' => '10.00', 'vTotTribEst' => '5.00', 'vTotTribMun' => '3.00'],
    ]);

    expect($dto->vTotTrib)->toBeInstanceOf(TotTribValor::class);
});

it('Tributacao::fromArray creates with pTotTrib', function () {
    $dto = Tributacao::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'pTotTrib' => ['pTotTribFed' => '10.00', 'pTotTribEst' => '5.00', 'pTotTribMun' => '3.00'],
    ]);

    expect($dto->pTotTrib)->toBeInstanceOf(TotTribPercentual::class);
});

it('Tributacao rejects when no totTrib variant provided', function () {
    Tributacao::fromArray(['tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1']]);
})->throws(InvalidDpsArgument::class);

it('Tributacao rejects when multiple totTrib variants provided', function () {
    Tributacao::fromArray([
        'tribMun' => ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
        'indTotTrib' => '0',
        'pTotTribSN' => '15.50',
    ]);
})->throws(InvalidDpsArgument::class);

it('DescontoCondIncond::fromArray creates instance from array', function () {
    $dto = DescontoCondIncond::fromArray(['vDescIncond' => '5.00']);
    expect($dto)->toBeInstanceOf(DescontoCondIncond::class);
});

it('DocOutNFSe::fromArray creates instance from array', function () {
    $dto = DocOutNFSe::fromArray(['cMunNFSeMun' => '3501608', 'nNFSeMun' => '123', 'cVerifNFSeMun' => 'ABC']);
    expect($dto)->toBeInstanceOf(DocOutNFSe::class);
});

it('DocNFNFS::fromArray creates instance from array', function () {
    $dto = DocNFNFS::fromArray(['nNFS' => '123', 'modNFS' => '55', 'serieNFS' => '1']);
    expect($dto)->toBeInstanceOf(DocNFNFS::class);
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

it('InfoDedRed::fromArray creates instance with documentos', function () {
    $dto = InfoDedRed::fromArray([
        'documentos' => [[
            'tpDedRed' => '1', 'dtEmiDoc' => '2026-01-01',
            'vDedutivelRedutivel' => '100.00', 'vDeducaoReducao' => '50.00',
            'chNFSe' => '12345678901234567890123456789012345678901234567890',
        ]],
    ]);

    expect($dto)->toBeInstanceOf(InfoDedRed::class)
        ->and($dto->documentos)->toHaveCount(1)
        ->and($dto->documentos[0])->toBeInstanceOf(DocDedRed::class);
});

it('InfoDedRed::fromArray preserves pDR', function () {
    $dto = InfoDedRed::fromArray(['pDR' => '10.00']);
    expect($dto->pDR)->toBe('10.00');
});

it('InfoDedRed::fromArray preserves vDR', function () {
    $dto = InfoDedRed::fromArray(['vDR' => '50.00']);
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
