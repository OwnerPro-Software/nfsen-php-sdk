<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

enum TipoDocumentoFiscal: string
{
    case Nenhum = 'NENHUM';
    case Dps = 'DPS';
    case PedidoRegistroEvento = 'PEDIDO_REGISTRO_EVENTO';
    case Nfse = 'NFSE';
    case Evento = 'EVENTO';
    case Cnc = 'CNC';
}
