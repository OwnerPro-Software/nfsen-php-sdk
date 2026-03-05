<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Valores;

enum TpRetPisCofins: string
{
    case PisCofinsCsllNaoRetidos = '0';
    case PisCofinsRetidos = '1';
    case PisCofinsNaoRetidos = '2';
    case PisCofinsCsllRetidos = '3';
    case PisCofinsRetidosCsllNaoRetido = '4';
    case PisRetidoCofinsCSLLNaoRetido = '5';
    case CofinsRetidoPisCsllNaoRetido = '6';
    case PisNaoRetidoCofinsCSLLRetidos = '7';
    case PisCofinsNaoRetidosCsllRetido = '8';
    case CofinsNaoRetidoPisCsllRetidos = '9';
}
