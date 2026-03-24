<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Serv;

/**
 * @phpstan-type CServArray array{cTribNac: string, xDescServ: string, cNBS?: string, cTribMun?: string, cIntContrib?: string}
 */
final readonly class CServ
{
    public function __construct(
        public string $cTribNac,
        public string $xDescServ,
        public ?string $cNBS = null,
        public ?string $cTribMun = null,
        public ?string $cIntContrib = null,
    ) {}

    /** @phpstan-param CServArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
