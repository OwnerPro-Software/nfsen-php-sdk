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

        // Compara contra o EMITENTE, não contra o prestador: com tpEmit 2 ou 3 quem
        // assina é o tomador ou o intermediário, e cobrar deles o CNPJ do prestador
        // reprovava justamente a emissão legítima.
        $emitter = $data->emitterIdentity();
        $emitterCnpj = $emitter['cnpj'];
        $emitterCpf = $emitter['cpf'];
        $emitterRole = $data->infDPS->tpEmit->label();

        if ($certCnpj !== null && $emitterCnpj !== null && $certCnpj !== $emitterCnpj) {
            throw new NfseException(
                sprintf(
                    'CNPJ do certificado (%s) não corresponde ao CNPJ do %s, que emite a DPS (%s). '
                    .'Use validateIdentity: false se o certificado pertence a um representante legal.',
                    $certCnpj,
                    $emitterRole,
                    $emitterCnpj,
                )
            );
        }

        if ($certCpf !== null && $emitterCpf !== null && $certCpf !== $emitterCpf) {
            throw new NfseException(
                sprintf(
                    'CPF do certificado (%s) não corresponde ao CPF do %s, que emite a DPS (%s). '
                    .'Use validateIdentity: false se o certificado pertence a um representante legal.',
                    $certCpf,
                    $emitterRole,
                    $emitterCpf,
                )
            );
        }

        // Tipos cruzados — e-CPF contra emitente que só declara CNPJ, ou o inverso.
        // Nenhuma das comparações acima toca neste caso, porque cada uma exige os
        // dois lados do MESMO campo; sem esta guarda a DPS era assinada e enviada
        // sem checagem alguma, que é o oposto do que validateIdentity promete.
        // Emitente sem inscrição federal (NIF/cNaoNIF) fica de fora: não há o que
        // comparar, e reprovar ali seria negar o único formato que lhe resta.
        $certHasRegistration = $certCnpj !== null || $certCpf !== null;
        $emitterHasRegistration = $emitterCnpj !== null || $emitterCpf !== null;
        $sharedDocumentType = ($certCnpj !== null && $emitterCnpj !== null) || ($certCpf !== null && $emitterCpf !== null);

        if ($certHasRegistration && $emitterHasRegistration && ! $sharedDocumentType) {
            throw new NfseException(
                sprintf(
                    'O certificado identifica-se por %s e o %s, que emite a DPS, por %s — não há como conferir se são a mesma pessoa. '
                    .'Use validateIdentity: false se o certificado pertence a um representante legal.',
                    $certCnpj !== null ? 'CNPJ' : 'CPF',
                    $emitterRole,
                    $emitterCnpj !== null ? 'CNPJ' : 'CPF',
                )
            );
        }
    }
}
