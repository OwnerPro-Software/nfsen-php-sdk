<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

use Pulsar\NfseNacional\Exceptions\InvalidDpsArgument;

final readonly class InfoReeRepRes
{
    /** @param list<ListaDocReeRepRes> $documentos */
    public function __construct(
        public array $documentos,
    ) {
        if ($documentos === []) {
            throw new InvalidDpsArgument('documentos deve conter ao menos um item.');
        }
    }
}
