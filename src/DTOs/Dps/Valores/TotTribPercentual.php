<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

final readonly class TotTribPercentual
{
    public function __construct(
        public string $pTotTribFed,
        public string $pTotTribEst,
        public string $pTotTribMun,
    ) {}
}
