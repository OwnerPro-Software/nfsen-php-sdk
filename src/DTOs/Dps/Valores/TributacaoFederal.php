<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

final readonly class TributacaoFederal
{
    public function __construct(
        public ?PisCofins $pisCofins = null,
        public ?string $vRetCP = null,
        public ?string $vRetIRRF = null,
        public ?string $vRetCSLL = null,
    ) {}
}
