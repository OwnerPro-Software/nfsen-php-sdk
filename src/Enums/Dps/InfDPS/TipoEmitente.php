<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\InfDPS;

enum TipoEmitente: string
{
    case Prestador = '1';
    case Tomador = '2';
    case Intermediario = '3';
}
