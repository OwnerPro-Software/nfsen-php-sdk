<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

/**
 * @phpstan-type ListaDocOutroArray array{nDoc: string, xDoc: string}
 */
final readonly class ListaDocOutro
{
    public function __construct(
        public string $nDoc,
        public string $xDoc,
    ) {}

    /** @phpstan-param ListaDocOutroArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
