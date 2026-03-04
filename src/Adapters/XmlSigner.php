<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Adapters;

use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use Pulsar\NfseNacional\Contracts\Driven\SignsXml;

final class XmlSigner implements SignsXml
{
    private readonly int $algorithm;

    /**
     * @var bool[]|null[]
     */
    private array $canonical = [true, false, null, null]; // @pest-mutate-ignore

    public function __construct(
        private readonly Certificate $certificate,
        string $signingAlgorithm = 'sha1',
    ) {
        $this->algorithm = match ($signingAlgorithm) {
            'sha256' => OPENSSL_ALGO_SHA256,
            'sha1' => OPENSSL_ALGO_SHA1,
            default => throw new InvalidArgumentException(sprintf("Algoritmo de assinatura não suportado: %s. Use 'sha1' ou 'sha256'.", $signingAlgorithm)),
        };
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
