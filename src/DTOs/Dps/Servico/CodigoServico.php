<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

final readonly class CodigoServico
{
    public function __construct(
        public string $cTribNac,
        public string $xDescServ,
        public string $cNBS,
        public ?string $cTribMun = null,
        public ?string $cIntContrib = null,
    ) {}
}
