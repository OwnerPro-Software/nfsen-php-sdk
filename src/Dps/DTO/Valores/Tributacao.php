<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type TributacaoMunicipalArray from TributacaoMunicipal
 * @phpstan-import-type TotTribValorArray from TotTribValor
 * @phpstan-import-type TotTribPercentualArray from TotTribPercentual
 * @phpstan-import-type TributacaoFederalArray from TributacaoFederal
 *
 * @phpstan-type TributacaoArray array{tribMun: TributacaoMunicipalArray, vTotTrib?: TotTribValorArray, pTotTrib?: TotTribPercentualArray, indTotTrib?: string, pTotTribSN?: string, tribFed?: TributacaoFederalArray}
 */
final readonly class Tributacao
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public TributacaoMunicipal $tribMun,
        public ?TotTribValor $vTotTrib = null,
        public ?TotTribPercentual $pTotTrib = null,
        public ?string $indTotTrib = null,
        public ?string $pTotTribSN = null,
        public ?TributacaoFederal $tribFed = null,
    ) {
        self::validateChoice(
            ['valor total tributos (vTotTrib)' => $vTotTrib, 'percentual total tributos (pTotTrib)' => $pTotTrib, 'indicador total tributos (indTotTrib)' => $indTotTrib, 'percentual Simples Nacional (pTotTribSN)' => $pTotTribSN],
            expected: 1,
            path: 'infDPS/valores/trib',
        );
    }

    /** @phpstan-param TributacaoArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tribMun: TributacaoMunicipal::fromArray($data['tribMun']),
            vTotTrib: isset($data['vTotTrib']) ? TotTribValor::fromArray($data['vTotTrib']) : null,
            pTotTrib: isset($data['pTotTrib']) ? TotTribPercentual::fromArray($data['pTotTrib']) : null,
            indTotTrib: $data['indTotTrib'] ?? null,
            pTotTribSN: $data['pTotTribSN'] ?? null,
            tribFed: isset($data['tribFed']) ? TributacaoFederal::fromArray($data['tribFed']) : null,
        );
    }
}
