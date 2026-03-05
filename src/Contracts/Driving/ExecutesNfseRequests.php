<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Responses\ProcessingMessage;

/** @phpstan-import-type MessageData from ProcessingMessage */
interface ExecutesNfseRequests
{
    public function executeAndDecompress(string $url): NfseResponse;

    public function executeHead(string $url): int;

    /**
     * Retorna JSON cru da API.
     *
     * @return array{
     *     erros?: list<MessageData>,
     *     erro?: MessageData,
     *     chaveAcesso?: string,
     *     idDps?: string,
     *     eventoXmlGZipB64?: string,
     *     tipoAmbiente?: int,
     *     versaoAplicativo?: string,
     *     dataHoraProcessamento?: string,
     * }
     */
    public function execute(string $url): array;

    public function executeAndDownload(string $url): string;
}
