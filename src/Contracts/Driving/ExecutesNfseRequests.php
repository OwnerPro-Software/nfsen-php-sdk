<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Responses\NfseResponse;

interface ExecutesNfseRequests
{
    public function executeAndDecompress(string $url): NfseResponse;

    public function executeHead(string $url): int;

    /**
     * Retorna JSON cru da API.
     *
     * @return array{
     *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
     *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
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
