<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

final readonly class DocNFNFS
{
    public function __construct(
        public string $nNFS,
        public string $modNFS,
        public string $serieNFS,
    ) {}
}
