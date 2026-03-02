<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

use Pulsar\NfseNacional\Enums\Dps\IBSCBS\TipoChaveDFe;

final readonly class ListaDocDFe
{
    public function __construct(
        public TipoChaveDFe $tipoChaveDFe,
        public string $chaveDFe,
        public ?string $xTipoChaveDFe = null,
    ) {}
}
