<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Pipeline;

use Pulsar\NfseNacional\Contracts\Driven\SendsHttpRequests;
use Pulsar\NfseNacional\Contracts\Driving\ExecutesNfseRequests;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Pipeline\Concerns\DispatchesEvents;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Responses\ProcessingMessage;
use Pulsar\NfseNacional\Support\GzipCompressor;

final readonly class NfseResponsePipeline implements ExecutesNfseRequests
{
    use DispatchesEvents;

    public function __construct(
        private SendsHttpRequests $httpClient,
    ) {}

    public function executeAndDecompress(string $url): NfseResponse
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
                $erros = ProcessingMessage::fromApiResult($result);
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
     *     eventoXmlGZipB64?: string,
     *     tipoAmbiente?: int,
     *     versaoAplicativo?: string,
     *     dataHoraProcessamento?: string,
     * }
     */
    public function execute(string $url): array
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
             *     eventoXmlGZipB64?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->httpClient->get($url);

            if (! empty($result['erros']) || isset($result['erro'])) {
                $erros = ProcessingMessage::fromApiResult($result);
                $this->dispatchEvent(new NfseRejected($operacao, $erros[0]->codigo ?? 'UNKNOWN'));
            } else {
                $this->dispatchEvent(new NfseQueried($operacao));
            }

            return $result;
        });
    }

    public function executeAndDownload(string $url): string
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        return $this->withFailureEvent($operacao, function () use ($url, $operacao): string {
            $result = $this->httpClient->getBytes($url);
            $this->dispatchEvent(new NfseQueried($operacao));

            return $result;
        });
    }

    public function executeHead(string $url): int
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        return $this->withFailureEvent($operacao, function () use ($url, $operacao): int {
            $status = $this->httpClient->head($url);

            if ($status === 200) {
                $this->dispatchEvent(new NfseQueried($operacao));
            }

            return $status;
        });
    }
}
