<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Responses\NfseResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
interface SubstitutesNfse
{
    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, ?string $descricao = null): NfseResponse;
}
