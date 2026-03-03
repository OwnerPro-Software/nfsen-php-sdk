<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts;

use Pulsar\NfseNacional\Consulta\ConsultaBuilder;

interface QueriesNfse
{
    public function consultar(): ConsultaBuilder;
}
