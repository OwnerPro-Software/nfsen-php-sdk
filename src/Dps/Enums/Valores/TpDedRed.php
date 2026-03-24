<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Valores;

enum TpDedRed: string
{
    case AlimentacaoBebidas = '1';
    case Materiais = '2';
    case ProducaoExterna = '3';
    case ReembolsoDespesas = '4';
    case RepasseConsorciado = '5';
    case RepassePlanoSaude = '6';
    case Servicos = '7';
    case SubempreitadaMaoDeObra = '8';
    case ProfissionalParceiro = '9';
    case OutrasDeducoes = '99';
}
