<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Operations\NfseConsulter;

interface QueriesNfse
{
    public function consultar(): NfseConsulter;
}
