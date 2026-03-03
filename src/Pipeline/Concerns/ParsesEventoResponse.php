<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Pipeline\Concerns;

use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Responses\MensagemProcessamento;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Support\GzipCompressor;

/**
 * Shared evento response parsing for cancelar/substituir operations.
 *
 * @requires DispatchesEvents
 */
trait ParsesEventoResponse
{
    /**
     * @param  array{
     *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
     *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
     *     eventoXmlGZipB64?: string,
     *     tipoAmbiente?: int,
     *     versaoAplicativo?: string,
     *     dataHoraProcessamento?: string,
     * }  $result
     */
    private function parseEventoResponse(array $result, string $chave, string $operacao, object $successEvent): NfseResponse
    {
        if (! empty($result['erros']) || isset($result['erro'])) {
            $erros = MensagemProcessamento::fromApiResult($result);
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
