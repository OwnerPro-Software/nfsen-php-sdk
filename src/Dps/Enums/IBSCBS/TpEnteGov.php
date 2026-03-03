<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\IBSCBS;

enum TpEnteGov: string
{
    case Uniao = '1';
    case Estado = '2';
    case DistritoFederal = '3';
    case Municipio = '4';
}
