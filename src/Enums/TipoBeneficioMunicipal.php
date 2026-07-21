<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

/**
 * Tipo de benefício municipal (`infNFSe/valores/tpBM`, simpleType `TBMISSQN`).
 *
 * Campo apurado pelo fisco, não declarado na DPS — daí viver fora de `Dps\Enums`.
 *
 * @api
 */
enum TipoBeneficioMunicipal: string
{
    use HasLabelOf;

    case Isencao = '1';
    case ReducaoBasePercentual = '2';
    case ReducaoBaseValor = '3';
    case AliquotaDiferenciada = '4';

    /** Rótulos transcritos do `<xs:documentation>` de `TBMISSQN`. */
    public function label(): string
    {
        return match ($this) {
            self::Isencao => 'Isenção',
            self::ReducaoBasePercentual => "Redução da BC em 'ppBM' %",
            self::ReducaoBaseValor => "Redução da BC em R$ 'vInfoBM'",
            self::AliquotaDiferenciada => "Alíquota Diferenciada de 'aliqDifBM' %",
        };
    }
}
