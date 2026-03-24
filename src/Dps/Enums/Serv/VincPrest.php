<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Serv;

enum VincPrest: string
{
    case SemVinculo = '0';
    case Controlada = '1';
    case Controladora = '2';
    case Coligada = '3';
    case Matriz = '4';
    case FilialSucursal = '5';
    case OutroVinculo = '6';
    case Desconhecido = '9';
}
