<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Shared;

enum CodNaoNIF: string
{
    case NaoInformado = '0';
    case Dispensado = '1';
    case NaoExigencia = '2';
}
