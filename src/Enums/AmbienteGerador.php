<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Enums;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

/**
 * Quem gerou a NFS-e (`infNFSe/ambGer`, simpleType `TSAmbGeradorNFSe`).
 *
 * @api
 */
enum AmbienteGerador: string
{
    use HasLabelOf;

    case Prefeitura = '1';
    case SistemaNacional = '2';

    /** Rótulos transcritos do `<xs:documentation>` de `TSAmbGeradorNFSe`. */
    public function label(): string
    {
        return match ($this) {
            self::Prefeitura => 'Prefeitura',
            self::SistemaNacional => 'Sistema Nacional da NFS-e',
        };
    }
}
