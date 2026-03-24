<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

/**
 * @phpstan-type PTotTribArray array{pTotTribFed: string, pTotTribEst: string, pTotTribMun: string}
 */
final readonly class PTotTrib
{
    public function __construct(
        public string $pTotTribFed,
        public string $pTotTribEst,
        public string $pTotTribMun,
    ) {}

    /** @phpstan-param PTotTribArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
