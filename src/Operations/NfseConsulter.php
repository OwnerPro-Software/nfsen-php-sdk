<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Operations;

use Pulsar\NfseNacional\Contracts\Driven\ResolvesOperations;
use Pulsar\NfseNacional\Contracts\Driving\ConsultsNfse;
use Pulsar\NfseNacional\Contracts\Driving\ExecutesNfseRequests;
use Pulsar\NfseNacional\Enums\TipoEvento;
use Pulsar\NfseNacional\Exceptions\HttpException;
use Pulsar\NfseNacional\Pipeline\Concerns\ValidatesChaveAcesso;
use Pulsar\NfseNacional\Responses\DanfseResponse;
use Pulsar\NfseNacional\Responses\EventsResponse;
use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Responses\ProcessingMessage;
use Pulsar\NfseNacional\Support\GzipCompressor;

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
        $result = $this->client->execute($this->buildUrl($this->seFinBaseUrl, $path));

        $tipoAmbiente = $result['tipoAmbiente'] ?? null;
        $versaoAplicativo = $result['versaoAplicativo'] ?? null;
        $dataHoraProcessamento = $result['dataHoraProcessamento'] ?? null;

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
        } catch (HttpException $e) {
            return new DanfseResponse(
                sucesso: false,
                erros: self::parseHttpError($e),
            );
        }
    }

    public function eventos(string $chave, TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador, int $nSequencial = 1): EventsResponse
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

        $result = $this->client->execute($this->buildUrl($this->seFinBaseUrl, $path));

        $tipoAmbiente = $result['tipoAmbiente'] ?? null;
        $versaoAplicativo = $result['versaoAplicativo'] ?? null;
        $dataHoraProcessamento = $result['dataHoraProcessamento'] ?? null;

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
            xml: GzipCompressor::decompressB64($result['eventoXmlGZipB64'] ?? null),
            tipoAmbiente: $tipoAmbiente,
            versaoAplicativo: $versaoAplicativo,
            dataHoraProcessamento: $dataHoraProcessamento,
        );
    }

    public function verificarDps(string $id): bool
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'verify_dps', ['id' => $id]);
        $status = $this->client->executeHead($this->buildUrl($this->seFinBaseUrl, $path));

        return $status === 200;
    }

    /** @return list<ProcessingMessage> */
    private static function parseHttpError(HttpException $e): array
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
