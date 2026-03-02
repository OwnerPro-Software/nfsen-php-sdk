<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Consulta;

use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\DanfseResponse;
use Pulsar\NfseNacional\DTOs\EventosResponse;
use Pulsar\NfseNacional\DTOs\MensagemProcessamento;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Support\GzipCompressor;

final readonly class ConsultaBuilder
{
    public function __construct(
        private NfseClientContract $client,
        private string $seFinBaseUrl,
        private string $adnBaseUrl,
        private PrefeituraResolver $resolver,
        private string $codigoIbge,
    ) {}

    public function nfse(string $chave): NfseResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_nfse', ['chave' => $chave]);

        return $this->client->executeGet($this->buildUrl($this->seFinBaseUrl, $path));
    }

    public function dps(string $chave): NfseResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_dps', ['chave' => $chave]);
        $result = $this->client->executeGetRaw($this->buildUrl($this->seFinBaseUrl, $path));

        if (! empty($result['erros']) || isset($result['erro'])) {
            return new NfseResponse(
                sucesso: false,
                erros: MensagemProcessamento::fromApiResult($result),
            );
        }

        return new NfseResponse(
            sucesso: true,
            chave: $result['chaveAcesso'] ?? null,
            idDps: $result['idDps'] ?? null,
        );
    }

    public function danfse(string $chave): DanfseResponse
    {
        $baseUrl = $this->adnBaseUrl ?: $this->seFinBaseUrl;
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_danfse', ['chave' => $chave]);

        $result = $this->client->executeGetRaw($this->buildUrl($baseUrl, $path));

        if (! empty($result['erros']) || isset($result['erro'])) {
            return new DanfseResponse(
                sucesso: false,
                erros: MensagemProcessamento::fromApiResult($result),
            );
        }

        return new DanfseResponse(sucesso: true, url: $result['danfseUrl'] ?? null);
    }

    public function eventos(string $chave, int $tipoEvento = 101101, int $nSequencial = 1): EventosResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_eventos', [
            'chave' => $chave,
            'tipoEvento' => $tipoEvento,
            'nSequencial' => $nSequencial,
        ]);

        $result = $this->client->executeGetRaw($this->buildUrl($this->seFinBaseUrl, $path));

        if (! empty($result['erros']) || isset($result['erro'])) {
            return new EventosResponse(
                sucesso: false,
                erros: MensagemProcessamento::fromApiResult($result),
            );
        }

        return new EventosResponse(
            sucesso: true,
            xml: GzipCompressor::decompressB64($result['eventoXmlGZipB64'] ?? null),
        );
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
