<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\Servico;

enum ObjetoLocacao: string
{
    case Ferrovia = '1';
    case Rodovia = '2';
    case Postes = '3';
    case Cabos = '4';
    case Dutos = '5';
    case Condutos = '6';
}
