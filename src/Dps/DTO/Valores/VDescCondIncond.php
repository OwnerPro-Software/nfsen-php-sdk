<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

/**
 * @phpstan-type VDescCondIncondArray array{vDescIncond?: string, vDescCond?: string}
 */
final readonly class VDescCondIncond
{
    public function __construct(
        public ?string $vDescIncond = null,
        public ?string $vDescCond = null,
    ) {}

    /** @phpstan-param VDescCondIncondArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
