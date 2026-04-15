<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations\Decorators\Concerns;

use OwnerPro\Nfsen\Contracts\Driving\RendersDanfse;
use OwnerPro\Nfsen\Responses\NfseResponse;

/**
 * Anexa PDF e erros de render a um NfseResponse.
 *
 * Classes hospedeiras devem declarar `private readonly RendersDanfse $renderer`.
 *
 * Idempotente: se o response recebido já tem `pdf !== null`, retorna como está.
 * Defende a wiring de `NfsenClient::forStandalone` contra double-render caso alguém
 * acidentalmente decore o emitter interno do `NfseSubstitutor`.
 *
 * @property-read RendersDanfse $renderer
 */
trait AttachesDanfsePdf
{
    private function attachPdf(NfseResponse $r): NfseResponse
    {
        if (! $r->sucesso || $r->xml === null || $r->pdf !== null) {
            return $r;
        }

        $danfse = $this->renderer->toPdf($r->xml);

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
