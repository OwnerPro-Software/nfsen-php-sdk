<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\Enums\Prest;

enum RegApTribSN: string
{
    case ApuracaoSN = '1';
    case ApuracaoSNIssqnFora = '2';
    case ApuracaoForaSN = '3';
}
