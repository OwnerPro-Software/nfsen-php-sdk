<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Responses\NfseResponse;

/** @phpstan-import-type DpsDataArray from DpsData */
interface EmitsNfse
{
    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitir(DpsData|array $data): NfseResponse;

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse;
}
