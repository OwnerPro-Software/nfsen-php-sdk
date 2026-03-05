<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Serv;

enum Mdic: string
{
    case NaoEnviar = '0';
    case Enviar = '1';
}
