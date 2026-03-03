<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\IBSCBS;

enum TpReeRepRes: string
{
    case RepasseImoveis = '01';
    case RepasseAgenciaTurismo = '02';
    case ReembolsoProducaoExterna = '03';
    case ReembolsoMidia = '04';
    case Outros = '99';
}
