<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Consulta;

use InvalidArgumentException;
use Pulsar\NfseNacional\Contracts\Ports\Driven\ResolvesPrefeituras;
use Pulsar\NfseNacional\Contracts\Ports\Driving\ExecutesNfseRequests;
use Pulsar\NfseNacional\DTOs\DanfseResponse;
use Pulsar\NfseNacional\DTOs\EventosResponse;
use Pulsar\NfseNacional\DTOs\MensagemProcessamento;
use Pulsar\NfseNacional\DTOs\NfseResponse;
use Pulsar\NfseNacional\Enums\TipoEvento;
use Pulsar\NfseNacional\Support\GzipCompressor;

final readonly class ConsultaBuilder
{
    public function __construct(
        private ExecutesNfseRequests $client,
        private string $seFinBaseUrl,
        private string $adnBaseUrl,
        private ResolvesPrefeituras $resolver,
        private string $codigoIbge,
    ) {}

    public function nfse(string $chave): NfseResponse
    {
        $this->validateChaveAcesso($chave);
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_nfse', ['chave' => $chave]);

        return $this->client->executeGet($this->buildUrl($this->seFinBaseUrl, $path));
    }

    public function dps(string $id): NfseResponse
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_dps', ['id' => $id]);
        $result = $this->client->executeGetRaw($this->buildUrl($this->seFinBaseUrl, $path));

        $tipoAmbiente = $result['tipoAmbiente'] ?? null;
        $versaoAplicativo = $result['versaoAplicativo'] ?? null;
        $dataHoraProcessamento = $result['dataHoraProcessamento'] ?? null;

        if (! empty($result['erros']) || isset($result['erro'])) {
            return new NfseResponse(
                sucesso: false,
                erros: MensagemProcessamento::fromApiResult($result),
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
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_danfse', ['chave' => $chave]);

        $result = $this->client->executeGetRaw($this->buildUrl($baseUrl, $path));

        $tipoAmbiente = $result['tipoAmbiente'] ?? null;
        $versaoAplicativo = $result['versaoAplicativo'] ?? null;
        $dataHoraProcessamento = $result['dataHoraProcessamento'] ?? null;

        if (! empty($result['erros']) || isset($result['erro'])) {
            return new DanfseResponse(
                sucesso: false,
                erros: MensagemProcessamento::fromApiResult($result),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
            );
        }

        return new DanfseResponse(
            sucesso: true,
            url: $result['danfseUrl'] ?? null,
            tipoAmbiente: $tipoAmbiente,
            versaoAplicativo: $versaoAplicativo,
            dataHoraProcessamento: $dataHoraProcessamento,
        );
    }

    public function eventos(string $chave, TipoEvento|int $tipoEvento = TipoEvento::CancelamentoPorIniciativaPrestador, int $nSequencial = 1): EventosResponse
    {
        $this->validateChaveAcesso($chave);

        if (is_int($tipoEvento)) {
            $tipoEvento = TipoEvento::from($tipoEvento);
        }

        $path = $this->resolver->resolveOperation($this->codigoIbge, 'consultar_eventos', [
            'chave' => $chave,
            'tipoEvento' => $tipoEvento->value,
            'nSequencial' => $nSequencial,
        ]);

        $result = $this->client->executeGetRaw($this->buildUrl($this->seFinBaseUrl, $path));

        $tipoAmbiente = $result['tipoAmbiente'] ?? null;
        $versaoAplicativo = $result['versaoAplicativo'] ?? null;
        $dataHoraProcessamento = $result['dataHoraProcessamento'] ?? null;

        if (! empty($result['erros']) || isset($result['erro'])) {
            return new EventosResponse(
                sucesso: false,
                erros: MensagemProcessamento::fromApiResult($result),
                tipoAmbiente: $tipoAmbiente,
                versaoAplicativo: $versaoAplicativo,
                dataHoraProcessamento: $dataHoraProcessamento,
            );
        }

        return new EventosResponse(
            sucesso: true,
            xml: GzipCompressor::decompressB64($result['eventoXmlGZipB64'] ?? null),
            tipoAmbiente: $tipoAmbiente,
            versaoAplicativo: $versaoAplicativo,
            dataHoraProcessamento: $dataHoraProcessamento,
        );
    }

    public function verificarDps(string $id): bool
    {
        $path = $this->resolver->resolveOperation($this->codigoIbge, 'verificar_dps', ['id' => $id]);
        $status = $this->client->executeHead($this->buildUrl($this->seFinBaseUrl, $path));

        return $status === 200;
    }

    private function validateChaveAcesso(string $chave): void
    {
        if (! preg_match('/^\d{50}$/', $chave)) {
            throw new InvalidArgumentException(sprintf("chaveAcesso inválida: '%s'. Esperado: exatamente 50 dígitos numéricos.", $chave));
        }
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
