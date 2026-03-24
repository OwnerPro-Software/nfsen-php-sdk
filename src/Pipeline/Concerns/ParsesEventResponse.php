<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Pipeline\Concerns;

use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;
use OwnerPro\Nfsen\Support\GzipCompressor;

/**
 * Shared event response parsing for cancelar/substituir operations.
 *
 * @requires DispatchesEvents
 *
 * @phpstan-import-type MessageData from ProcessingMessage
 */
trait ParsesEventResponse
{
    /**
     * @phpstan-param  array{
     *     erros?: list<MessageData>,
     *     erro?: MessageData,
     *     eventoXmlGZipB64?: string,
     *     tipoAmbiente?: int,
     *     versaoAplicativo?: string,
     *     dataHoraProcessamento?: string,
     * }  $result
     */
    private function parseEventResponse(array $result, string $chave, string $operacao, object $successEvent): NfseResponse
    {
        if (! empty($result['erros']) || isset($result['erro'])) {
            $erros = ProcessingMessage::fromApiResult($result);
            $codigo = $erros[0]->codigo ?? 'UNKNOWN';
            $this->dispatchEvent(new NfseRejected($operacao, $codigo));

            return new NfseResponse(
                sucesso: false,
                erros: $erros,
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
            );
        }

        $this->dispatchEvent($successEvent);

        return new NfseResponse(
            sucesso: true,
            chave: $chave,
            xml: GzipCompressor::decompressB64($result['eventoXmlGZipB64'] ?? null),
            tipoAmbiente: $result['tipoAmbiente'] ?? null,
            versaoAplicativo: $result['versaoAplicativo'] ?? null,
            dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
        );
    }
}
