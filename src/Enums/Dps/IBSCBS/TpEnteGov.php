<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\IBSCBS;

enum TpEnteGov: string
{
    case Uniao = '1';
    case Estado = '2';
    case DistritoFederal = '3';
    case Municipio = '4';
}
