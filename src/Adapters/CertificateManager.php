<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use DateTimeImmutable;
use NFePHP\Common\Certificate;
use OwnerPro\Nfsen\Contracts\Driven\ExtractsAuthorIdentity;
use OwnerPro\Nfsen\Exceptions\CertificateExpiredException;
use OwnerPro\Nfsen\Exceptions\CertificateNotYetValidException;
use SensitiveParameter;

final readonly class CertificateManager implements ExtractsAuthorIdentity
{
    private Certificate $certificate;

    public function __construct(#[SensitiveParameter] string $pfxContent, #[SensitiveParameter] string $password, ?DateTimeImmutable $now = null)
    {
        $this->certificate = Certificate::readPfx($pfxContent, $password);

        $now ??= new DateTimeImmutable('now');

        if ($this->certificate->isExpired()) {
            throw new CertificateExpiredException(
                sprintf('Certificado expirado em %s (UTC).', $this->certificate->getValidTo()->format('d/m/Y H:i:s')),
            );
        }

        if ($now < $this->certificate->getValidFrom()) {
            throw new CertificateNotYetValidException(
                sprintf('Certificado ainda não é válido: início da vigência em %s (UTC).', $this->certificate->getValidFrom()->format('d/m/Y H:i:s')),
            );
        }
    }

    /**
     * @internal This method is not part of the public API.
     *           The Certificate object contains private key material.
     *           Do not log, serialize, or cache the returned object.
     */
    public function getCertificate(): Certificate
    {
        return $this->certificate;
    }

    /** @return array{cnpj: ?string, cpf: ?string} */
    public function extract(): array
    {
        $cnpj = $this->certificate->getCnpj() ?: null;
        $cpf = $this->certificate->getCpf() ?: null;

        return ['cnpj' => $cnpj, 'cpf' => $cpf];
    }
}
