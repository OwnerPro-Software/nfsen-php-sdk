<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

final readonly class DocOutNFSe
{
    public function __construct(
        public string $cMunNFSeMun,
        public string $nNFSeMun,
        public string $cVerifNFSeMun,
    ) {}
}
