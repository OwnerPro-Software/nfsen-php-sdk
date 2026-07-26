<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

/**
 * Eventos que `pedRegEvento` aceita para retirar uma NFS-e de circulação.
 *
 * O valor é o código do evento, usado no Id do pedido ("PRE" + chave + código,
 * TSIdPedRegEvt em tiposSimples_v1.01.xsd). Os textos de `xDesc` são fixados por
 * enumeração no XSD (TE101101 e TE101103 em tiposEventos_v1.01.xsd).
 *
 * @api
 */
enum EventoCancelamento: string
{
    case Cancelamento = '101101';
    case SolicitacaoAnaliseFiscal = '101103';

    public function tag(): string
    {
        return 'e'.$this->value;
    }

    public function xDesc(): string
    {
        return match ($this) {
            self::Cancelamento => 'Cancelamento de NFS-e',
            self::SolicitacaoAnaliseFiscal => 'Solicitação de Análise Fiscal para Cancelamento de NFS-e',
        };
    }
}
