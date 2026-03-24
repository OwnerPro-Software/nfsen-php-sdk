<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

/**
 * @phpstan-type NFSeMunArray array{cMunNFSeMun: string, nNFSeMun: string, cVerifNFSeMun: string}
 */
final readonly class NFSeMun
{
    public function __construct(
        public string $cMunNFSeMun,
        public string $nNFSeMun,
        public string $cVerifNFSeMun,
    ) {}

    /** @phpstan-param NFSeMunArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
