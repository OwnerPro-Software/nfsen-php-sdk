<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Valores;

enum TribISSQN: string
{
    case Tributavel = '1';
    case Imunidade = '2';
    case ExportacaoServico = '3';
    case NaoIncidencia = '4';
}
