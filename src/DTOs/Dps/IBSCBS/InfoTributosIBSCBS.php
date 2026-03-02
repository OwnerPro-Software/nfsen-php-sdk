<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

final readonly class InfoTributosIBSCBS
{
    public function __construct(
        public InfoTributosSitClas $gIBSCBS,
    ) {}
}
