<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Prest;

enum OpSimpNac: string
{
    case NaoOptante = '1';
    case OptanteMEI = '2';
    case OptanteMEEPP = '3';
}
