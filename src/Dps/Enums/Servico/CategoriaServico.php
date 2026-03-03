<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Servico;

enum CategoriaServico: string
{
    case Locacao = '1';
    case Sublocacao = '2';
    case Arrendamento = '3';
    case DireitoPassagem = '4';
    case PermissaoUso = '5';
}
