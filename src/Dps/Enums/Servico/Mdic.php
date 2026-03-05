<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Servico;

enum Mdic: string
{
    case NaoEnviar = '0';
    case Enviar = '1';
}
