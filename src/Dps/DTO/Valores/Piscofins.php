<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

use OwnerPro\Nfsen\Dps\Enums\Valores\CST;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetPisCofins;

/**
 * @phpstan-type PiscofinsArray array{CST: string, vBCPisCofins?: string, pAliqPis?: string, pAliqCofins?: string, vPis?: string, vCofins?: string, tpRetPisCofins?: string}
 */
final readonly class Piscofins
{
    public function __construct(
        public CST $CST,
        public ?string $vBCPisCofins = null,
        public ?string $pAliqPis = null,
        public ?string $pAliqCofins = null,
        public ?string $vPis = null,
        public ?string $vCofins = null,
        public ?TpRetPisCofins $tpRetPisCofins = null,
    ) {}

    /** @phpstan-param PiscofinsArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            CST: CST::from($data['CST']),
            vBCPisCofins: $data['vBCPisCofins'] ?? null,
            pAliqPis: $data['pAliqPis'] ?? null,
            pAliqCofins: $data['pAliqCofins'] ?? null,
            vPis: $data['vPis'] ?? null,
            vCofins: $data['vCofins'] ?? null,
            tpRetPisCofins: isset($data['tpRetPisCofins']) ? TpRetPisCofins::from($data['tpRetPisCofins']) : null,
        );
    }
}
