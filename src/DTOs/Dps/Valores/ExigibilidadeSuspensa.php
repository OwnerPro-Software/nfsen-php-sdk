<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

use Pulsar\NfseNacional\Enums\Dps\Valores\TipoSuspensao;

final readonly class ExigibilidadeSuspensa
{
    public function __construct(
        public TipoSuspensao $tpSusp,
        public string $nProcesso,
    ) {}
}
