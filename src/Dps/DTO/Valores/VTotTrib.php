<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

/**
 * @phpstan-type VTotTribArray array{vTotTribFed: string, vTotTribEst: string, vTotTribMun: string}
 */
final readonly class VTotTrib
{
    public function __construct(
        public string $vTotTribFed,
        public string $vTotTribEst,
        public string $vTotTribMun,
    ) {}

    /** @phpstan-param VTotTribArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
