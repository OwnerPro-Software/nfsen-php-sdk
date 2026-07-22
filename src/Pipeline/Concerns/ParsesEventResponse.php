<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Pipeline\Concerns;

use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
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
        if (ProcessingMessage::hasApiError($result)) {
            $erros = ProcessingMessage::fromApiResult($result);
            $firstError = $erros[0] ?? null;
            $this->dispatchEvent(new NfseRejected(
                $operacao,
                $firstError->codigo ?? 'UNKNOWN',
                $firstError->descricao ?? $firstError->mensagem ?? null,
                $firstError->complemento ?? null,
            ));

            return new NfseResponse(
                sucesso: false,
                erros: $erros,
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
            );
        }

        $eventoXml = $result['eventoXmlGZipB64'] ?? null;

        // EventosPostResponseSucesso (SefinNacional-swagger.json) declara
        // eventoXmlGZipB64 obrigatório: sem rejeição estruturada E sem o
        // recibo, nada prova que o evento foi registrado — indeterminado,
        // nunca sucesso silencioso (mesma régua de consultar()->eventos()).
        // Chega aqui tanto o 2xx fora do contrato quanto o 4xx de proxy/WAF
        // com JSON próprio que request() devolve para o resgate SEM_CHAVE
        // do emitter — resgate que evento nenhum tem.
        if (! is_string($eventoXml) || $eventoXml === '') {
            throw IndeterminateResultException::fromMissingEventReceipt('eventoXmlGZipB64');
        }

        $this->dispatchEvent($successEvent);

        return new NfseResponse(
            sucesso: true,
            chave: $chave,
            xml: GzipCompressor::decompressB64($eventoXml),
            tipoAmbiente: $result['tipoAmbiente'] ?? null,
            versaoAplicativo: $result['versaoAplicativo'] ?? null,
            dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
        );
    }
}
