<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Responses\NfseResponse;

/** @phpstan-import-type DpsDataArray from DpsData */
interface EmitsNfse
{
    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitir(DpsData|array $data): NfseResponse;

    /**
     * Não suportado: o endpoint `decisao-judicial/nfse` recebe o documento NFS-e
     * completo, gerado por quem detém a decisão judicial — não uma DPS. A
     * implementação lança {@see NfseException}.
     *
     * @phpstan-param DpsData|DpsDataArray $data
     */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse;
}
