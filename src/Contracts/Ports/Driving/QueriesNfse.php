<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Ports\Driving;

use Pulsar\NfseNacional\Builders\Consulta\ConsultaBuilder;

interface QueriesNfse
{
    public function consultar(): ConsultaBuilder;
}
