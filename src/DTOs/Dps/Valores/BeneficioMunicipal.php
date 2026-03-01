<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;

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
}
