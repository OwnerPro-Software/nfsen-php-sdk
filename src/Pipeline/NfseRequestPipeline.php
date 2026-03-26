<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Pipeline;

use OwnerPro\Nfsen\Contracts\Driven\ExtractsAuthorIdentity;
use OwnerPro\Nfsen\Contracts\Driven\ResolvesPrefeituras;
use OwnerPro\Nfsen\Contracts\Driven\SendsHttpRequests;
use OwnerPro\Nfsen\Contracts\Driven\SignsXml;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Support\GzipCompressor;

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
        private bool $validateIdentity = true,
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

    public function validateIdentityAgainst(DpsData $data): void
    {
        if (! $this->validateIdentity) {
            return;
        }

        $identity = $this->authorIdentity->extract();
        $certCnpj = $identity['cnpj'];
        $certCpf = $identity['cpf'];
        $prestCnpj = $data->prest->CNPJ;
        $prestCpf = $data->prest->CPF;

        if ($certCnpj !== null && $prestCnpj !== null && $certCnpj !== $prestCnpj) {
            throw new NfseException(
                sprintf(
                    'CNPJ do certificado (%s) não corresponde ao CNPJ do prestador (%s). '
                    .'Use validateIdentity: false se o certificado pertence a um representante legal.',
                    $certCnpj,
                    $prestCnpj,
                )
            );
        }

        if ($certCpf !== null && $prestCpf !== null && $certCpf !== $prestCpf) {
            throw new NfseException(
                sprintf(
                    'CPF do certificado (%s) não corresponde ao CPF do prestador (%s). '
                    .'Use validateIdentity: false se o certificado pertence a um representante legal.',
                    $certCpf,
                    $prestCpf,
                )
            );
        }
    }
}
