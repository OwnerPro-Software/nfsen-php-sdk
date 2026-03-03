<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-type BeneficioMunicipalArray array{nBM: string, vRedBCBM?: string, pRedBCBM?: string}
 */
final readonly class BeneficioMunicipal
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $nBM,
        public ?string $vRedBCBM = null,
        public ?string $pRedBCBM = null,
    ) {
        self::validateAtMostOne(
            ['vRedBCBM' => $vRedBCBM, 'pRedBCBM' => $pRedBCBM],
            'BM deve ter apenas vRedBCBM ou pRedBCBM, não ambos.',
        );
    }

    /** @phpstan-param BeneficioMunicipalArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
