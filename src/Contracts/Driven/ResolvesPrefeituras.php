<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driven;

use Pulsar\NfseNacional\Enums\NfseAmbiente;

interface ResolvesPrefeituras extends ResolvesOperations
{
    public function resolveSeFinUrl(string $codigoIbge, NfseAmbiente $ambiente): string;

    public function resolveAdnUrl(string $codigoIbge, NfseAmbiente $ambiente): string;
}
