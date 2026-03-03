<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Pipeline;

use Pulsar\NfseNacional\Contracts\Ports\Driven\ExtractsAuthorIdentity;
use Pulsar\NfseNacional\Contracts\Ports\Driven\ResolvesPrefeituras;
use Pulsar\NfseNacional\Contracts\Ports\Driven\SendsHttpRequests;
use Pulsar\NfseNacional\Contracts\Ports\Driven\SignsXml;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Support\GzipCompressor;

final readonly class NfseRequestPipeline
{
    public function __construct(
        private NfseAmbiente $ambiente,
        private ResolvesPrefeituras $prefeituraResolver,
        private GzipCompressor $gzipCompressor,
        private SignsXml $signer,
        private ExtractsAuthorIdentity $authorIdentity,
        private string $prefeitura,
        private SendsHttpRequests $httpClient,
    ) {}

    /**
     * @param  array<string, string>  $operationParams
     * @return array<string, mixed>
     */
    public function signCompressSend(string $xml, string $signTagName, string $signRootName, string $payloadKey, string $operationKey, array $operationParams = []): array
    {
        $signed = '<?xml version="1.0" encoding="UTF-8"?>'.$this->signer->sign($xml, $signTagName, $signRootName);
        $compressed = ($this->gzipCompressor)($signed);
        if ($compressed === false) {
            throw new NfseException('Falha ao comprimir XML.');
        }

        $payload = [$payloadKey => base64_encode($compressed)];

        $seFinUrl = $this->prefeituraResolver->resolveSeFinUrl($this->prefeitura, $this->ambiente);
        $opPath = $this->prefeituraResolver->resolveOperation($this->prefeitura, $operationKey, $operationParams);
        $url = $opPath !== '' ? rtrim($seFinUrl, '/').'/'.ltrim($opPath, '/') : $seFinUrl;

        /** @var array<string, mixed> */
        return $this->httpClient->post($url, $payload);
    }

    /**
     * @return array{cnpj: ?string, cpf: ?string}
     */
    public function extractAuthorIdentity(string $operacao): array
    {
        $identity = $this->authorIdentity->extract();

        if ($identity['cnpj'] === null && $identity['cpf'] === null) {
            throw new NfseException(sprintf('Certificado não contém CNPJ nem CPF. É necessário ao menos um para %s a NFS-e.', $operacao));
        }

        return $identity;
    }
}
