<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

use Pulsar\NfseNacional\Dps\Enums\Valores\TipoCST;
use Pulsar\NfseNacional\Dps\Enums\Valores\TipoRetPisCofins;

/**
 * @phpstan-type PisCofinsArray array{CST: string, vBCPisCofins?: string, pAliqPis?: string, pAliqCofins?: string, vPis?: string, vCofins?: string, tpRetPisCofins?: string}
 */
final readonly class PisCofins
{
    public function __construct(
        public TipoCST $CST,
        public ?string $vBCPisCofins = null,
        public ?string $pAliqPis = null,
        public ?string $pAliqCofins = null,
        public ?string $vPis = null,
        public ?string $vCofins = null,
        public ?TipoRetPisCofins $tpRetPisCofins = null,
    ) {}

    /** @phpstan-param PisCofinsArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            CST: TipoCST::from($data['CST']),
            vBCPisCofins: $data['vBCPisCofins'] ?? null,
            pAliqPis: $data['pAliqPis'] ?? null,
            pAliqCofins: $data['pAliqCofins'] ?? null,
            vPis: $data['vPis'] ?? null,
            vCofins: $data['vCofins'] ?? null,
            tpRetPisCofins: isset($data['tpRetPisCofins']) ? TipoRetPisCofins::from($data['tpRetPisCofins']) : null,
        );
    }
}
