<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

/**
 * Tipos de evento aceitos pela consulta `/nfse/{chave}/eventos/{tipoEvento}/{nSeq}`.
 *
 * Os códigos seguem a ordem do enum `tipoEvento` em `storage/schemes/SefinNacional-swagger.json`.
 * Os nomes vêm da documentação de cada elemento `eNNNNNN` em
 * `storage/schemes/tiposEventos_v1.01.xsd` e são consistentes com {@see TipoEventoDistribuicao}.
 *
 * Exceção: 467201 e 907201 não constam no XSD — aparecem apenas no swagger. Seus nomes
 * derivam da correspondência posicional com as duas últimas entradas de
 * {@see TipoEventoDistribuicao}, cujas 16 primeiras batem com o XSD.
 */
enum TipoEvento: int
{
    case Cancelamento = 101101;
    case SolicitacaoCancelamentoAnaliseFiscal = 101103;
    case CancelamentoPorSubstituicao = 105102;
    case CancelamentoDeferidoAnaliseFiscal = 105104;
    case CancelamentoIndeferidoAnaliseFiscal = 105105;
    case ConfirmacaoPrestador = 202201;
    case RejeicaoPrestador = 202205;
    case ConfirmacaoTomador = 203202;
    case RejeicaoTomador = 203206;
    case ConfirmacaoIntermediario = 204203;
    case RejeicaoIntermediario = 204207;
    case ConfirmacaoTacita = 205204;
    case AnulacaoRejeicao = 205208;
    case CancelamentoPorOficio = 305101;
    case BloqueioPorOficio = 305102;
    case DesbloqueioPorOficio = 305103;
    case InclusaoNfseDan = 467201;
    case TributosNfseRecolhidos = 907201;
}
