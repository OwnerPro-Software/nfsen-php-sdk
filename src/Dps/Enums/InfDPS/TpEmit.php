<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\InfDPS;

enum TpEmit: string
{
    case Prestador = '1';
    case Tomador = '2';
    case Intermediario = '3';
}
