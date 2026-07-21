<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

/**
 * Marca d'água exigida pela NT 008 nos itens 2.5.1 e 2.5.2.
 *
 * O XML da NFS-e não carrega esta informação: `infNFSe/cStat` (`TStat`) só descreve
 * como a nota foi gerada, e cancelamento/substituição chegam depois, como evento
 * separado. Por isso a marca é escolhida por quem renderiza o DANFSe, a partir dos
 * eventos que consultou.
 *
 * @api
 */
enum MarcaDagua: string
{
    case Cancelada = 'CANCELADA';

    case Substituida = 'SUBSTITUIDA';

    /** Texto impresso na diagonal, conforme os modelos dos itens 2.5.1 e 2.5.2. */
    public function texto(): string
    {
        return match ($this) {
            self::Cancelada => 'CANCELADA',
            self::Substituida => 'SUBSTITUÍDA',
        };
    }
}
