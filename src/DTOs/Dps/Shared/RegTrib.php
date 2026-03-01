<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Shared;

use Pulsar\NfseNacional\Enums\Dps\Prestador\OpSimpNac;
use Pulsar\NfseNacional\Enums\Dps\Prestador\RegApTribSN;
use Pulsar\NfseNacional\Enums\Dps\Prestador\RegEspTrib;

final readonly class RegTrib
{
    public function __construct(
        public OpSimpNac $opSimpNac,
        public RegEspTrib $regEspTrib,
        public ?RegApTribSN $regApTribSN = null,
    ) {}
}
