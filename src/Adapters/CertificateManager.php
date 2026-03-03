<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Adapters;

use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Contracts\Driven\ExtractsAuthorIdentity;
use Pulsar\NfseNacional\Exceptions\CertificateExpiredException;

final readonly class CertificateManager implements ExtractsAuthorIdentity
{
    private Certificate $certificate;

    public function __construct(string $pfxContent, string $password)
    {
        $this->certificate = Certificate::readPfx($pfxContent, $password);

        if ($this->certificate->isExpired()) {
            throw new CertificateExpiredException('Certificate is expired.');
        }
    }

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
