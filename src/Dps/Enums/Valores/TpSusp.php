<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Valores;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum TpSusp: string
{
    use HasLabelOf;

    case DecisaoJudicial = '1';
    case ProcedimentoAdministrativo = '2';

    /** Rótulos transcritos do `<xs:documentation>` de `TSOpExigSuspensa`. */
    public function label(): string
    {
        return match ($this) {
            self::DecisaoJudicial => 'Exigibilidade Suspensa por Decisão Judicial',
            self::ProcedimentoAdministrativo => 'Exigibilidade Suspensa por Processo Administrativo',
        };
    }
}
