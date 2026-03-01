<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\Prestador;

enum RegEspTrib: string
{
    case Nenhum = '0';
    case AtoCooperado = '1';
    case Estimativa = '2';
    case MicroempresaMunicipal = '3';
    case NotarioRegistrador = '4';
    case ProfissionalAutonomo = '5';
    case SociedadeProfissionais = '6';
}
