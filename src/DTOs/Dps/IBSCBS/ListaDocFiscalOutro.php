<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

final readonly class ListaDocFiscalOutro
{
    public function __construct(
        public string $cMunDocFiscal,
        public string $nDocFiscal,
        public string $xDocFiscal,
    ) {}
}
