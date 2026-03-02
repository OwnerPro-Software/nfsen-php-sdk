<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\IBSCBS;

enum TipoChaveDFe: string
{
    case NFSe = '1';
    case NFe = '2';
    case CTe = '3';
    case Outro = '9';
}
