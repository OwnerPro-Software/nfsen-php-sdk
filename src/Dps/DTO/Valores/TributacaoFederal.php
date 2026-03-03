<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

/**
 * @phpstan-import-type PisCofinsArray from PisCofins
 *
 * @phpstan-type TributacaoFederalArray array{piscofins?: PisCofinsArray, vRetCP?: string, vRetIRRF?: string, vRetCSLL?: string}
 */
final readonly class TributacaoFederal
{
    public function __construct(
        public ?PisCofins $piscofins = null,
        public ?string $vRetCP = null,
        public ?string $vRetIRRF = null,
        public ?string $vRetCSLL = null,
    ) {}

    /** @phpstan-param TributacaoFederalArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            piscofins: isset($data['piscofins']) ? PisCofins::fromArray($data['piscofins']) : null,
            vRetCP: $data['vRetCP'] ?? null,
            vRetIRRF: $data['vRetIRRF'] ?? null,
            vRetCSLL: $data['vRetCSLL'] ?? null,
        );
    }
}
