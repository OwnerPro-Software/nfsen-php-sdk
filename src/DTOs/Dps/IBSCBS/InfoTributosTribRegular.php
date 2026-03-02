<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

final readonly class InfoTributosTribRegular
{
    public function __construct(
        public string $CSTReg,
        public string $cClassTribReg,
    ) {}
}
