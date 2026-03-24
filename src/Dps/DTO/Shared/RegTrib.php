<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Shared;

use OwnerPro\Nfsen\Dps\Enums\Prest\OpSimpNac;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegApTribSN;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegEspTrib;

/**
 * @phpstan-type RegTribArray array{opSimpNac: string, regEspTrib: string, regApTribSN?: string}
 */
final readonly class RegTrib
{
    public function __construct(
        public OpSimpNac $opSimpNac,
        public RegEspTrib $regEspTrib,
        public ?RegApTribSN $regApTribSN = null,
    ) {}

    /** @phpstan-param RegTribArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            opSimpNac: OpSimpNac::from($data['opSimpNac']),
            regEspTrib: RegEspTrib::from($data['regEspTrib']),
            regApTribSN: isset($data['regApTribSN']) ? RegApTribSN::from($data['regApTribSN']) : null,
        );
    }
}
