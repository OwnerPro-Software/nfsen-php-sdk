<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

/**
 * @phpstan-import-type VServPrestArray from VServPrest
 * @phpstan-import-type TribArray from Trib
 * @phpstan-import-type VDescCondIncondArray from VDescCondIncond
 * @phpstan-import-type VDedRedArray from VDedRed
 *
 * @phpstan-type ValoresArray array{vServPrest: VServPrestArray, trib: TribArray, vDescCondIncond?: VDescCondIncondArray, vDedRed?: VDedRedArray}
 */
final readonly class Valores
{
    public function __construct(
        public VServPrest $vServPrest,
        public Trib $trib,
        public ?VDescCondIncond $vDescCondIncond = null,
        public ?VDedRed $vDedRed = null,
    ) {}

    /** @phpstan-param ValoresArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            vServPrest: VServPrest::fromArray($data['vServPrest']),
            trib: Trib::fromArray($data['trib']),
            vDescCondIncond: isset($data['vDescCondIncond']) ? VDescCondIncond::fromArray($data['vDescCondIncond']) : null,
            vDedRed: isset($data['vDedRed']) ? VDedRed::fromArray($data['vDedRed']) : null,
        );
    }
}
