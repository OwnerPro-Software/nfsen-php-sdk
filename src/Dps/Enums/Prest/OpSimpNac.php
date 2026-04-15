<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Prest;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum OpSimpNac: string
{
    use HasLabelOf;

    case NaoOptante = '1';
    case OptanteMEI = '2';
    case OptanteMEEPP = '3';

    public function label(): string
    {
        return match ($this) {
            self::NaoOptante => 'Não Optante',
            self::OptanteMEI => 'Optante - Microempreendedor Individual (MEI)',
            self::OptanteMEEPP => 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)',
        };
    }
}
