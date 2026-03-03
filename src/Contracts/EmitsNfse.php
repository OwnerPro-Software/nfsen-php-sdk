<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts;

use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\DTOs\NfseResponse;

/** @phpstan-import-type DpsDataArray from DpsData */
interface EmitsNfse
{
    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitir(DpsData|array $data): NfseResponse;

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse;
}
