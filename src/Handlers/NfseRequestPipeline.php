<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Handlers;

use Pulsar\NfseNacional\Certificates\CertificateManager;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Http\NfseHttpClient;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Signing\XmlSigner;
use Pulsar\NfseNacional\Support\GzipCompressor;

final readonly class NfseRequestPipeline
{
    public function __construct(
        private NfseAmbiente $ambiente,
        private string $signingAlgorithm,
        private PrefeituraResolver $prefeituraResolver,
        private GzipCompressor $gzipCompressor,
        private CertificateManager $certManager,
        private string $prefeitura,
        private NfseHttpClient $httpClient,
    ) {}

    /**
     * @param  array<string, string>  $operationParams
     * @return array<string, mixed>
     */
    public function signCompressSend(string $xml, string $signTagName, string $signRootName, string $payloadKey, string $operationKey, array $operationParams = []): array
    {
        $signer = new XmlSigner($this->certManager->getCertificate(), $this->signingAlgorithm);
        $signed = '<?xml version="1.0" encoding="UTF-8"?>'.$signer->sign($xml, $signTagName, $signRootName);
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
        $certificate = $this->certManager->getCertificate();
        $cnpj = $certificate->getCnpj() ?: null;
        $cpf = $certificate->getCpf() ?: null;

        if ($cnpj === null && $cpf === null) {
            throw new NfseException(sprintf('Certificado não contém CNPJ nem CPF. É necessário ao menos um para %s a NFS-e.', $operacao));
        }

        return ['cnpj' => $cnpj, 'cpf' => $cpf];
    }
}
