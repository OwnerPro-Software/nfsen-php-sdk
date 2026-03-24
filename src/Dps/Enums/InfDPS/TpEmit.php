<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\InfDPS;

enum TpEmit: string
{
    case Prestador = '1';
    case Tomador = '2';
    case Intermediario = '3';
}
