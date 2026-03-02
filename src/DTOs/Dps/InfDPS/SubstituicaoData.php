<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\InfDPS;

use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;

final readonly class SubstituicaoData
{
    public function __construct(
        public string $chSubstda,
        public CodigoJustificativaSubstituicao $cMotivo,
        public ?string $xMotivo = null,
    ) {}
}
