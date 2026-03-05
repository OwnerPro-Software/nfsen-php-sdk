<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type TribMunArray from TribMun
 * @phpstan-import-type VTotTribArray from VTotTrib
 * @phpstan-import-type PTotTribArray from PTotTrib
 * @phpstan-import-type TribFedArray from TribFed
 *
 * @phpstan-type TribArray array{tribMun: TribMunArray, vTotTrib?: VTotTribArray, pTotTrib?: PTotTribArray, indTotTrib?: string, pTotTribSN?: string, tribFed?: TribFedArray}
 */
final readonly class Trib
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public TribMun $tribMun,
        public ?VTotTrib $vTotTrib = null,
        public ?PTotTrib $pTotTrib = null,
        public ?string $indTotTrib = null,
        public ?string $pTotTribSN = null,
        public ?TribFed $tribFed = null,
    ) {
        self::validateChoice(
            ['valor total tributos (vTotTrib)' => $vTotTrib, 'percentual total tributos (pTotTrib)' => $pTotTrib, 'indicador total tributos (indTotTrib)' => $indTotTrib, 'percentual Simples Nacional (pTotTribSN)' => $pTotTribSN],
            expected: 1,
            path: 'infDPS/valores/trib',
        );
    }

    /** @phpstan-param TribArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tribMun: TribMun::fromArray($data['tribMun']),
            vTotTrib: isset($data['vTotTrib']) ? VTotTrib::fromArray($data['vTotTrib']) : null,
            pTotTrib: isset($data['pTotTrib']) ? PTotTrib::fromArray($data['pTotTrib']) : null,
            indTotTrib: $data['indTotTrib'] ?? null,
            pTotTribSN: $data['pTotTribSN'] ?? null,
            tribFed: isset($data['tribFed']) ? TribFed::fromArray($data['tribFed']) : null,
        );
    }
}
