<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

interface QueriesNfse
{
    public function consultar(): ConsultsNfse;
}
