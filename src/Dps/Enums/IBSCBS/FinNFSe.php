<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\IBSCBS;

use OwnerPro\Nfsen\Dps\Enums\HasLabelOf;

enum FinNFSe: string
{
    use HasLabelOf;

    case Regular = '0';

    /** Rótulo transcrito do `<xs:documentation>` de `TSRTCFinNFSe`. */
    public function label(): string
    {
        return match ($this) {
            self::Regular => 'NFS-e regular',
        };
    }
}
