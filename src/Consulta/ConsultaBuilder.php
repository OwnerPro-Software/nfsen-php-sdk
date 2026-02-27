<?php

namespace Pulsar\NfseNacional\Consulta;

use Pulsar\NfseNacional\Contracts\NfseClientContract;
use Pulsar\NfseNacional\DTOs\DanfseResponse;
use Pulsar\NfseNacional\DTOs\EventosResponse;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Services\PrefeituraResolver;

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
        return $this->client->executeGet($this->buildUrl($this->seFinBaseUrl, $path));
    }

    public function danfse(string $chave): DanfseResponse
    {
        $baseUrl = $this->adnBaseUrl ?: $this->seFinBaseUrl;
        $path    = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_danfse', ['chave' => $chave]);

        $result = $this->client->executeGetRaw($this->buildUrl($baseUrl, $path));

        if (isset($result['erros']) || isset($result['erro'])) {
            $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
            return new DanfseResponse(false, null, $erro);
        }

        return new DanfseResponse(true, $result['danfseUrl'] ?? null, null);
    }

    public function eventos(string $chave, int $tipoEvento = 101101, int $nSequencial = 1): EventosResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_eventos', [
            'chave'       => $chave,
            'tipoEvento'  => $tipoEvento,
            'nSequencial' => $nSequencial,
        ]);

        $result = $this->client->executeGetRaw($this->buildUrl($this->seFinBaseUrl, $path));

        if (isset($result['erros']) || isset($result['erro'])) {
            $erro = $result['erros'][0]['descricao'] ?? $result['erro'] ?? 'Erro';
            return new EventosResponse(false, [], $erro);
        }

        return new EventosResponse(true, $result['eventos'] ?? [], null);
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return $baseUrl;
        }
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
