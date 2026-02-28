<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Certificates;

use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Exceptions\CertificateExpiredException;

final readonly class CertificateManager
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
}
