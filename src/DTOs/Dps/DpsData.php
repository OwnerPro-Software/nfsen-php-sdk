<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps;

use Pulsar\NfseNacional\DTOs\Dps\InfDPS\InfDPS;
use Pulsar\NfseNacional\DTOs\Dps\Prestador\Prestador;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Servico;
use Pulsar\NfseNacional\DTOs\Dps\Tomador\Tomador;
use Pulsar\NfseNacional\DTOs\Dps\Valores\Valores;

final readonly class DpsData
{
    public function __construct(
        public InfDPS $infDPS,
        public Prestador $prest,
        public ?Tomador $toma,
        public Servico $serv,
        public Valores $valores,
    ) {}
}
