<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

/**
 * @phpstan-import-type DocumentosArray from Documentos
 *
 * @phpstan-type GReeRepResArray array{documentos: list<DocumentosArray>}
 */
final readonly class GReeRepRes
{
    /** @param list<Documentos> $documentos */
    public function __construct(
        public array $documentos,
    ) {
        if ($documentos === []) {
            throw new InvalidDpsArgument('documentos deve conter ao menos um item.');
        }
    }

    /** @phpstan-param GReeRepResArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            documentos: array_map(Documentos::fromArray(...), $data['documentos']),
        );
    }
}
