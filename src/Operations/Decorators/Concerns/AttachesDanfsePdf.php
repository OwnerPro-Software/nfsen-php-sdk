<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations\Decorators\Concerns;

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * Anexa PDF e erros de render a um NfseResponse.
 *
 * Classes hospedeiras devem implementar `renderer()` retornando seu `RendersDanfse`.
 */
trait AttachesDanfsePdf
{
    abstract private function renderer(): RendersDanfse;

    private function attachPdf(NfseResponse $r): NfseResponse
    {
        if (! $r->sucesso || $r->xml === null) {
            return $r;
        }

        $danfse = $this->renderer()->toPdf($r->xml);

        return new NfseResponse(
            sucesso: $r->sucesso,
            chave: $r->chave,
            xml: $r->xml,
            idDps: $r->idDps,
            alertas: $r->alertas,
            erros: $r->erros,
            tipoAmbiente: $r->tipoAmbiente,
            versaoAplicativo: $r->versaoAplicativo,
            dataHoraProcessamento: $r->dataHoraProcessamento,
            pdf: $danfse->pdf,
            pdfErrors: $danfse->erros,
        );
    }
}
