<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

/**
 * Em que ponto está o cancelamento por análise fiscal de uma NFS-e.
 *
 * Não é campo da nota: `TStat` (`infNFSe/cStat`) não tem estado para análise em
 * curso — a NFS-e continua "Gerada" até o fisco decidir. A situação é deduzida dos
 * eventos vinculados à chave: o pedido (e101103) e seu desfecho (e105104 deferido,
 * e105105 indeferido).
 *
 * @api
 */
enum SituacaoCancelamento: string
{
    case SemPedido = 'SEM_PEDIDO';
    case EmAnalise = 'EM_ANALISE';
    case Deferido = 'DEFERIDO';
    case Indeferido = 'INDEFERIDO';
}
