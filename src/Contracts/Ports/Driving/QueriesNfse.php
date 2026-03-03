<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Ports\Driving;

use Pulsar\NfseNacional\Operations\ConsultaBuilder;

interface QueriesNfse
{
    public function consultar(): ConsultaBuilder;
}
