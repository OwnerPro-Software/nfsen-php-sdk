<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Valores;

enum TpImunidade: string
{
    case NaoInformado = '0';
    case PatrimonioRendaServicos = '1';
    case TemploQualquerCulto = '2';
    case PartidosSindicaisEducacao = '3';
    case LivrosJornaisPeriodicos = '4';
    case FonogramasVideofonogramas = '5';
}
