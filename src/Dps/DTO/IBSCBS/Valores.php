<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\IBSCBS;

/**
 * @phpstan-import-type TribArray from Trib
 * @phpstan-import-type GReeRepResArray from GReeRepRes
 *
 * @phpstan-type ValoresArray array{trib: TribArray, gReeRepRes?: GReeRepResArray}
 */
final readonly class Valores
{
    public function __construct(
        public Trib $trib,
        public ?GReeRepRes $gReeRepRes = null,
    ) {}

    /** @phpstan-param ValoresArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            trib: Trib::fromArray($data['trib']),
            gReeRepRes: isset($data['gReeRepRes']) ? GReeRepRes::fromArray($data['gReeRepRes']) : null,
        );
    }
}
