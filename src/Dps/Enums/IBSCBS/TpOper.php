<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\IBSCBS;

enum TpOper: string
{
    case FornecimentoPagamentoPosterior = '1';
    case RecebimentoFornecimentoRealizado = '2';
    case FornecimentoPagamentoRealizado = '3';
    case RecebimentoFornecimentoPosterior = '4';
    case FornecimentoRecebimentoConcomitantes = '5';
}
