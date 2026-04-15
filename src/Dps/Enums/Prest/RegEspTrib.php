<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Prest;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum RegEspTrib: string
{
    use HasLabelOf;

    case Nenhum = '0';
    case AtoCooperado = '1';
    case Estimativa = '2';
    case MicroempresaMunicipal = '3';
    case NotarioRegistrador = '4';
    case ProfissionalAutonomo = '5';
    case SociedadeProfissionais = '6';
    case Outros = '9';

    public function label(): string
    {
        return match ($this) {
            self::Nenhum => 'Nenhum',
            self::AtoCooperado => 'Ato Cooperado (Cooperativa)',
            self::Estimativa => 'Estimativa',
            self::MicroempresaMunicipal => 'Microempresa Municipal',
            self::NotarioRegistrador => 'Notário ou Registrador',
            self::ProfissionalAutonomo => 'Profissional Autônomo',
            self::SociedadeProfissionais => 'Sociedade de Profissionais',
            self::Outros => 'Outros',
        };
    }
}
