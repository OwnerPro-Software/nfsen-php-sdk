<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
interface SubstitutesNfse
{
    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(string $chave, DpsData|array $dps, CodigoJustificativaSubstituicao|string $codigoMotivo, ?string $descricao = null): NfseResponse;
}
