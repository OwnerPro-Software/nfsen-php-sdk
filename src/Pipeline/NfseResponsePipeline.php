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
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
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
             *     erro?: MessageData|list<MessageData>,
             *     nfseXmlGZipB64?: string,
             *     chaveAcesso?: string,
             *     tipoAmbiente?: int,
             *     versaoAplicativo?: string,
             *     dataHoraProcessamento?: string,
             * } $result
             */
            $result = $this->httpClient->get($url);

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
                    raw: $result,
                );
            }

            $nfseXml = $result['nfseXmlGZipB64'] ?? null;

            // NFSeGetResponseSucesso (SefinNacional-swagger.json) declara
            // nfseXmlGZipB64 required no 200: sem o XML — o único fruto da
            // consulta — não há sucesso a relatar. Mesma régua de eventos()
            // e dps(); só o 2xx chega aqui (get() lança nos demais casos).
            if (! is_string($nfseXml) || $nfseXml === '') {
                throw IndeterminateResultException::fromMissingQueryField('nfseXmlGZipB64', $result);
            }

            $xml = GzipCompressor::decompressB64($nfseXml);

            $this->dispatchEvent(new NfseQueried('consultar'));

            return new NfseResponse(
                sucesso: true,
                chave: $result['chaveAcesso'] ?? null,
                xml: $xml,
                tipoAmbiente: $result['tipoAmbiente'] ?? null,
                versaoAplicativo: $result['versaoAplicativo'] ?? null,
                dataHoraProcessamento: $result['dataHoraProcessamento'] ?? null,
                raw: $result,
            );
        });
    }

    /**
     * @param  list<string>  $requiredStrings
     * @param  list<string>  $requiredLists
     */
    public function executeRaw(string $url, array $requiredStrings = [], array $requiredLists = []): HttpResponse
    {
        $operacao = 'consultar';
        $this->dispatchEvent(new NfseRequested($operacao));

        return $this->withFailureEvent($operacao, function () use ($url, $operacao, $requiredStrings, $requiredLists): HttpResponse {
            $response = $this->httpClient->getResponse($url);

            /** @var array{erros?: list<MessageData>, erro?: MessageData|list<MessageData>} $result */
            $result = $response->json;

            $hasStructuredError = ProcessingMessage::hasApiError($result);

            if (! $hasStructuredError) {
                if (! in_array($response->statusCode, [200, 201, 404], true)) {
                    throw HttpException::fromResponse($response->statusCode, $response->body);
                }

                // 404 sem corpo de erro não é consulta bem-sucedida (o recurso
                // não existe) nem rejeição da SEFIN — nenhum evento de resultado.
                if ($response->statusCode === 404) {
                    return $response;
                }

                // 2xx sem nenhum dos campos exigidos pela operação: corpo legível
                // porém ininterpretável — indeterminado, nunca sucesso silencioso.
                // Lançado aqui (e não no chamador) para que NfseFailed seja
                // disparado no lugar de NfseQueried.
                $required = [...$requiredStrings, ...$requiredLists];

                if ($required !== [] && ! $this->hasAnyField($response->json, $requiredStrings, $requiredLists)) {
                    throw IndeterminateResultException::fromMissingResponseField(
                        $response->statusCode,
                        $response->body,
                        $response->json,
                        ...$required,
                    );
                }
            }

            $this->dispatchResultEvents($result, $operacao);

            return $response;
        });
    }

    /**
     * A rota GET de eventos entrega o resultado numa lista (`eventos`), e as demais
     * num campo de texto. Campo presente é o que veio preenchido **na forma que a
     * operação lê**: aceitar qualquer valor não-vazio deixaria o chamador entregar
     * uma lista a um construtor que declara `?string`.
     *
     * @param  array<string, mixed>  $json
     * @param  list<string>  $strings
     * @param  list<string>  $lists
     */
    private function hasAnyField(array $json, array $strings, array $lists): bool
    {
        foreach ($strings as $field) {
            $value = $json[$field] ?? null;

            if (is_string($value) && $value !== '') {
                return true;
            }
        }

        foreach ($lists as $field) {
            $value = $json[$field] ?? null;

            if (is_array($value) && $value !== []) {
                return true;
            }
        }

        return false;
    }

    /** @param array{erros?: list<MessageData>, erro?: MessageData|list<MessageData>} $result */
    private function dispatchResultEvents(array $result, string $operacao): void
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
