<?php

namespace Pulsar\NfseNacional\Signing;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

class XmlSigner
{
    private int $algorithm;
    private array $canonical = [true, false, null, null];

    public function __construct(
        private readonly Certificate $certificate,
        string $signingAlgorithm = 'sha1',
    ) {
        $this->algorithm = $signingAlgorithm === 'sha256'
            ? OPENSSL_ALGO_SHA256
            : OPENSSL_ALGO_SHA1;
    }

    public function sign(string $xml, string $tagname, string $rootname): string
    {
        return Signer::sign(
            $this->certificate,
            $xml,
            $tagname,
            'Id',
            $this->algorithm,
            $this->canonical,
            $rootname,
        );
    }
}
