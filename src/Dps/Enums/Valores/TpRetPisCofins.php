<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Valores;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum TpRetPisCofins: string
{
    use HasLabelOf;

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

    /** Rótulos transcritos do `<xs:documentation>` de `TSTipoRetPISCofins`. */
    public function label(): string
    {
        return match ($this) {
            self::PisCofinsCsllNaoRetidos => 'PIS/COFINS/CSLL Não Retidos',
            self::PisCofinsRetidos => 'PIS/COFINS Retidos',
            self::PisCofinsNaoRetidos => 'PIS/COFINS Não Retidos',
            self::PisCofinsCsllRetidos => 'PIS/COFINS/CSLL Retidos',
            self::PisCofinsRetidosCsllNaoRetido => 'PIS/COFINS Retidos, CSLL Não Retido',
            self::PisRetidoCofinsCSLLNaoRetido => 'PIS Retido, COFINS/CSLL Não Retido',
            self::CofinsRetidoPisCsllNaoRetido => 'COFINS Retido, PIS/CSLL Não Retido',
            self::PisNaoRetidoCofinsCSLLRetidos => 'PIS Não Retido, COFINS/CSLL Retidos',
            self::PisCofinsNaoRetidosCsllRetido => 'PIS/COFINS Não Retidos, CSLL Retido',
            self::CofinsNaoRetidoPisCsllRetidos => 'COFINS Não Retido, PIS/CSLL Retidos',
        };
    }
}
