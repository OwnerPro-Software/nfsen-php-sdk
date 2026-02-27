<?php

namespace Pulsar\NfseNacional\Contracts;

use Pulsar\NfseNacional\DTOs\NfseResponse;

interface NfseClientContract
{
    public function executeGet(string $url): NfseResponse;

    /** Retorna JSON cru da API */
    public function executeGetRaw(string $url): array;
}
