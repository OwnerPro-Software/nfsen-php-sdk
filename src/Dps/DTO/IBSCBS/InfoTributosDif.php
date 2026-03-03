<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

/**
 * @phpstan-type InfoTributosDifArray array{pDifUF: string, pDifMun: string, pDifCBS: string}
 */
final readonly class InfoTributosDif
{
    public function __construct(
        public string $pDifUF,
        public string $pDifMun,
        public string $pDifCBS,
    ) {}

    /** @phpstan-param InfoTributosDifArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
