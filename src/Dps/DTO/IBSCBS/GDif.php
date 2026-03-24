<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\IBSCBS;

/**
 * @phpstan-type GDifArray array{pDifUF: string, pDifMun: string, pDifCBS: string}
 */
final readonly class GDif
{
    public function __construct(
        public string $pDifUF,
        public string $pDifMun,
        public string $pDifCBS,
    ) {}

    /** @phpstan-param GDifArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
