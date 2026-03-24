<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Valores;

/**
 * @phpstan-import-type PiscofinsArray from Piscofins
 *
 * @phpstan-type TribFedArray array{piscofins?: PiscofinsArray, vRetCP?: string, vRetIRRF?: string, vRetCSLL?: string}
 */
final readonly class TribFed
{
    public function __construct(
        public ?Piscofins $piscofins = null,
        public ?string $vRetCP = null,
        public ?string $vRetIRRF = null,
        public ?string $vRetCSLL = null,
    ) {}

    /** @phpstan-param TribFedArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            piscofins: isset($data['piscofins']) ? Piscofins::fromArray($data['piscofins']) : null,
            vRetCP: $data['vRetCP'] ?? null,
            vRetIRRF: $data['vRetIRRF'] ?? null,
            vRetCSLL: $data['vRetCSLL'] ?? null,
        );
    }
}
