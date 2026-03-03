<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Ports\Driven;

use Pulsar\NfseNacional\Enums\NfseAmbiente;

interface ResolvesPrefeituras
{
    public function resolveSeFinUrl(string $codigoIbge, NfseAmbiente $ambiente): string;

    public function resolveAdnUrl(string $codigoIbge, NfseAmbiente $ambiente): string;

    /** @param array<string, int|string> $params */
    public function resolveOperation(string $codigoIbge, string $operacao, array $params = []): string;
}
