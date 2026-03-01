<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

use Pulsar\NfseNacional\Enums\Dps\Valores\TipoCST;
use Pulsar\NfseNacional\Enums\Dps\Valores\TipoRetPisCofins;

final readonly class PisCofins
{
    public function __construct(
        public TipoCST $CST,
        public ?string $vBCPisCofins = null,
        public ?string $pAliqPis = null,
        public ?string $pAliqCofins = null,
        public ?string $vPis = null,
        public ?string $vCofins = null,
        public ?TipoRetPisCofins $tpRetPisCofins = null,
    ) {}
}
