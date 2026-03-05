<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

/**
 * @phpstan-type NFNFSArray array{nNFS: string, modNFS: string, serieNFS: string}
 */
final readonly class NFNFS
{
    public function __construct(
        public string $nNFS,
        public string $modNFS,
        public string $serieNFS,
    ) {}

    /** @phpstan-param NFNFSArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
