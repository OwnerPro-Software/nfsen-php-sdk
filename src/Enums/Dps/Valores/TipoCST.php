<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\Valores;

enum TipoCST: string
{
    case Nenhum = '00';
    case AliqBasica = '01';
    case AliqDiferenciada = '02';
    case AliqUnidadeMedida = '03';
    case MonofasicaRevendaAliqZero = '04';
    case SubstituicaoTributaria = '05';
    case AliqZero = '06';
    case TributavelContribuicao = '07';
    case SemIncidencia = '08';
    case Suspensao = '09';
}
