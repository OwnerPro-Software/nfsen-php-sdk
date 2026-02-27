<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts;

use Pulsar\NfseNacional\DTOs\NfseResponse;

interface NfseClientContract
{
    public function executeGet(string $url): NfseResponse;

    /** @return array<string, mixed> Retorna JSON cru da API */
    public function executeGetRaw(string $url): array;
}
