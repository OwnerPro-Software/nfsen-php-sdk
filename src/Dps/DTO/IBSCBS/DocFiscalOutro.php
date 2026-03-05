<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

/**
 * @phpstan-type DocFiscalOutroArray array{cMunDocFiscal: string, nDocFiscal: string, xDocFiscal: string}
 */
final readonly class DocFiscalOutro
{
    public function __construct(
        public string $cMunDocFiscal,
        public string $nDocFiscal,
        public string $xDocFiscal,
    ) {}

    /** @phpstan-param DocFiscalOutroArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
