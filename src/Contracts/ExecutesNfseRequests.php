<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts;

use Pulsar\NfseNacional\DTOs\NfseResponse;

interface ExecutesNfseRequests
{
    public function executeGet(string $url): NfseResponse;

    public function executeHead(string $url): int;

    /**
     * Retorna JSON cru da API.
     *
     * @return array{
     *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
     *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
     *     chaveAcesso?: string,
     *     idDps?: string,
     *     danfseUrl?: string,
     *     eventoXmlGZipB64?: string,
     *     tipoAmbiente?: int,
     *     versaoAplicativo?: string,
     *     dataHoraProcessamento?: string,
     * }
     */
    public function executeGetRaw(string $url): array;
}
