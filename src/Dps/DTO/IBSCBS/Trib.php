<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\IBSCBS;

/**
 * @phpstan-import-type GIBSCBSArray from GIBSCBS
 *
 * @phpstan-type TribArray array{gIBSCBS: GIBSCBSArray}
 */
final readonly class Trib
{
    public function __construct(
        public GIBSCBS $gIBSCBS,
    ) {}

    /** @phpstan-param TribArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            gIBSCBS: GIBSCBS::fromArray($data['gIBSCBS']),
        );
    }
}
