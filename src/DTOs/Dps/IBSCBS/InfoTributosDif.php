<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

final readonly class InfoTributosDif
{
    public function __construct(
        public string $pDifUF,
        public string $pDifMun,
        public string $pDifCBS,
    ) {}
}
