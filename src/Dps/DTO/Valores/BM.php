<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

use OwnerPro\Nfsen\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-type BMArray array{nBM: string, vRedBCBM?: string, pRedBCBM?: string}
 */
final readonly class BM
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $nBM,
        public ?string $vRedBCBM = null,
        public ?string $pRedBCBM = null,
    ) {
        self::validateAtMostOne(
            ['vRedBCBM' => $vRedBCBM, 'pRedBCBM' => $pRedBCBM],
            'BM deve ter apenas vRedBCBM ou pRedBCBM, não ambos.',
        );
    }

    /** @phpstan-param BMArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
