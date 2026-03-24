<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Serv;

enum MovTempBens: string
{
    case Desconhecido = '0';
    case Nao = '1';
    case VinculadaImportacao = '2';
    case VinculadaExportacao = '3';
}
