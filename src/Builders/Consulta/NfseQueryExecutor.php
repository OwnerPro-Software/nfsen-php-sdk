<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Builders\Consulta;

use Pulsar\NfseNacional\Contracts\Ports\Driven\SendsHttpRequests;
use Pulsar\NfseNacional\Contracts\Ports\Driving\ExecutesNfseRequests;
use Pulsar\NfseNacional\Responses\MensagemProcessamento;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Pipeline\Concerns\DispatchesEvents;
use Pulsar\NfseNacional\Support\GzipCompressor;

final readonly class NfseQueryExecutor implements ExecutesNfseRequests
{
    use DispatchesEvents;

    public function __construct(
        private SendsHttpRequests $httpClient,
    ) {}

    public function executeGet(string $url): NfseResponse
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        return $this->withFailureEvent($operacao, function () use ($url, $operacao): NfseResponse {
            /**
             * @var array{
             *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
             *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
             *     nfseXmlGZipB64?: string,
             *     chaveAcesso?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->httpClient->get($url);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $erros = MensagemProcessamento::fromApiResult($result);
                $this->dispatchEvent(new NfseRejected($operacao, $erros[0]->codigo ?? 'UNKNOWN'));

                return new NfseResponse(
                    sucesso: false,
                    erros: $erros,
                    tipoAmbiente: $result['tipoAmbiente'] ?? null,
                    versaoAplicativo: $result['versaoAplicativo'] ?? null,
                    dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
                );
            }

            $this->dispatchEvent(new NfseQueried('consultar'));

            return new NfseResponse(
                sucesso: true,
                chave: $result['chaveAcesso'] ?? null,
                xml: GzipCompressor::decompressB64($result['nfseXmlGZipB64'] ?? null),
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
            );
        });
    }

    /**
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
    public function executeGetRaw(string $url): array
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        return $this->withFailureEvent($operacao, function () use ($url, $operacao): array {
            /**
             * @var array{
             *     erros?: list<array{mensagem?: string, descricao?: string, codigo?: string, complemento?: string}>,
             *     erro?: array{mensagem?: string, codigo?: string, descricao?: string, complemento?: string},
             *     chaveAcesso?: string,
             *     idDps?: string,
             *     danfseUrl?: string,
             *     eventoXmlGZipB64?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->httpClient->get($url);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $this->dispatchEvent(new NfseRejected($operacao, $result['erros'][0]['codigo'] ?? $result['erro']['codigo'] ?? 'UNKNOWN'));
            } else {
                $this->dispatchEvent(new NfseQueried($operacao));
            }

            return $result;
        });
    }

    public function executeHead(string $url): int
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        return $this->withFailureEvent($operacao, function () use ($url, $operacao): int {
            $status = $this->httpClient->head($url);

            $this->dispatchEvent(new NfseQueried($operacao));

            return $status;
        });
    }
}
