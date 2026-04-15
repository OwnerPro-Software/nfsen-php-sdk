<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations\Decorators;

use OwnerPro\Nfsen\Contracts\Driving\EmitsNfse;
use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Operations\Decorators\Concerns\AttachesDanfsePdf;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
final readonly class EmitterWithDanfse implements EmitsNfse
{
    use AttachesDanfsePdf;

    public function __construct(
        private EmitsNfse $inner,
        private RendersDanfse $renderer,
    ) {}

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitir(DpsData|array $data): NfseResponse
    {
        return $this->attachPdf($this->inner->emitir($data));
    }

    /** @phpstan-param DpsData|DpsDataArray $data */
    public function emitirDecisaoJudicial(DpsData|array $data): NfseResponse
    {
        return $this->attachPdf($this->inner->emitirDecisaoJudicial($data));
    }
}
