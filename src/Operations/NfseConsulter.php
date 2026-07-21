<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Operations;

use OwnerPro\Nfsen\Contracts\Driven\ResolvesOperations;
use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Contracts\Driving\ExecutesNfseRequests;
use OwnerPro\Nfsen\Enums\TipoEvento;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Pipeline\Concerns\ValidatesChaveAcesso;
use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\EventsResponse;
use OwnerPro\Nfsen\Responses\NfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;
use OwnerPro\Nfsen\Support\GzipCompressor;

/**
 * @internal
 *
 * @phpstan-import-type MessageData from ProcessingMessage
 */
final readonly class NfseConsulter implements ConsultsNfse
{
    use ValidatesChaveAcesso;

    public function __construct(
        private ExecutesNfseRequests $client,
        private string $seFinBaseUrl,
        private string $adnBaseUrl,
        private ResolvesOperations $resolver,
        private string $codigoIbge,
    ) {}

    public function nfse(string $chave): NfseResponse
    {
        $this->validateChaveAcesso($chave);
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'query_nfse', ['chave' => $chave]);

        return $this->client->executeAndDecompress($this->buildUrl($this->seFinBaseUrl, $path));
    }

    public function dps(string $id): NfseResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'query_dps', ['id' => $id]);
        $response = $this->client->executeRaw($this->buildUrl($this->seFinBaseUrl, $path));

        /**
         * @var array{
         *     erros?: list<MessageData>,
         *     erro?: MessageData,
         *     chaveAcesso?: string,
         *     idDps?: string,
         *     tipoAmbiente?: int,
         *     versaoAplicativo?: string,
         *     dataHoraProcessamento?: string,
         * } $result
         */
        $result = $response->json;

        $tipoAmbiente = $result['tipoAmbiente'] ?? null;
        $versaoAplicativo = $result['versaoAplicativo'] ?? null;
        $dataHoraProcessamento = $result['dataHoraProcessamento'] ?? null;

        if ($response->statusCode === 404) {
            return new NfseResponse(
                sucesso: false,
                erros: $this->notFoundErrors(
                    new ProcessingMessage(
                        mensagem: 'DPS não encontrada',
                        codigo: NfseResponse::DPS_NOT_FOUND,
                        descricao: 'A SEFIN respondeu 404: não existe DPS com o identificador informado.',
                    ),
                    $result,
                ),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
            );
        }

        if (! empty($result['erros']) || isset($result['erro'])) {
            return new NfseResponse(
                sucesso: false,
                erros: ProcessingMessage::fromApiResult($result),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
            );
        }

        return new NfseResponse(
            sucesso: true,
            chave: $result['chaveAcesso'] ?? null,
            idDps: $result['idDps'] ?? null,
            tipoAmbiente: $tipoAmbiente,
            versaoAplicativo: $versaoAplicativo,
            dataHoraProcessamento: $dataHoraProcessamento,
        );
    }

    public function danfse(string $chave): DanfseResponse
    {
        $this->validateChaveAcesso($chave);
        $baseUrl = $this->adnBaseUrl ?: $this->seFinBaseUrl;
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'query_danfse', ['chave' => $chave]);

        try {
            $pdf = $this->client->executeAndDownload($this->buildUrl($baseUrl, $path));

            if ($pdf === '') {
                return new DanfseResponse(
                    sucesso: false,
                    erros: [new ProcessingMessage(
                        mensagem: 'Resposta vazia',
                        codigo: 'EMPTY_RESPONSE',
                        descricao: 'O servidor retornou uma resposta vazia para o DANFSe.',
                    )],
                );
            }

            return new DanfseResponse(sucesso: true, pdf: $pdf);
        } catch (HttpException $httpException) {
            return new DanfseResponse(
                sucesso: false,
                erros: $this->parseHttpError($httpException),
            );
        }
    }

    public function eventos(string $chave, TipoEvento|int $tipoEvento = TipoEvento::Cancelamento, int $nSequencial = 1): EventsResponse
    {
        $this->validateChaveAcesso($chave);

        if (is_int($tipoEvento)) {
            $tipoEvento = TipoEvento::from($tipoEvento);
        }

        $path = $this->resolver->resolveOperation($this->codigoIbge, 'query_events', [
            'chave' => $chave,
            'tipoEvento' => $tipoEvento->value,
            'nSequencial' => $nSequencial,
        ]);

        $response = $this->client->executeRaw($this->buildUrl($this->seFinBaseUrl, $path), 'eventoXmlGZipB64');

        /**
         * @var array{
         *     erros?: list<MessageData>,
         *     erro?: MessageData,
         *     eventoXmlGZipB64?: string,
         *     tipoAmbiente?: int,
         *     versaoAplicativo?: string,
         *     dataHoraProcessamento?: string,
         * } $result
         */
        $result = $response->json;

        $tipoAmbiente = $result['tipoAmbiente'] ?? null;
        $versaoAplicativo = $result['versaoAplicativo'] ?? null;
        $dataHoraProcessamento = $result['dataHoraProcessamento'] ?? null;

        if ($response->statusCode === 404) {
            return new EventsResponse(
                sucesso: false,
                erros: $this->notFoundErrors(
                    new ProcessingMessage(
                        mensagem: 'Evento não encontrado',
                        codigo: EventsResponse::EVENT_NOT_FOUND,
                        descricao: 'A SEFIN respondeu 404: não existe evento com os parâmetros informados.',
                    ),
                    $result,
                ),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
            );
        }

        if (! empty($result['erros']) || isset($result['erro'])) {
            return new EventsResponse(
                sucesso: false,
                erros: ProcessingMessage::fromApiResult($result),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
            );
        }

        return new EventsResponse(
            sucesso: true,
            // executeRaw('eventoXmlGZipB64') garante string não-vazia neste ponto.
            xml: GzipCompressor::decompressB64($result['eventoXmlGZipB64'] ?? null),
            tipoAmbiente: $tipoAmbiente,
            versaoAplicativo: $versaoAplicativo,
            dataHoraProcessamento: $dataHoraProcessamento,
        );
    }

    public function verificarDps(string $id): bool
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'verify_dps', ['id' => $id]);

        // executeHead só retorna 200 ou 404; qualquer outro status lança lá.
        return $this->client->executeHead($this->buildUrl($this->seFinBaseUrl, $path)) === 200;
    }

    /**
     * Erro dedicado de ausência comprovada (HTTP 404) seguido dos erros
     * originais da SEFIN, quando o corpo do 404 os traz.
     *
     * @param  array{erros?: list<MessageData>, erro?: MessageData}  $result
     * @return list<ProcessingMessage>
     */
    private function notFoundErrors(ProcessingMessage $marker, array $result): array
    {
        return [$marker, ...ProcessingMessage::fromApiResult($result)];
    }

    /** @return list<ProcessingMessage> */
    private function parseHttpError(HttpException $e): array
    {
        $body = $e->getResponseBody();

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);

        if (is_array($decoded) && (! empty($decoded['erros']) || isset($decoded['erro']))) {
            return ProcessingMessage::fromApiResult($decoded); // @phpstan-ignore argument.type (validated by condition above)
        }

        return [new ProcessingMessage(
            mensagem: 'HTTP error: '.$e->getCode(),
            codigo: (string) $e->getCode(),
            descricao: $body,
        )];
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
