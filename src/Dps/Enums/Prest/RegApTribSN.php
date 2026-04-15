<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Prest;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum RegApTribSN: string
{
    use HasLabelOf;

    case ApuracaoSN = '1';
    case ApuracaoSNIssqnFora = '2';
    case ApuracaoForaSN = '3';

    public function label(): string
    {
        return match ($this) {
            self::ApuracaoSN => 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
            self::ApuracaoSNIssqnFora => 'Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo',
            self::ApuracaoForaSN => 'Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo',
        };
    }
}
