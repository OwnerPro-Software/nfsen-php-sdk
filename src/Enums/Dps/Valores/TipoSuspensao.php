<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\Valores;

enum TipoSuspensao: string
{
    case DecisaoJudicial = '1';
    case ProcedimentoAdministrativo = '2';
}
