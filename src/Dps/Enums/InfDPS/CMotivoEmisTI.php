<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\InfDPS;

enum CMotivoEmisTI: string
{
    case ImportacaoServico = '1';
    case LegislacaoMunicipal = '2';
    case RecusaEmissaoPrestador = '3';
    case RejeicaoNfsePrestador = '4';
}
