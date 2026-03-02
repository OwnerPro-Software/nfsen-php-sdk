<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

/**
 * @phpstan-type DocOutNFSeArray array{cMunNFSeMun: string, nNFSeMun: string, cVerifNFSeMun: string}
 */
final readonly class DocOutNFSe
{
    public function __construct(
        public string $cMunNFSeMun,
        public string $nNFSeMun,
        public string $cVerifNFSeMun,
    ) {}

    /** @phpstan-param DocOutNFSeArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
