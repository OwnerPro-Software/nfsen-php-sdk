<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

/**
 * @phpstan-type VDescCondIncondArray array{vDescIncond?: string, vDescCond?: string}
 */
final readonly class VDescCondIncond
{
    public function __construct(
        public ?string $vDescIncond = null,
        public ?string $vDescCond = null,
    ) {
        if ($vDescIncond === null && $vDescCond === null) {
            throw new InvalidDpsArgument('vDescCondIncond deve conter ao menos um campo preenchido.');
        }
    }

    /** @phpstan-param VDescCondIncondArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
