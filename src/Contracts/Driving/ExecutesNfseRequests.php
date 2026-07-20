<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Responses\HttpResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;

/** @phpstan-import-type MessageData from ProcessingMessage */
interface ExecutesNfseRequests
{
    public function executeAndDecompress(string $url): NfseResponse;

    /**
     * Retorna apenas 200 ou 404. Qualquer outro status lança HttpException
     * (dentro do pipeline de eventos, disparando NfseFailed); falha de
     * transporte lança IndeterminateResultException.
     */
    public function executeHead(string $url): int;

    /**
     * Retorna a resposta HTTP crua (status + JSON + corpo).
     *
     * Lança HttpException quando o servidor responde status inesperado
     * (diferente de 200/201/404) sem corpo de erro estruturado (`erros`/`erro`).
     */
    public function executeRaw(string $url): HttpResponse;

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
