<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

use OwnerPro\Nfsen\Enums\NfseAmbiente;

/**
 * @api
 */
interface ResolvesPrefeituras extends ResolvesOperations
{
    public function resolveSeFinUrl(string $codigoIbge, NfseAmbiente $ambiente): string;

    public function resolveAdnUrl(string $codigoIbge, NfseAmbiente $ambiente): string;
}
