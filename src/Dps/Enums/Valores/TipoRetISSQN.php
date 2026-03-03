<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Valores;

enum TipoRetISSQN: string
{
    case NaoRetido = '1';
    case RetidoPeloTomador = '2';
    case RetidoPeloIntermediario = '3';
}
