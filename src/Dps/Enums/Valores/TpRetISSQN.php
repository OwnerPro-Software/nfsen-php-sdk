<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Valores;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum TpRetISSQN: string
{
    use HasLabelOf;

    case NaoRetido = '1';
    case RetidoPeloTomador = '2';
    case RetidoPeloIntermediario = '3';

    public function label(): string
    {
        return match ($this) {
            self::NaoRetido => 'Não Retido',
            self::RetidoPeloTomador => 'Retido pelo Tomador',
            self::RetidoPeloIntermediario => 'Retido pelo Intermediário',
        };
    }
}
