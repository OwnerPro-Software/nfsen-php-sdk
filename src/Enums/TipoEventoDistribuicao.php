<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

enum TipoEventoDistribuicao: string
{
    case Cancelamento = 'CANCELAMENTO';
    case SolicitacaoCancelamentoAnaliseFiscal = 'SOLICITACAO_CANCELAMENTO_ANALISE_FISCAL';
    case CancelamentoPorSubstituicao = 'CANCELAMENTO_POR_SUBSTITUICAO';
    case CancelamentoDeferidoAnaliseFiscal = 'CANCELAMENTO_DEFERIDO_ANALISE_FISCAL';
    case CancelamentoIndeferidoAnaliseFiscal = 'CANCELAMENTO_INDEFERIDO_ANALISE_FISCAL';
    case ConfirmacaoPrestador = 'CONFIRMACAO_PRESTADOR';
    case RejeicaoPrestador = 'REJEICAO_PRESTADOR';
    case ConfirmacaoTomador = 'CONFIRMACAO_TOMADOR';
    case RejeicaoTomador = 'REJEICAO_TOMADOR';
    case ConfirmacaoIntermediario = 'CONFIRMACAO_INTERMEDIARIO';
    case RejeicaoIntermediario = 'REJEICAO_INTERMEDIARIO';
    case ConfirmacaoTacita = 'CONFIRMACAO_TACITA';
    case AnulacaoRejeicao = 'ANULACAO_REJEICAO';
    case CancelamentoPorOficio = 'CANCELAMENTO_POR_OFICIO';
    case BloqueioPorOficio = 'BLOQUEIO_POR_OFICIO';
    case DesbloqueioPorOficio = 'DESBLOQUEIO_POR_OFICIO';
    case InclusaoNfseDan = 'INCLUSAO_NFSE_DAN';
    case TributosNfseRecolhidos = 'TRIBUTOS_NFSE_RECOLHIDOS';
}
