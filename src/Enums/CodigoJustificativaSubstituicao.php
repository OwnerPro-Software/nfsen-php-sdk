<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums;

enum CodigoJustificativaSubstituicao: string
{
    case DesenquadramentoSimplesNacional = '01';
    case EnquadramentoSimplesNacional = '02';
    case InclusaoRetroativaImunidadeIsencao = '03';
    case ExclusaoRetroativaImunidadeIsencao = '04';
    case RejeicaoTomadorIntermediario = '05';
    case Outros = '99';
}
