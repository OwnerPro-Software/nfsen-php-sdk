<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\InfDPS;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum TpEmit: string
{
    use HasLabelOf;

    case Prestador = '1';
    case Tomador = '2';
    case Intermediario = '3';

    /** Rótulos transcritos do `<xs:documentation>` de `TSEmitenteDPS`. */
    public function label(): string
    {
        return match ($this) {
            self::Prestador => 'Prestador',
            self::Tomador => 'Tomador',
            self::Intermediario => 'Intermediário',
        };
    }
}
