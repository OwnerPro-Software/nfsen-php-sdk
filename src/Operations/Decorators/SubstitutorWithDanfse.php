<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations\Decorators;

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Contracts\Driving\SubstitutesNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\CodigoJustificativaSubstituicao;
use OwnerPro\Nfsen\Operations\Decorators\Concerns\AttachesDanfsePdf;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * @phpstan-import-type DpsDataArray from DpsData
 */
final readonly class SubstitutorWithDanfse implements SubstitutesNfse
{
    use AttachesDanfsePdf;

    public function __construct(
        private SubstitutesNfse $inner,
        private RendersDanfse $renderer,
    ) {}

    /** @phpstan-param DpsData|DpsDataArray $dps */
    public function substituir(
        string $chave,
        DpsData|array $dps,
        CodigoJustificativaSubstituicao|string $codigoMotivo,
        ?string $descricao = null,
    ): NfseResponse {
        return $this->attachPdf($this->inner->substituir($chave, $dps, $codigoMotivo, $descricao));
    }

    private function renderer(): RendersDanfse
    {
        return $this->renderer;
    }
}
