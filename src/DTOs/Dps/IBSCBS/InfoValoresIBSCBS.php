<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

final readonly class InfoValoresIBSCBS
{
    public function __construct(
        public InfoTributosIBSCBS $trib,
        public ?InfoReeRepRes $gReeRepRes = null,
    ) {}
}
