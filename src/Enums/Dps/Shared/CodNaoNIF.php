<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\Shared;

enum CodNaoNIF: string
{
    case NaoInformado = '0';
    case Dispensado = '1';
    case NaoExigencia = '2';
}
