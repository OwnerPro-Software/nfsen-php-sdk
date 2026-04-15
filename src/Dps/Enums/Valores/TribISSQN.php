<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Valores;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum TribISSQN: string
{
    use HasLabelOf;

    case Tributavel = '1';
    case Imunidade = '2';
    case ExportacaoServico = '3';
    case NaoIncidencia = '4';

    public function label(): string
    {
        return match ($this) {
            self::Tributavel => 'Operação Tributável',
            self::Imunidade => 'Imunidade',
            self::ExportacaoServico => 'Exportação de Serviço',
            self::NaoIncidencia => 'Não Incidência',
        };
    }
}
