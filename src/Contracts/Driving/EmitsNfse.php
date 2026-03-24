<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Responses\NfseResponse;

/** @phpstan-import-type DpsDataArray from DpsData */
interface EmitsNfse
{
    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitir(DpsData|array $data): NfseResponse;

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse;
}
