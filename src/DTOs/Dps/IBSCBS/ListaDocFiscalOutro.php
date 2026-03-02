<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

/**
 * @phpstan-type ListaDocFiscalOutroArray array{cMunDocFiscal: string, nDocFiscal: string, xDocFiscal: string}
 */
final readonly class ListaDocFiscalOutro
{
    public function __construct(
        public string $cMunDocFiscal,
        public string $nDocFiscal,
        public string $xDocFiscal,
    ) {}

    /** @phpstan-param ListaDocFiscalOutroArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
