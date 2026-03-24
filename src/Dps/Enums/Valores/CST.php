<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Valores;

enum CST: string
{
    case Nenhum = '00';
    case AliqBasica = '01';
    case AliqDiferenciada = '02';
    case AliqUnidadeMedida = '03';
    case MonofasicaRevendaAliqZero = '04';
    case SubstituicaoTributaria = '05';
    case AliqZero = '06';
    case IsentaContribuicao = '07';
    case SemIncidencia = '08';
    case Suspensao = '09';
    case OutrasOperacoesSaida = '49';
    case CreditoReceitaTributadaMercInt = '50';
    case CreditoReceitaNaoTributadaMercInt = '51';
    case CreditoReceitaExportacao = '52';
    case CreditoReceitasTribNaoTribMercInt = '53';
    case CreditoReceitasTribMercIntExport = '54';
    case CreditoReceitasNaoTribMercIntExport = '55';
    case CreditoReceitasTribNaoTribMercIntExport = '56';
    case CreditoPresumidoRecTribMercInt = '60';
    case CreditoPresumidoRecNaoTribMercInt = '61';
    case CreditoPresumidoRecExportacao = '62';
    case CreditoPresumidoRecTribNaoTribMercInt = '63';
    case CreditoPresumidoRecTribMercIntExport = '64';
    case CreditoPresumidoRecNaoTribMercIntExport = '65';
    case CreditoPresumidoRecTribNaoTribMercIntExport = '66';
    case CreditoPresumidoOutras = '67';
    case AquisicaoSemCredito = '70';
    case AquisicaoIsencao = '71';
    case AquisicaoSuspensao = '72';
    case AquisicaoAliqZero = '73';
    case AquisicaoSemIncidencia = '74';
    case AquisicaoSubstituicaoTributaria = '75';
    case OutrasOperacoesEntrada = '98';
    case OutrasOperacoes = '99';
}
