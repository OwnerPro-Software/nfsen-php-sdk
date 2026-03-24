<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

enum TipoEvento: int
{
    case CancelamentoPorIniciativaPrestador = 101101;
    case CancelamentoPorIniciativaFisco = 101103;
    case CancelamentoPorDecisaoJudicial = 105102;
    case CancelamentoPorDecisaoAdministrativa = 105104;
    case CancelamentoPorOficio = 105105;
    case AnaliseParaCancelamento = 202201;
    case AnaliseParaCancelamentoDecisaoJudicial = 202205;
    case SolicitacaoCancelamento = 203202;
    case SolicitacaoCancelamentoDecisaoJudicial = 203206;
    case RejeicaoCancelamento = 204203;
    case RejeicaoCancelamentoDecisaoJudicial = 204207;
    case ConclusaoCancelamento = 205204;
    case ConclusaoCancelamentoDecisaoJudicial = 205208;
    case SubstituicaoPorIniciativaPrestador = 305101;
    case SubstituicaoPorIniciativaFisco = 305102;
    case SubstituicaoPorOficio = 305103;
    case BloqueioNfse = 467201;
    case TravamentoNfse = 907201;
}
