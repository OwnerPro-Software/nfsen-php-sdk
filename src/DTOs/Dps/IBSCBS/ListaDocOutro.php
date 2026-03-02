<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

final readonly class ListaDocOutro
{
    public function __construct(
        public string $nDoc,
        public string $xDoc,
    ) {}
}
