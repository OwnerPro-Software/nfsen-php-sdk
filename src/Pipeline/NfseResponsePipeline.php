<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Pipeline;

use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Contracts\Driven\SendsRawHttpRequests;
use OwnerPro\Nfsen\Contracts\Driving\ExecutesNfseRequests;
use OwnerPro\Nfsen\Events\NfseQueried;
use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Pipeline\Concerns\DispatchesEvents;
use OwnerPro\Nfsen\Responses\HttpResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;
use OwnerPro\Nfsen\Support\GzipCompressor;

/** @phpstan-import-type MessageData from ProcessingMessage */
final readonly class NfseResponsePipeline implements ExecutesNfseRequests
{
    use DispatchesEvents;

    public function __construct(
        private SendsHttpRequests&SendsRawHttpRequests $httpClient,
    ) {}

    public function executeAndDecompress(string $url): NfseResponse
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        return $this->withFailureEvent($operacao, function () use ($url, $operacao): NfseResponse {
            /**
             * @var array{
             *     erros?: list<MessageData>,
             *     erro?: MessageData,
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
    public function execute(string $url): array
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        return $this->withFailureEvent($operacao, function () use ($url, $operacao): array {
            /**
             * @var array{
             *     erros?: list<MessageData>,
             *     erro?: MessageData,
             *     chaveAcesso?: string,
             *     idDps?: string,
             *     eventoXmlGZipB64?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->httpClient->get($url);

            $this->dispatchResultEvents($result, $operacao);

            return $result;
        });
    }

    public function executeRaw(string $url): HttpResponse
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        return $this->withFailureEvent($operacao, function () use ($url, $operacao): HttpResponse {
            $response = $this->httpClient->getResponse($url);

            /** @var array{erros?: list<MessageData>, erro?: MessageData} $result */
            $result = $response->json;

            $hasStructuredError = ! empty($result['erros']) || isset($result['erro']);

            if (! $hasStructuredError && ! in_array($response->statusCode, [200, 201, 404], true)) {
                throw HttpException::fromResponse($response->statusCode, $response->body);
            }

            $this->dispatchResultEvents($result, $operacao);

            return $response;
        });
    }

    /** @param array{erros?: list<MessageData>, erro?: MessageData} $result */
    private function dispatchResultEvents(array $result, string $operacao): void
    {
        if (! empty($result['erros']) || isset($result['erro'])) {
            $erros = ProcessingMessage::fromApiResult($result);
            $firstError = $erros[0] ?? null;
            $this->dispatchEvent(new NfseRejected(
                $operacao,
                $firstError->codigo ?? 'UNKNOWN',
                $firstError->descricao ?? $firstError->mensagem ?? null,
                $firstError->complemento ?? null,
            ));

            return;
        }

        $this->dispatchEvent(new NfseQueried($operacao));
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

                return $status;
            }

            if ($status === 404) {
                return $status;
            }

            throw HttpException::fromResponse($status, '');
        });
    }
}
