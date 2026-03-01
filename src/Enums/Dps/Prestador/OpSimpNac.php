<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\Prestador;

enum OpSimpNac: string
{
    case NaoOptante = '1';
    case OptanteMEI = '2';
    case OptanteMEEPP = '3';
}
