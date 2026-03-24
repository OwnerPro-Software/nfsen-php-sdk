<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\Enums\Shared;

enum CNaoNIF: string
{
    case NaoInformado = '0';
    case Dispensado = '1';
    case NaoExigencia = '2';
}
