<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Enums\Dps\Servico;

enum MecAFComexP: string
{
    case Desconhecido = '00';
    case Nenhum = '01';
    case ACC = '02';
    case ACE = '03';
    case BNDESEximPosEmbarque = '04';
    case BNDESEximPreEmbarque = '05';
    case FGE = '06';
    case PROEXEqualizacao = '07';
    case PROEXFinanciamento = '08';
}
