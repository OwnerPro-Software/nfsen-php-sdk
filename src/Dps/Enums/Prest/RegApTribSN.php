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

    /**
     * Rótulos transcritos literalmente do `<xs:documentation>` de `TSRegimeApuracaoSimpNac`
     * em `storage/schemes/tiposSimples_v1.01.xsd`, que é a fonte de verdade — o texto sai
     * impresso na DANFSe. Não parafraseie: "por fora do SN" e "pela NFS-e" descrevem
     * regimes de apuração diferentes.
     */
    public function label(): string
    {
        return match ($this) {
            self::ApuracaoSN => 'Regime de apuração dos tributos federais e municipal pelo SN',
            self::ApuracaoSNIssqnFora => 'Regime de apuração dos tributos federais pelo SN e ISSQN por fora do SN conforme respectiva legislação municipal do tributo',
            self::ApuracaoForaSN => 'Regime de apuração dos tributos federais e municipal por fora do SN conforme respectivas legislações federal e municipal de cada tributo',
        };
    }
}
